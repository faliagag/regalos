<?php
/**
 * Procesamiento de guardado de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../../includes/Validator.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;

// Verificar que el usuario esté autenticado
SessionManager::checkAccess('/login?redirect=/gift/add');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /gift/add');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /gift/add?error=csrf');
    exit;
}

// Obtener ID de usuario
$userId = SessionManager::get('user_id');

// Sanitizar datos
$data = Sanitizer::cleanInput($_POST);

// Validar datos básicos
$validator = new Validator($data);
$validator->required(['title', 'list_id']);

if (!$validator->isValid()) {
    header('Location: /gift/add?error=validation');
    exit;
}

// Obtener valores
$listId = (int) $data['list_id'];
$title = $data['title'];
$description = $data['description'] ?? '';
$price = !empty($data['price']) ? (float) $data['price'] : null;
$url = $data['url'] ?? '';
$imageUrl = $data['image_url'] ?? '';
$category = $data['category'] ?? '';
$priority = $data['priority'] ?? 'medium';
$addMore = isset($data['add_more']) && $data['add_more'] == '1';

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Verificar propiedad de la lista
    $list = $query->table('gift_lists')->findOne([
        'id' => $listId,
        'user_id' => $userId
    ]);
    
    if (!$list) {
        header('Location: /gift/add?error=list');
        exit;
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Preparar datos del regalo
    $giftData = [
        'list_id' => $listId,
        'title' => $title,
        'description' => $description,
        'price' => $price,
        'url' => $url,
        'image_url' => $imageUrl,
        'category' => $category,
        'priority' => $priority,
        'status' => 'available',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Insertar regalo
    $giftId = $query->table('gifts')->insert($giftData);
    
    if (!$giftId) {
        // Rollback y redirigir con error
        $db->rollBack();
        header('Location: /gift/add?error=save');
        exit;
    }
    
    // Registrar evento de creación de regalo
    $query->table('gift_events')->insert([
        'gift_id' => $giftId,
        'list_id' => $listId,
        'user_id' => $userId,
        'event_type' => 'created',
        'details' => json_encode([
            'title' => $title,
            'price' => $price
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Confirmar transacción
    $db->commit();
    
    // Redirigir según opción seleccionada
    if ($addMore) {
        // Redirigir a añadir otro regalo para la misma lista
        header('Location: /gift/add?list_id=' . $listId . '&success=created');
    } else {
        // Redirigir a ver la lista
        header('Location: /list/view/' . $list['slug'] . '?success=gift_added');
    }
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al añadir regalo: ' . $e->getMessage());
    
    // Rollback si hay transacción activa
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Redirigir con error
    header('Location: /gift/add?error=system');
    exit;
}