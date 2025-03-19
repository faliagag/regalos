<?php
/**
 * Procesamiento de edición de lista de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../Create/upload.php';
require_once __DIR__ . '/../../../includes/Cache.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;
use Features\GiftList\Create\ImageUploader;

// Verificar que el usuario esté autenticado
SessionManager::checkAccess('/login?redirect=/list/edit');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /list/edit?error=csrf');
    exit;
}

// Sanitizar datos
$data = Sanitizer::cleanInput($_POST);

// Obtener ID de usuario y de lista
$userId = SessionManager::get('user_id');
$listId = isset($data['list_id']) && is_numeric($data['list_id']) ? (int)$data['list_id'] : null;

// Verificar si se proporcionó un ID de lista
if (!$listId) {
    header('Location: /dashboard?error=invalid_list');
    exit;
}

// Validaciones básicas (campos requeridos)
if (empty($data['title']) || empty($data['privacy'])) {
    header('Location: /list/edit/' . $listId . '?error=validation&message=Campos+obligatorios+incompletos');
    exit;
}

// Verificar contraseña si es lista privada con password
if ($data['privacy'] === 'password' && isset($data['password_changed']) && empty($data['list_password'])) {
    header('Location: /list/edit/' . $listId . '?error=validation&message=Se+requiere+contraseña');
    exit;
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Verificar que la lista exista y pertenezca al usuario
    $list = $query->table('gift_lists')
        ->findOne([
            'id' => $listId,
            'user_id' => $userId
        ]);
    
    if (!$list) {
        header('Location: /dashboard?error=list_not_found');
        exit;
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Procesar imagen de portada si se ha subido
    $imagePath = $list['image_path'];
    
    // Si se solicitó eliminar la imagen
    if (isset($data['remove_image']) && $data['remove_image'] == '1') {
        // Eliminar archivo físico si existe
        if (!empty($imagePath)) {
            $uploader = new ImageUploader();
            $uploader->deleteImage($imagePath);
        }
        $imagePath = null;
    } 
    // Si se subió una nueva imagen
    elseif (isset($_FILES['cover_image']) && !empty($_FILES['cover_image']['name'])) {
        $uploader = new ImageUploader('/uploads/lists');
        $newImagePath = $uploader->upload($_FILES['cover_image']);
        
        if ($uploader->hasErrors()) {
            header('Location: /list/edit/' . $listId . '?error=upload&message=' . urlencode(implode(', ', $uploader->getErrors())));
            exit;
        }
        
        // Eliminar imagen anterior si existía
        if (!empty($list['image_path']) && $newImagePath !== $list['image_path']) {
            $uploader->deleteImage($list['image_path']);
        }
        
        $imagePath = $newImagePath;
    }
    
    // Preparar datos para actualización
    $listData = [
        'title' => $data['title'],
        'description' => $data['description'] ?? '',
        'occasion' => $data['occasion'] ?? '',
        'event_date' => !empty($data['event_date']) ? $data['event_date'] : null,
        'image_path' => $imagePath,
        'privacy' => $data['privacy'],
        'allow_comments' => isset($data['allow_comments']) ? 1 : 0,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Si es privada con contraseña y se solicitó cambiar la contraseña
    if ($data['privacy'] === 'password' && isset($data['password_changed']) && !empty($data['list_password'])) {
        $listData['password_hash'] = password_hash($data['list_password'], PASSWORD_DEFAULT);
    }
    
    // Actualizar lista
    $updated = $query->table('gift_lists')
        ->update($listData, ['id' => $listId]);
    
    if (!$updated) {
        // Rollback y redirigir con error
        $db->rollBack();
        header('Location: /list/edit/' . $listId . '?error=save');
        exit;
    }
    
    // Confirmar transacción
    $db->commit();
    
    // Eliminar caché relacionada con esta lista
    Cache::clear('page_list_' . $list['slug']);
    Cache::clear('home_popular_lists');
    Cache::clear('home_recent_lists');
    
    // Redirigir a ver la lista
    header('Location: /list/view/' . $list['slug'] . '?success=list_updated');
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al editar lista: ' . $e->getMessage());
    
    // Rollback si hay transacción activa
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Redirigir con error
    header('Location: /list/edit/' . $listId . '?error=system');
    exit;
}