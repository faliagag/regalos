<?php
/**
 * Cierre de sesión
 */
require_once __DIR__ . '/../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../core/Auth/CookieHandler.php';
require_once __DIR__ . '/../../core/Security/CSRF.php';

use Core\Auth\SessionManager;
use Core\Auth\CookieHandler;
use Core\Security\CSRF;

// Verificar si es una petición POST (recomendado para mayor seguridad)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF para prevenir ataques de cierre de sesión forzado
    if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
        // Redirigir con un mensaje de error
        header('Location: /dashboard?error=csrf');
        exit;
    }
}

// Obtener ID de usuario antes de cerrar sesión (para registro)
$userId = SessionManager::get('user_id');

// Eliminar cookie "Recordarme" si existe
if (CookieHandler::has('remember_me')) {
    // Si registramos tokens en base de datos, los eliminaremos
    if ($userId) {
        try {
            require_once __DIR__ . '/../../core/Database/Connection.php';
            require_once __DIR__ . '/../../core/Database/QueryBuilder.php';
            
            use Core\Database\Connection;
            use Core\Database\QueryBuilder;
            
            $db = Connection::getInstance();
            $query = new QueryBuilder($db);
            
            // Eliminar tokens almacenados para este usuario
            $query
                ->table('remember_tokens')
                ->delete(['user_id' => $userId]);
                
        } catch (\Exception $e) {
            // Solo registrar error, no interrumpir el proceso de cierre de sesión
            error_log('Error al eliminar tokens remember_me: ' . $e->getMessage());
        }
    }
    
    // Eliminar la cookie del navegador del cliente
    CookieHandler::delete('remember_me');
}

// Registrar cierre de sesión si hay usuario identificado
if ($userId) {
    try {
        require_once __DIR__ . '/../../core/Database/Connection.php';
        require_once __DIR__ . '/../../core/Database/QueryBuilder.php';
        
        use Core\Database\Connection;
        use Core\Database\QueryBuilder;
        
        $db = Connection::getInstance();
        $query = new QueryBuilder($db);
        
        // Registrar logout
        $query
            ->table('login_logs')
            ->insert([
                'user_id' => $userId,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'logout_time' => date('Y-m-d H:i:s')
            ]);
            
    } catch (\Exception $e) {
        // Solo registrar error, no interrumpir el proceso de cierre de sesión
        error_log('Error al registrar logout: ' . $e->getMessage());
    }
}

// Destruir la sesión actual
SessionManager::destroy();

// Redireccionar a la página de inicio de sesión
header('Location: /login?logout=success');
exit;