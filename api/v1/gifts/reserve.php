<?php
/**
 * API Endpoint para reservar un regalo
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

// Obtener ID de regalo y datos de reserva
$giftId = (int) $data['gift_id'];

// Nombre de quien reserva (opcional si está autenticado)
$reserverName = $data['reserver_name'] ?? '';
$reserverEmail = $data['reserver_email'] ?? '';
$reserverMessage = $data['message'] ?? '';
$isAnonymous = isset($data['anonymous']) && $data['anonymous'] == '1';

// Obtener sesión si existe
SessionManager::startSecureSession();
$userId = SessionManager::get('user_id');

// Si no hay nombre pero hay usuario, usar datos de usuario
if (empty($reserverName) && $userId) {
    try {
        $db = Connection::getInstance();
        $query = new QueryBuilder($db);
        $user = $query->table('users')->findOne(['id' => $userId]);
        
        if ($user) {
            $reserverName = $user['name'];
            $reserverEmail = $user['email'];
        }
    } catch (\Exception $e) {
        // Continuar sin datos de usuario
        error_log('Error al obtener datos de usuario: ' . $e->getMessage());
    }
}

// Validar que al menos hay nombre si no está autenticado
if (!$userId && empty($reserverName)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'error' => 'Se requiere nombre para reservar']);
    exit;
}

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
    
    // Verificar que el regalo esté disponible
    if ($gift['status'] !== 'available') {
        $db->rollBack();
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'Este regalo ya ha sido reservado']);
        exit;
    }
    
    // Obtener información de la lista
    $list = $query->table('gift_lists')->findOne(['id' => $gift['list_id']]);
    
    if (!$list) {
        $db->rollBack();
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'error' => 'Lista no encontrada']);
        exit;
    }
    
    // Actualizar estado del regalo a reservado
    $updated = $query->table('gifts')->update(
        [
            'status' => 'reserved',
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
    
    // Guardar información de reserva
    $reservationData = [
        'gift_id' => $giftId,
        'list_id' => $gift['list_id'],
        'user_id' => $userId ?: null,
        'name' => $isAnonymous ? 'Anónimo' : $reserverName,
        'email' => $reserverEmail,
        'message' => $reserverMessage,
        'is_anonymous' => $isAnonymous ? 1 : 0,
        'reservation_date' => date('Y-m-d H:i:s'),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'status' => 'active'
    ];
    
    $reservationId = $query->table('gift_reservations')->insert($reservationData);
    
    if (!$reservationId) {
        $db->rollBack();
        http_response_code(500); // Internal Server Error
        echo json_encode(['success' => false, 'error' => 'Error al registrar la reserva']);
        exit;
    }
    
    // Registrar evento de reserva
    $query->table('gift_events')->insert([
        'gift_id' => $giftId,
        'list_id' => $gift['list_id'],
        'user_id' => $userId ?: null,
        'event_type' => 'reserved',
        'details' => json_encode([
            'reservation_id' => $reservationId,
            'is_anonymous' => $isAnonymous
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Si no es anónimo, enviar notificación al dueño de la lista
    if (!$isAnonymous && !empty($list['user_id'])) {
        $query->table('notifications')->insert([
            'user_id' => $list['user_id'],
            'type' => 'gift_reserved',
            'title' => 'Regalo reservado',
            'message' => $reserverName . ' ha reservado el regalo "' . $gift['title'] . '"',
            'data' => json_encode([
                'gift_id' => $giftId,
                'list_id' => $gift['list_id'],
                'reservation_id' => $reservationId
            ]),
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Confirmar transacción
    $db->commit();
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'data' => [
            'reservation_id' => $reservationId,
            'gift_id' => $giftId,
            'status' => 'reserved',
            'message' => 'Regalo reservado exitosamente'
        ]
    ]);
    exit;
    
} catch (\Exception $e) {
    // Rollback en caso de error
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Registrar error
    error_log('Error en API de reserva: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
    exit;
}