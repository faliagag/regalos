<?php
/**
 * Procesamiento de guardado de lista de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';
require_once __DIR__ . '/upload.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;
use Features\GiftList\Create\ImageUploader;

// Verificar que el usuario esté autenticado
SessionManager::checkAccess('/login?redirect=/list/create');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /list/create');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /list/create?error=csrf');
    exit;
}

// Sanitizar datos
$data = Sanitizer::cleanInput($_POST);

// Validaciones básicas (campos requeridos)
if (empty($data['title']) || empty($data['privacy'])) {
    header('Location: /list/create?error=validation&message=Campos+obligatorios+incompletos');
    exit;
}

// Verificar contraseña si es lista privada con password
if ($data['privacy'] === 'password' && empty($data['list_password'])) {
    header('Location: /list/create?error=validation&message=Se+requiere+contraseña');
    exit;
}

try {
    // Procesar imagen de portada si se ha subido
    $imagePath = null;
    
    if (isset($_FILES['cover_image']) && !empty($_FILES['cover_image']['name'])) {
        $uploader = new ImageUploader('/uploads/lists');
        $imagePath = $uploader->upload($_FILES['cover_image']);
        
        if ($uploader->hasErrors()) {
            header('Location: /list/create?error=upload&message=' . urlencode(implode(', ', $uploader->getErrors())));
            exit;
        }
    }
    
    // Obtener ID de usuario de la sesión
    $userId = SessionManager::get('user_id');
    
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Preparar datos para inserción
    $listData = [
        'user_id' => $userId,
        'title' => $data['title'],
        'description' => $data['description'] ?? '',
        'occasion' => $data['occasion'] ?? '',
        'event_date' => !empty($data['event_date']) ? $data['event_date'] : null,
        'image_path' => $imagePath,
        'privacy' => $data['privacy'],
        'allow_comments' => isset($data['allow_comments']) ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
    
    // Si es privada con contraseña, almacenar hash de la contraseña
    if ($data['privacy'] === 'password' && !empty($data['list_password'])) {
        $listData['password_hash'] = password_hash($data['list_password'], PASSWORD_DEFAULT);
    }
    
    // Generar slug único para la URL
    $baseSlug = $this->generateSlug($data['title']);
    $slug = $baseSlug;
    $counter = 1;
    
    // Verificar si el slug ya existe y generar uno único
    while ($query->table('gift_lists')->count(['slug' => $slug]) > 0) {
        $slug = $baseSlug . '-' . $counter++;
    }
    
    $listData['slug'] = $slug;
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Insertar lista
    $listId = $query->table('gift_lists')->insert($listData);
    
    if (!$listId) {
        // Rollback y redirigir con error
        $db->rollBack();
        header('Location: /list/create?error=save');
        exit;
    }
    
    // Generar código de acceso aleatorio para compartir
    $accessCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    
    // Guardar código de acceso
    $query->table('list_access_codes')->insert([
        'list_id' => $listId,
        'access_code' => $accessCode,
        'created_at' => date('Y-m-d H:i:s'),
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
    ]);
    
    // Confirmar transacción
    $db->commit();
    
    // Redirigir según opción seleccionada
    if (isset($data['redirect_to_items']) && $data['redirect_to_items'] == '1') {
        // Redirigir a añadir regalos
        header('Location: /gift/add?list_id=' . $listId);
    } else {
        // Redirigir a ver la lista creada
        header('Location: /list/view/' . $slug . '?success=created');
    }
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al crear lista: ' . $e->getMessage());
    
    // Redirigir con error
    header('Location: /list/create?error=system');
    exit;
}

/**
 * Genera un slug a partir de un título
 * 
 * @param string $title Título para convertir a slug
 * @return string Slug generado
 */
function generateSlug(string $title): string {
    // Convertir a minúsculas y reemplazar espacios por guiones
    $slug = strtolower($title);
    
    // Eliminar caracteres especiales y acentos
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Limitar longitud
    $slug = substr($slug, 0, 100);
    
    // Eliminar guiones al inicio y final
    return trim($slug, '-');
}