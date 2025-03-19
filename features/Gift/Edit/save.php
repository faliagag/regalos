<?php
/**
 * Procesamiento de edición de regalo
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
SessionManager::checkAccess('/login?redirect=/dashboard');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /gift/edit?error=csrf');
    exit;
}

// Sanitizar datos
$data = Sanitizer::cleanInput($_POST);

// Obtener ID de usuario y de regalo
$userId = SessionManager::get('user_id');
$giftId = isset($data['gift_id']) && is_numeric($data['gift_id']) ? (int)$data['gift_id'] : null;

// Verificar si se proporcionó un ID de regalo
if (!$giftId) {
    header('Location: /dashboard?error=invalid_gift');
    exit;
}

// Validar datos básicos
$validator = new Validator($data);
$validator->required(['title']);

if (!$validator->isValid()) {
    header('Location: /gift/edit/' . $giftId . '?error=validation');
    exit;
}

// Obtener valores
$title = $data['title'];
$description = $data['description'] ?? '';
$price = !empty($data['price']) ? (float) $data['price'] : null;
$url = $data['url'] ?? '';
$imageUrl = $data['image_url'] ?? '';
$category = $data['category'] ?? '';
$priority = $data['priority'] ?? 'medium';

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Verificar propiedad del regalo
    $gift = $query->raw(
        "SELECT g.* FROM gifts g
        JOIN gift_lists l ON g.list_id = l.id
        WHERE g.id = :gift_id AND l.user_id = :user_id",
        ['gift_id' => $giftId, 'user_id' => $userId]
    );
    
    if (empty($gift)) {
        header('Location: /dashboard?error=gift_not_found');
        exit;
    }
    
    $gift = $gift[0]; // Obtener el primer resultado
    
    // Obtener la lista a la que pertenece
    $list = $query->table('gift_lists')
        ->findOne(['id' => $gift['list_id']]);
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Preparar datos para actualización
    $giftData = [
        'title' => $title,
        'description' => $description,
        'price' => $price,
        'url' => $url,
        'image_url' => $imageUrl,
        'category' => $category,
        'priority' => $priority,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Actualizar regalo
    $updated = $query->table('gifts')
        ->update($giftData, ['id' => $giftId]);
    
    if (!$updated) {
        // Rollback y redirigir con error
        $db->rollBack();
        header('Location: /gift/edit/' . $giftId . '?error=save');
        exit;
    }
    
    // Registrar evento de actualización
    $query->table('gift_events')->insert([
        'gift_id' => $giftId,
        'list_id' => $gift['list_id'],
        'user_id' => $userId,
        'event_type' => 'updated',
        'details' => json_encode([
            'title' => $title,
            'price' => $price,
            'previous_title' => $gift['title'],
            'previous_price' => $gift['price']
        ]),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Confirmar transacción
    $db->commit();
    
    // Redirigir a la lista con mensaje de éxito
    header('Location: /list/view/' . $list['slug'] . '?success=gift_updated');
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al actualizar regalo: ' . $e->getMessage());
    
    // Rollback si hay transacción activa
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Redirigir con error
    header('Location: /gift/edit/' . $giftId . '?error=system');
    exit;
}