<?php
/**
 * Formulario de inicio de sesión
 */
require_once __DIR__ . '/../../core/Security/CSRF.php';
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';

use Core\Security\CSRF;
use Core\Security\Headers;
use Core\Auth\SessionManager;

// Inicialización
Headers::setSecureHeaders();
SessionManager::startSecureSession();

// Verificar si ya está autenticado
if (SessionManager::isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

// Obtener mensajes de error si existen
$error = $_GET['error'] ?? '';
$errorMessage = '';

if ($error === 'invalid') {
    $errorMessage = 'Correo electrónico o contraseña incorrectos.';
} elseif ($error === 'csrf') {
    $errorMessage = 'Error de seguridad. Por favor, intente nuevamente.';
} elseif ($error === 'empty') {
    $errorMessage = 'Por favor, complete todos los campos.';
}

// Valor previo de email (para mantener en caso de error)
$prevEmail = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Listas de Regalos</title>
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>Iniciar Sesión</h1>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
            <?php endif; ?>
            
            <form action="/login/process.php" method="POST">
                <!-- Campo de token CSRF para seguridad -->
                <?= CSRF::tokenField() ?>
                
                <div class="form-group">
                    <label for="email">Correo Electrónico:</label>
                    <input type="email" id="email" name="email" 
                        value="<?= htmlspecialchars($prevEmail) ?>" 
                        required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <input type="password" id="password" name="password" 
                        required autocomplete="current-password">
                </div>
                
                <div class="form-check">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">Mantener sesión iniciada</label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                </div>
                
                <div class="auth-links">
                    <a href="/register">Crear una cuenta</a> | 
                    <a href="/reset-password">¿Olvidó su contraseña?</a>
                </div>
            </form>
        </div>
    </div>

    <script src="/assets/js/validation.js"></script>
</body>
</html>