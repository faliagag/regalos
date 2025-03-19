<?php
/**
 * Procesamiento de inicio de sesión
 */
require_once __DIR__ . '/../../core/Security/CSRF.php';
require_once __DIR__ . '/../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../core/Database/Connection.php';
require_once __DIR__ . '/../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../core/Auth/CookieHandler.php';
require_once __DIR__ . '/validate.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;
use Core\Auth\CookieHandler;
use Features\UserLogin\Validator;

// Iniciar sesión de forma segura
SessionManager::startSecureSession();

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /login?error=csrf');
    exit;
}

// Sanitizar datos de entrada
$data = Sanitizer::cleanInput($_POST);

// Validación básica
$redirectError = Validator::validateAndGetRedirect($data);
if ($redirectError) {
    header('Location: ' . $redirectError);
    exit;
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Buscar usuario por email
    $user = $query
        ->table('users')
        ->findOne(['email' => $data['email']]);
    
    // Verificar si el usuario existe y la contraseña es correcta
    if (
        !$user || 
        !password_verify($data['password'], $user['password'])
    ) {
        // Respuesta genérica para no revelar si el email existe o no
        header('Location: /login?error=invalid&email=' . urlencode($data['email']));
        exit;
    }
    
    // Verificar si la cuenta está activa
    if (isset($user['active']) && !$user['active']) {
        header('Location: /login?error=inactive&email=' . urlencode($data['email']));
        exit;
    }
    
    // Verificar si la contraseña necesita actualización (hash antiguo)
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        // Actualizar hash
        $newHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $query
            ->table('users')
            ->update(
                ['password' => $newHash],
                ['id' => $user['id']]
            );
    }
    
    // Registrar login exitoso
    $query
        ->table('login_logs')
        ->insert([
            'user_id' => $user['id'],
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'login_time' => date('Y-m-d H:i:s')
        ]);
    
    // Establecer sesión de usuario
    SessionManager::setUser([
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'] ?? 'user'
    ]);
    
    // Establecer cookie "Recordarme" si fue solicitado
    if (isset($data['remember_me']) && $data['remember_me'] == 1) {
        // Generar token único
        $rememberToken = bin2hex(random_bytes(32));
        
        // Guardar token en base de datos para validación posterior
        $query
            ->table('remember_tokens')
            ->insert([
                'user_id' => $user['id'],
                'token' => password_hash($rememberToken, PASSWORD_DEFAULT),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
            ]);
        
        // Establecer cookie segura
        CookieHandler::setRememberMe($user['id'], $rememberToken, 30);
    }
    
    // Redireccionar al dashboard o página solicitada
    $redirectTo = SessionManager::get('redirect_after_login', '/dashboard');
    SessionManager::remove('redirect_after_login');
    
    header('Location: ' . $redirectTo);
    exit;
    
} catch (\Exception $e) {
    // Registrar error de forma segura (sin exponer detalles sensibles al usuario)
    error_log('Error en inicio de sesión: ' . $e->getMessage());
    
    // Redireccionar con error genérico
    header('Location: /login?error=system');
    exit;
}