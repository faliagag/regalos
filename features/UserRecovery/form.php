<?php
/**
 * Formulario de recuperación de contraseña
 */
require_once __DIR__ . '/../../core/Security/CSRF.php';
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';

use Core\Security\CSRF;
use Core\Security\Headers;
use Core\Auth\SessionManager;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Inicialización
SessionManager::startSecureSession();

// Verificar si ya está autenticado
if (SessionManager::isLoggedIn()) {
    header('Location: /dashboard');
    exit;
}

// Determinar el paso del proceso de recuperación
$step = $_GET['step'] ?? 'request';
$token = $_GET['token'] ?? '';

// Obtener mensajes de error si existen
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$errorMessage = '';
$successMessage = '';

if ($error === 'email_not_found') {
    $errorMessage = 'No se encontró ninguna cuenta con ese correo electrónico.';
} elseif ($error === 'invalid_token') {
    $errorMessage = 'El enlace de recuperación no es válido o ha expirado.';
} elseif ($error === 'passwords_dont_match') {
    $errorMessage = 'Las contraseñas no coinciden.';
} elseif ($error === 'password_too_short') {
    $errorMessage = 'La contraseña debe tener al menos 8 caracteres.';
} elseif ($error === 'system') {
    $errorMessage = 'Error del sistema. Por favor, inténtelo de nuevo más tarde.';
}

if ($success === 'email_sent') {
    $successMessage = 'Se ha enviado un correo electrónico con instrucciones para restablecer su contraseña.';
} elseif ($success === 'password_reset') {
    $successMessage = 'Su contraseña ha sido restablecida correctamente. Ya puede iniciar sesión.';
}

// Definir página activa y título
$pageName = '';
$pageTitle = 'Recuperar Contraseña';

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h1 class="h4 mb-0 text-center">Recuperar Contraseña</h1>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($successMessage): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($successMessage) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step === 'request'): ?>
                        <!-- Paso 1: Solicitar recuperación -->
                        <p class="text-center mb-4">Ingresa tu correo electrónico y te enviaremos instrucciones para restablecer tu contraseña.</p>
                        
                        <form action="/reset-password/process.php" method="POST" class="needs-validation" novalidate>
                            <?= CSRF::tokenField() ?>
                            <input type="hidden" name="action" value="request_reset">
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                    value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" 
                                    required>
                                <div class="invalid-feedback">
                                    Por favor, ingresa un correo electrónico válido.
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Enviar Instrucciones</button>
                            </div>
                        </form>
                    <?php elseif ($step === 'reset' && !empty($token)): ?>
                        <!-- Paso 2: Restablecer contraseña -->
                        <p class="text-center mb-4">Ingresa tu nueva contraseña.</p>
                        
                        <form action="/reset-password/process.php" method="POST" class="needs-validation" novalidate>
                            <?= CSRF::tokenField() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                    required minlength="8">
                                <div class="form-text">
                                    La contraseña debe tener al menos 8 caracteres.
                                </div>
                                <div class="invalid-feedback">
                                    Por favor, ingresa una contraseña de al menos 8 caracteres.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" 
                                    required>
                                <div class="invalid-feedback">
                                    Las contraseñas no coinciden.
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Cambiar Contraseña</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Token inválido o paso no reconocido -->
                        <div class="alert alert-danger">
                            El enlace de recuperación no es válido o ha expirado. Por favor, solicite un nuevo enlace.
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="/reset-password" class="btn btn-primary">Volver al inicio</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer bg-white py-3 text-center">
                    <p class="mb-0">¿Recordaste tu contraseña? <a href="/login" class="text-decoration-none">Iniciar Sesión</a></p>
                </div>
            </div>
            
            <!-- Consejos de seguridad -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">Consejos de seguridad</h5>
                    <ul class="list-unstyled">
                        <li><i class="bi bi-shield-check text-success me-2"></i> Utiliza una contraseña única para cada servicio</li>
                        <li><i class="bi bi-shield-check text-success me-2"></i> Combina letras, números y símbolos</li>
                        <li><i class="bi bi-shield-check text-success me-2"></i> Evita información personal en tu contraseña</li>
                        <li><i class="bi bi-shield-check text-success me-2"></i> Cambia tus contraseñas regularmente</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validación personalizada para coincidencia de contraseñas
    const confirmPassword = document.getElementById('confirm_password');
    const password = document.getElementById('password');
    
    if (confirmPassword && password) {
        confirmPassword.addEventListener('input', function() {
            if (this.value !== password.value) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
        
        password.addEventListener('input', function() {
            if (confirmPassword.value !== '' && confirmPassword.value !== this.value) {
                confirmPassword.setCustomValidity('Las contraseñas no coinciden');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
    
    // Validación general del formulario
    const form = document.querySelector('.needs-validation');
    
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        form.classList.add('was-validated');
    });
});
</script>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>