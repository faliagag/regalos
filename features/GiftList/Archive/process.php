<?php
/**
 * Procesamiento de archivado/activación de lista de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;

// Verificar que el usuario esté autenticado
SessionManager::checkAccess('/login?redirect=/dashboard');

// Obtener parámetros
$listId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$action = $_GET['action'] ?? 'archive'; // 'archive' o 'activate'

// Verificar que se proporcionó un ID de lista
if (!$listId) {
    header('Location: /dashboard?error=invalid_list');
    exit;
}

// Verificar token CSRF si viene de un formulario POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /dashboard?error=csrf');
    exit;
}

// Obtener ID de usuario
$userId = SessionManager::get('user_id');

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Verificar propiedad de la lista
    $list = $query->table('gift_lists')
        ->findOne([
            'id' => $listId,
            'user_id' => $userId
        ]);
    
    // Verificar si la lista existe y pertenece al usuario
    if (!$list) {
        header('Location: /dashboard?error=list_not_found');
        exit;
    }
    
    // Determinar el nuevo estado según la acción
    $newStatus = ($action === 'archive') ? 'archived' : 'active';
    
    // Si la lista ya tiene el estado deseado, redirigir sin cambios
    if ($list['status'] === $newStatus) {
        $redirectUrl = '/dashboard?info=list_already_' . ($action === 'archive' ? 'archived' : 'activated');
        header('Location: ' . $redirectUrl);
        exit;
    }
    
    // Actualizar estado de la lista
    $updated = $query->table('gift_lists')
        ->update(
            [
                'status' => $newStatus,
                'updated_at' => date('Y-m-d H:i:s')
            ],
            ['id' => $listId]
        );
    
    if (!$updated) {
        throw new \Exception('Error al actualizar estado de la lista');
    }
    
    // Redireccionar según el destino solicitado o a dashboard por defecto
    $redirectTo = $_GET['redirect'] ?? '/dashboard';
    
    // Añadir mensaje de éxito
    $successParam = ($action === 'archive') ? 'list_archived' : 'list_activated';
    $redirectTo .= (strpos($redirectTo, '?') !== false) ? '&success=' . $successParam : '?success=' . $successParam;
    
    header('Location: ' . $redirectTo);
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al ' . ($action === 'archive' ? 'archivar' : 'activar') . ' lista: ' . $e->getMessage());
    
    // Redirigir con error
    header('Location: /dashboard?error=system');
    exit;
}