<?php
/**
 * Formulario de registro de usuarios
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

// Obtener mensajes de error si existen
$error = $_GET['error'] ?? '';
$errorMessage = '';

if ($error === 'validation') {
    $errorMessage = 'Por favor, complete todos los campos obligatorios.';
} elseif ($error === 'email_exists') {
    $errorMessage = 'Este correo electrónico ya está registrado. Por favor, utilice otro o inicie sesión.';
} elseif ($error === 'password_match') {
    $errorMessage = 'Las contraseñas no coinciden. Por favor, inténtelo de nuevo.';
} elseif ($error === 'password_too_short') {
    $errorMessage = 'La contraseña debe tener al menos 8 caracteres.';
} elseif ($error === 'invalid_email') {
    $errorMessage = 'El formato del correo electrónico no es válido.';
} elseif ($error === 'system') {
    $errorMessage = 'Error del sistema. Por favor, inténtelo de nuevo más tarde.';
}

// Cargar valores previos en caso de error (excepto contraseña)
$prevName = $_GET['name'] ?? '';
$prevEmail = $_GET['email'] ?? '';

// Definir página activa y título
$pageName = 'register';
$pageTitle = 'Crear Cuenta';

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white py-3">
                    <h1 class="h4 mb-0 text-center">Crear una Cuenta</h1>
                </div>
                
                <div class="card-body p-4">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="/register/process.php" method="POST" class="needs-validation" novalidate>
                        <?= CSRF::tokenField() ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" 
                                value="<?= htmlspecialchars($prevName) ?>" 
                                required>
                            <div class="invalid-feedback">
                                Por favor, ingrese su nombre completo.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?= htmlspecialchars($prevEmail) ?>" 
                                required>
                            <div class="invalid-feedback">
                                Por favor, ingrese un correo electrónico válido.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" 
                                required minlength="8">
                            <div class="form-text">
                                La contraseña debe tener al menos 8 caracteres.
                            </div>
                            <div class="invalid-feedback">
                                Por favor, ingrese una contraseña de al menos 8 caracteres.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                required data-match-password="password">
                            <div class="invalid-feedback">
                                Las contraseñas no coinciden.
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                Acepto los <a href="/terms" target="_blank">Términos y Condiciones</a> y la 
                                <a href="/privacy" target="_blank">Política de Privacidad</a>
                            </label>
                            <div class="invalid-feedback">
                                Debe aceptar los términos y condiciones para continuar.
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Crear Cuenta</button>
                        </div>
                    </form>
                </div>
                
                <div class="card-footer bg-white py-3 text-center">
                    <p class="mb-0">¿Ya tienes una cuenta? <a href="/login" class="text-decoration-none">Iniciar Sesión</a></p>
                </div>
            </div>
            
            <!-- Ventajas de crear una cuenta -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">Ventajas de registrarte</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Crea y gestiona tus listas de regalos</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Evita regalos duplicados</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Comparte fácilmente con amigos y familia</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Recibe notificaciones de reservas</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Servicio completamente gratuito</li>
                                <li><i class="bi bi-check-circle-fill text-success me-2"></i> Acceso desde cualquier dispositivo</li>
                            </ul>
                        </div>
                    </div>
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