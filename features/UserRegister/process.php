<?php
/**
 * Procesamiento de registro de usuarios
 */
require_once __DIR__ . '/../../core/Security/CSRF.php';
require_once __DIR__ . '/../../core/Security/Sanitizer.php';
require_once __DIR__ . '/../../core/Database/Connection.php';
require_once __DIR__ . '/../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../includes/Validator.php';

use Core\Security\CSRF;
use Core\Security\Sanitizer;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;

// Iniciar sesión de forma segura
SessionManager::startSecureSession();

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /register');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /register?error=csrf');
    exit;
}

// Sanitizar datos de entrada
$data = Sanitizer::cleanInput($_POST);

// Validación de datos
$validator = new Validator($data);
$validator->required(['name', 'email', 'password', 'confirm_password', 'terms'])
          ->email('email')
          ->minLength('password', 8)
          ->match('password', 'confirm_password');

if (!$validator->isValid()) {
    $errors = $validator->getErrors();
    
    if (isset($errors['name']) || isset($errors['email']) || isset($errors['terms'])) {
        header('Location: /register?error=validation&name=' . urlencode($data['name'] ?? '') . '&email=' . urlencode($data['email'] ?? ''));
    } elseif (isset($errors['password']) && strpos($errors['password'], 'al menos') !== false) {
        header('Location: /register?error=password_too_short&name=' . urlencode($data['name'] ?? '') . '&email=' . urlencode($data['email'] ?? ''));
    } elseif (isset($errors['confirm_password']) || isset($errors['password'])) {
        header('Location: /register?error=password_match&name=' . urlencode($data['name'] ?? '') . '&email=' . urlencode($data['email'] ?? ''));
    } elseif (isset($errors['email'])) {
        header('Location: /register?error=invalid_email&name=' . urlencode($data['name'] ?? ''));
    } else {
        header('Location: /register?error=validation&name=' . urlencode($data['name'] ?? '') . '&email=' . urlencode($data['email'] ?? ''));
    }
    exit;
}

// Extraer valores validados
$name = $data['name'];
$email = $data['email'];
$password = $data['password'];

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Verificar si el email ya existe
    $existingUser = $query->table('users')
        ->findOne(['email' => $email]);
    
    if ($existingUser) {
        header('Location: /register?error=email_exists&name=' . urlencode($name));
        exit;
    }
    
    // Generar hash de la contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // Preparar datos del usuario
    $userData = [
        'name' => $name,
        'email' => $email,
        'password' => $passwordHash,
        'role' => 'user',
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Iniciar transacción
    $db->beginTransaction();
    
    // Insertar usuario
    $userId = $query->table('users')
        ->insert($userData);
    
    if (!$userId) {
        // Rollback y redirigir con error
        $db->rollBack();
        header('Location: /register?error=system&name=' . urlencode($name) . '&email=' . urlencode($email));
        exit;
    }
    
    // Registrar login
    $query->table('login_logs')
        ->insert([
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'login_time' => date('Y-m-d H:i:s'),
            'status' => 'success'
        ]);
    
    // Actualizar el campo last_login en usuarios
    $query->table('users')
        ->update(
            ['last_login' => date('Y-m-d H:i:s')],
            ['id' => $userId]
        );
    
    // Confirmar transacción
    $db->commit();
    
    // Establecer sesión de usuario
    SessionManager::setUser([
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'role' => 'user'
    ]);
    
    // Redirigir a bienvenida o dashboard
    $redirectTo = $_SESSION['redirect_after_login'] ?? '/dashboard?welcome=new_user';
    SessionManager::remove('redirect_after_login');
    
    header('Location: ' . $redirectTo);
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error en registro: ' . $e->getMessage());
    
    // Rollback si hay transacción activa
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Redireccionar con error genérico
    header('Location: /register?error=system&name=' . urlencode($name) . '&email=' . urlencode($email));
    exit;
}