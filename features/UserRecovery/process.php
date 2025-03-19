<?php
/**
 * Procesamiento de recuperación de contraseña
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
    header('Location: /reset-password');
    exit;
}

// Validar token CSRF
if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
    header('Location: /reset-password?error=csrf');
    exit;
}

// Sanitizar datos de entrada
$data = Sanitizer::cleanInput($_POST);
$action = $data['action'] ?? '';

// Procesar según la acción solicitada
if ($action === 'request_reset') {
    // Validación de datos
    $validator = new Validator($data);
    $validator->required(['email'])
              ->email('email');
    
    if (!$validator->isValid()) {
        header('Location: /reset-password?error=validation&email=' . urlencode($data['email'] ?? ''));
        exit;
    }
    
    // Obtener email
    $email = $data['email'];
    
    try {
        // Conexión a base de datos
        $db = Connection::getInstance();
        $query = new QueryBuilder($db);
        
        // Buscar usuario por email
        $user = $query->table('users')
            ->findOne(['email' => $email]);
        
        if (!$user) {
            // No revelar si el email existe o no por seguridad
            // Simular éxito aunque el usuario no exista
            header('Location: /reset-password?step=request&success=email_sent');
            exit;
        }
        
        // Generar token único
        $token = bin2hex(random_bytes(32));
        
        // Calcular fecha de expiración (24 horas)
        $expireDate = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Guardar token en la base de datos
        $updated = $query->table('users')
            ->update(
                [
                    'reset_token' => $token,
                    'reset_expires' => $expireDate,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                ['id' => $user['id']]
            );
        
        if (!$updated) {
            throw new \Exception('Error al actualizar token de recuperación');
        }
        
        // Generar enlace de recuperación
        $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . 
                     $_SERVER['HTTP_HOST'] . 
                     '/reset-password?step=reset&token=' . $token;
        
        // Simular envío de correo (en producción, usar una biblioteca de correo)
        // Aquí solo registramos para depuración
        error_log('Enlace de recuperación para ' . $email . ': ' . $resetLink);
        
        // TODO: Implementar envío real de correo en producción
        
        // Redireccionar con mensaje de éxito
        header('Location: /reset-password?step=request&success=email_sent');
        exit;
        
    } catch (\Exception $e) {
        // Registrar error
        error_log('Error en solicitud de recuperación: ' . $e->getMessage());
        
        // Redireccionar con error genérico
        header('Location: /reset-password?error=system');
        exit;
    }
} elseif ($action === 'reset_password') {
    // Validación de datos
    $validator = new Validator($data);
    $validator->required(['password', 'confirm_password', 'token'])
              ->minLength('password', 8)
              ->match('password', 'confirm_password');
    
    if (!$validator->isValid()) {
        $errors = $validator->getErrors();
        
        if (isset($errors['password']) && strpos($errors['password'], 'al menos') !== false) {
            header('Location: /reset-password?step=reset&token=' . urlencode($data['token']) . '&error=password_too_short');
        } elseif (isset($errors['confirm_password']) || isset($errors['password'])) {
            header('Location: /reset-password?step=reset&token=' . urlencode($data['token']) . '&error=passwords_dont_match');
        } else {
            header('Location: /reset-password?step=reset&token=' . urlencode($data['token']) . '&error=validation');
        }
        exit;
    }
    
    // Obtener datos
    $token = $data['token'];
    $password = $data['password'];
    
    try {
        // Conexión a base de datos
        $db = Connection::getInstance();
        $query = new QueryBuilder($db);
        
        // Buscar usuario por token
        $user = $query->table('users')
            ->findOne(['reset_token' => $token]);
        
        if (!$user) {
            header('Location: /reset-password?error=invalid_token');
            exit;
        }
        
        // Verificar si el token ha expirado
        if (empty($user['reset_expires']) || strtotime($user['reset_expires']) < time()) {
            header('Location: /reset-password?error=invalid_token');
            exit;
        }
        
        // Generar hash de la nueva contraseña
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Actualizar contraseña y limpiar token
        $updated = $query->table('users')
            ->update(
                [
                    'password' => $passwordHash,
                    'reset_token' => null,
                    'reset_expires' => null,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                ['id' => $user['id']]
            );
        
        if (!$updated) {
            throw new \Exception('Error al actualizar contraseña');
        }
        
        // Registrar cambio de contraseña (opcional)
        error_log('Contraseña restablecida para usuario ID: ' . $user['id']);
        
        // Redireccionar a login con mensaje de éxito
        header('Location: /login?success=password_reset');
        exit;
        
    } catch (\Exception $e) {
        // Registrar error
        error_log('Error en restablecimiento de contraseña: ' . $e->getMessage());
        
        // Redireccionar con error genérico
        header('Location: /reset-password?step=reset&token=' . urlencode($token) . '&error=system');
        exit;
    }
} else {
    // Acción no reconocida
    header('Location: /reset-password');
    exit;
}