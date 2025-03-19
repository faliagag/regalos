<?php
/**
 * Generación de enlaces para compartir listas de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';

use Core\Security\CSRF;
use Core\Auth\SessionManager;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

// Verificar autenticación
SessionManager::checkAccess('/login');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error de seguridad']);
    exit;
}

// Verificar parámetro list_id
if (!isset($_POST['list_id']) || !is_numeric($_POST['list_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'ID de lista no válido']);
    exit;
}

$listId = (int) $_POST['list_id'];
$userId = SessionManager::get('user_id');

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
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes acceso a esta lista']);
        exit;
    }
    
    // Verificar si ya existe un código de acceso válido
    $existingCode = $query->table('list_access_codes')->findOne([
        'list_id' => $listId,
        'status' => 'active'
    ]);
    
    // Si existe un código activo y no ha expirado, usarlo
    if ($existingCode && strtotime($existingCode['expires_at']) > time()) {
        $accessCode = $existingCode['access_code'];
    } else {
        // Generar nuevo código aleatorio
        $accessCode = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // Desactivar códigos anteriores
        $query->table('list_access_codes')->update(
            ['status' => 'inactive'],
            ['list_id' => $listId]
        );
        
        // Guardar nuevo código
        $query->table('list_access_codes')->insert([
            'list_id' => $listId,
            'access_code' => $accessCode,
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year')),
            'status' => 'active'
        ]);
    }
    
    // Generar enlaces para compartir
    $shareUrl = '';
    
    // Usar el slug si existe, de lo contrario usar el código directo
    if (!empty($list['slug'])) {
        $shareUrl = $_SERVER['HTTP_HOST'] . '/list/' . $list['slug'];
    } else {
        $shareUrl = $_SERVER['HTTP_HOST'] . '/list?code=' . $accessCode;
    }
    
    // Si es lista con contraseña, añadir nota
    $passwordNote = '';
    if ($list['privacy'] === 'password') {
        $passwordNote = 'Esta lista está protegida con contraseña. Deberás compartir la contraseña por separado.';
    }
    
    // Crear enlaces para distintas plataformas
    $shareLinks = [
        'url' => 'https://' . $shareUrl,
        'whatsapp' => 'https://wa.me/?text=' . urlencode('¡Mira mi lista de regalos! https://' . $shareUrl),
        'email' => 'mailto:?subject=' . urlencode('Mi lista de regalos: ' . $list['title']) . 
                   '&body=' . urlencode('Hola, he creado una lista de regalos. Puedes verla en: https://' . $shareUrl),
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode('https://' . $shareUrl),
        'twitter' => 'https://twitter.com/intent/tweet?text=' . 
                    urlencode('¡Mira mi lista de regalos! https://' . $shareUrl),
        'access_code' => $accessCode,
        'password_note' => $passwordNote
    ];
    
    // Registrar evento de generación de enlace
    $query->table('share_events')->insert([
        'list_id' => $listId,
        'user_id' => $userId,
        'event_type' => 'link_generated',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // Devolver respuesta exitosa
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $shareLinks
    ]);
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al generar enlace: ' . $e->getMessage());
    
    // Devolver error
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error al generar enlace']);
    exit;
}