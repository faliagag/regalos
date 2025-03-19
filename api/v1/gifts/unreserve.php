<?php
/**
 * API Endpoint para liberar un regalo reservado
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../../core/Security/Headers.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Security\Headers;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;

// Establecer cabeceras para API
Headers::setAPIHeaders();

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Validar token CSRF para peticiones desde el navegador
$headers = getallheaders();
$csrfHeader = $headers['X-CSRF-Token'] ?? '';

if (!CSRF::validate($_POST['csrf_token'] ?? $csrfHeader)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Error de seguridad (CSRF)']);
    exit;
}

// Sanitizar y validar datos de entrada
$data = Sanitizer::cleanInput($_POST);

// Validar parámetros requeridos
if (!isset($data['gift_id']) || !is_numeric($data['gift_id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'ID de regalo no válido']);
    exit;
}

// Obtener ID de regalo
$giftId = (int) $data['gift_id'];

// Motivo opcional de liberación
$reason = $data['reason'] ?? '';

// Verificar sesión
SessionManager::startSecureSession();
$userId = SessionManager::get('user_id');

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Obtener información del regalo
    $gift = $query->table('gifts')->findOne(['id' => $giftId]);
    
    if (!$gift) {
        $db->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'error' => 'Regalo no encontrado']);
        exit;
    }
    
    // Verificar que el regalo esté reservado
    if ($gift['status'] !== 'reserved') {
        $db->rollBack();
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'Este regalo no está reservado actualmente']);
        exit;
    }
    
    // Obtener reserva actual
    $reservation = $query->table('gift_reservations')
        ->findOne([
            'gift_id' => $giftId,
            'status' => 'active'
        ]);
    
    if (!$reservation) {
        // Situación anómala: regalo marcado como reservado sin reserva activa
        // Proceder a liberar el regalo de todos modos
        error_log('Inconsistencia: Regalo ' . $giftId . ' marcado como reservado sin reserva activa');
    } else {
        // Verificar que solo el dueño de la lista o quien reservó puede liberar
        $listOwner = $query->table('gift_lists')
            ->findOne([
                'id' => $gift['list_id'],
                'user_id' => $userId
            ]);
        
        $isReserver = $userId && $reservation['user_id'] == $userId;
        $isListOwner = !empty($listOwner);
        
        if (!$isReserver && !$isListOwner) {
            $db->rollBack();
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'error' => 'No tienes permiso para liberar este regalo']);
            exit;
        }
        
        // Actualizar estado de la reserva a cancelada
        $updated = $query->table('gift_reservations')->update(
            [
                'status' => 'cancelled',
                'cancellation_reason' => $reason,
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by_user_id' => $userId
            ],
            ['id' => $reservation['id']]
        );
        
        if (!$updated) {
            $db->rollBack();
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'error' => 'Error al actualizar estado de la reserva']);
            exit;
        }
    }
    
    // Actualizar estado del regalo a disponible
    $updated = $query->table('gifts')->update(
        [
            'status' => 'available',
            'updated_at' => date('Y-m-d H:i:s')
        ],
        ['id' => $giftId]
    );
    
    if (!$updated) {
        $db->rollBack();
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'error' => 'Error al actualizar estado del regalo']);
        exit;
    }
    
    // Registrar evento de liberación
    $query->table('gift_events')->insert([
        'gift_id' => $giftId,
        'list_id' => $gift['list_id'],
        'user_id' => $userId,
        'event_type' => 'unreserved',
        'details' => json_encode([
            'reservation_id' => $reservation['id'] ?? null,
            'reason' => $reason,
            'by_list_owner' => $isListOwner ?? false
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Si hay un dueño de lista y la liberación la hizo el reservante, notificar al dueño
    if (isset($isReserver) && $isReserver && !empty($gift['list_id'])) {
        $list = $query->table('gift_lists')->findOne(['id' => $gift['list_id']]);
        
        if ($list && !empty($list['user_id'])) {
            $query->table('notifications')->insert([
                'user_id' => $list['user_id'],
                'type' => 'gift_unreserved',
                'title' => 'Regalo liberado',
                'message' => ($reservation['is_anonymous'] ? 'Alguien' : $reservation['name']) . 
                             ' ha liberado el regalo "' . $gift['title'] . '"',
                'data' => json_encode([
                    'gift_id' => $giftId,
                    'list_id' => $gift['list_id'],
                    'reason' => $reason
                ]),
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    // Confirmar transacción
    $db->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => [
            'gift_id' => $giftId,
            'status' => 'available',
            'message' => 'Regalo liberado exitosamente'
        ]
    ]);
    exit;
    
} catch (\Exception $e) {
    // Rollback en caso de error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Registrar error
    error_log('Error en API de liberación de regalo: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
    exit;
}