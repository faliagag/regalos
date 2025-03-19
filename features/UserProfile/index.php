<?php
/**
 * Página de perfil de usuario
 */
require_once __DIR__ . '/../../core/Security/CSRF.php';
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../core/Database/Connection.php';
require_once __DIR__ . '/../../core/Database/QueryBuilder.php';

use Core\Security\CSRF;
use Core\Security\Headers;
use Core\Auth\SessionManager;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Verificar autenticación
SessionManager::checkAccess('/login?redirect=/profile');

// Obtener ID y datos de usuario de la sesión
$userId = SessionManager::get('user_id');
$userName = SessionManager::get('user_name');
$userEmail = SessionManager::get('user_email');
$userRole = SessionManager::get('user_role');

// Verificar mensajes de éxito/error
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$successMessage = '';
$errorMessage = '';

if ($success === 'profile_updated') {
    $successMessage = 'Perfil actualizado exitosamente.';
} elseif ($success === 'password_changed') {
    $successMessage = 'Contraseña cambiada exitosamente.';
}

if ($error === 'current_password') {
    $errorMessage = 'La contraseña actual es incorrecta.';
} elseif ($error === 'password_match') {
    $errorMessage = 'Las contraseñas nuevas no coinciden.';
} elseif ($error === 'validation') {
    $errorMessage = 'Por favor, complete todos los campos obligatorios.';
} elseif ($error === 'system') {
    $errorMessage = 'Error del sistema. Por favor, intente nuevamente.';
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Obtener datos completos del usuario
    $user = $query->table('users')
        ->findOne(['id' => $userId]);
    
    if (!$user) {
        // Redirigir a login si no se encuentra el usuario
        SessionManager::destroy();
        header('Location: /login?error=session_expired');
        exit;
    }
    
    // Formatear fechas
    $createdAt = !empty($user['created_at']) 
        ? (new DateTime($user['created_at']))->format('d/m/Y') 
        : 'N/A';
    
    $lastLogin = !empty($user['last_login']) 
        ? (new DateTime($user['last_login']))->format('d/m/Y H:i') 
        : 'N/A';
    
    // Obtener estadísticas del usuario
    $stats = $query->raw(
        "SELECT 
            (SELECT COUNT(*) FROM gift_lists WHERE user_id = :user_id) as total_lists,
            (SELECT COUNT(*) FROM gifts WHERE list_id IN (SELECT id FROM gift_lists WHERE user_id = :user_id)) as total_gifts,
            (SELECT COUNT(*) FROM gift_reservations WHERE list_id IN (SELECT id FROM gift_lists WHERE user_id = :user_id)) as total_reservations,
            (SELECT COUNT(*) FROM share_events WHERE user_id = :user_id) as total_shares,
            (SELECT COUNT(*) FROM login_logs WHERE user_id = :user_id) as total_logins
        ",
        ['user_id' => $userId]
    );
    
    // Estadísticas del usuario
    $userStats = [
        'total_lists' => $stats[0]['total_lists'] ?? 0,
        'total_gifts' => $stats[0]['total_gifts'] ?? 0,
        'total_reservations' => $stats[0]['total_reservations'] ?? 0,
        'total_shares' => $stats[0]['total_shares'] ?? 0,
        'total_logins' => $stats[0]['total_logins'] ?? 0
    ];
    
    // Procesar actualización de perfil
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // Validar token CSRF
        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            header('Location: /profile?error=csrf');
            exit;
        }
        
        // Actualizar perfil
        if ($_POST['action'] === 'update_profile') {
            $name = $_POST['name'] ?? '';
            $email = $_POST['email'] ?? '';
            
            // Validaciones básicas
            if (empty($name) || empty($email)) {
                header('Location: /profile?error=validation');
                exit;
            }
            
            // Verificar si el email ya existe (si cambia)
            if ($email !== $user['email']) {
                $existingUser = $query->table('users')
                    ->findOne(['email' => $email]);
                
                if ($existingUser) {
                    header('Location: /profile?error=email_exists');
                    exit;
                }
            }
            
            // Actualizar datos
            $updated = $query->table('users')
                ->update(
                    [
                        'name' => $name,
                        'email' => $email,
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    ['id' => $userId]
                );
            
            if ($updated) {
                // Actualizar datos en sesión
                SessionManager::set('user_name', $name);
                SessionManager::set('user_email', $email);
                
                header('Location: /profile?success=profile_updated');
                exit;
            } else {
                header('Location: /profile?error=system');
                exit;
            }
        }
        
        // Cambiar contraseña
        elseif ($_POST['action'] === 'change_password') {
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Validaciones básicas
            if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                header('Location: /profile?error=validation');
                exit;
            }
            
            // Verificar contraseña actual
            if (!password_verify($currentPassword, $user['password'])) {
                header('Location: /profile?error=current_password');
                exit;
            }
            
            // Verificar que las nuevas contraseñas coincidan
            if ($newPassword !== $confirmPassword) {
                header('Location: /profile?error=password_match');
                exit;
            }
            
            // Actualizar contraseña
            $updated = $query->table('users')
                ->update(
                    [
                        'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                        'updated_at' => date('Y-m-d H:i:s')
                    ],
                    ['id' => $userId]
                );
            
            if ($updated) {
                header('Location: /profile?success=password_changed');
                exit;
            } else {
                header('Location: /profile?error=system');
                exit;
            }
        }
    }
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error en perfil: ' . $e->getMessage());
    
    // Redirigir a dashboard con error
    header('Location: /dashboard?error=system');
    exit;
}

// Definir página activa y título
$pageName = 'profile';
$pageTitle = 'Mi Perfil';

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-3 mb-4">
            <!-- Tarjeta de perfil -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body text-center p-4">
                    <div class="mb-3">
                        <?php if (!empty($user['avatar_path'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_path']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" 
                                class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white mx-auto d-flex align-items-center justify-content-center"
                                style="width: 100px; height: 100px; font-size: 2.5rem;">
                                <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1))) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h5><?= htmlspecialchars($user['name']) ?></h5>
                    <p class="text-muted mb-2"><?= htmlspecialchars($user['email']) ?></p>
                    <?php if ($user['role'] === 'admin'): ?>
                        <span class="badge bg-primary">Administrador</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Usuario</span>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Miembro desde:</span>
                            <span><?= $createdAt ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Último acceso:</span>
                            <span><?= $lastLogin ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas del usuario -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Mis Estadísticas</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Listas creadas</span>
                            <span class="badge bg-primary rounded-pill"><?= $userStats['total_lists'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Regalos añadidos</span>
                            <span class="badge bg-primary rounded-pill"><?= $userStats['total_gifts'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Reservas recibidas</span>
                            <span class="badge bg-primary rounded-pill"><?= $userStats['total_reservations'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Veces compartidas</span>
                            <span class="badge bg-primary rounded-pill"><?= $userStats['total_shares'] ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Inicios de sesión</span>
                            <span class="badge bg-primary rounded-pill"><?= $userStats['total_logins'] ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-md-9">
            <?php if ($successMessage): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($successMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($errorMessage) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Pestañas -->
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" 
                        type="button" role="tab" aria-controls="profile" aria-selected="true">
                        <i class="bi bi-person"></i> Información Personal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" 
                        type="button" role="tab" aria-controls="security" aria-selected="false">
                        <i class="bi bi-shield-lock"></i> Seguridad
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" 
                        type="button" role="tab" aria-controls="preferences" aria-selected="false">
                        <i class="bi bi-gear"></i> Preferencias
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="profileTabsContent">
                <!-- Pestaña de Información Personal -->
                <div class="tab-pane fade show active p-4 bg-white border border-top-0 shadow-sm" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                    <h4 class="mb-4">Información Personal</h4>
                    
                    <form action="/profile" method="POST">
                        <?= CSRF::tokenField() ?>
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                value="<?= htmlspecialchars($user['name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="avatar" class="form-label">Foto de Perfil</label>
                            <input type="file" class="form-control" id="avatar" name="avatar" disabled>
                            <div class="form-text text-muted">Esta funcionalidad estará disponible próximamente.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">Biografía</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3" disabled></textarea>
                            <div class="form-text text-muted">Esta funcionalidad estará disponible próximamente.</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                    </form>
                </div>
                
                <!-- Pestaña de Seguridad -->
                <div class="tab-pane fade p-4 bg-white border border-top-0 shadow-sm" id="security" role="tabpanel" aria-labelledby="security-tab">
                    <h4 class="mb-4">Cambiar Contraseña</h4>
                    
                    <form action="/profile" method="POST">
                        <?= CSRF::tokenField() ?>
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                required minlength="8">
                            <div class="form-text">Mínimo 8 caracteres. Se recomienda incluir letras mayúsculas, minúsculas, números y símbolos.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key"></i> Cambiar Contraseña
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h4 class="mb-4">Sesiones Activas</h4>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Esta funcionalidad estará disponible próximamente. Por ahora, solo tienes acceso desde este dispositivo.
                    </div>
                </div>
                
                <!-- Pestaña de Preferencias -->
                <div class="tab-pane fade p-4 bg-white border border-top-0 shadow-sm" id="preferences" role="tabpanel" aria-labelledby="preferences-tab">
                    <h4 class="mb-4">Preferencias de Notificaciones</h4>
                    
                    <form action="#" method="POST">
                        <?= CSRF::tokenField() ?>
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notif_reservations" name="notif_reservations" value="1" checked disabled>
                            <label class="form-check-label" for="notif_reservations">Recibir notificaciones cuando reserven mis regalos</label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notif_comments" name="notif_comments" value="1" checked disabled>
                            <label class="form-check-label" for="notif_comments">Recibir notificaciones por comentarios en mis listas</label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notif_email" name="notif_email" value="1" checked disabled>
                            <label class="form-check-label" for="notif_email">Recibir notificaciones por correo electrónico</label>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> La configuración de notificaciones estará disponible próximamente.
                        </div>
                        
                        <button type="submit" class="btn btn-primary" disabled>
                            <i class="bi bi-save"></i> Guardar Preferencias
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h4 class="mb-4">Opciones de Privacidad</h4>
                    
                    <form action="#" method="POST">
                        <?= CSRF::tokenField() ?>
                        <input type="hidden" name="action" value="update_privacy">
                        
                        <div class="mb-3">
                            <label class="form-label">Visibilidad de perfil</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="profile_visibility" id="visibility_public" value="public" checked disabled>
                                <label class="form-check-label" for="visibility_public">
                                    Público - Cualquiera puede ver tu perfil
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="profile_visibility" id="visibility_private" value="private" disabled>
                                <label class="form-check-label" for="visibility_private">
                                    Privado - Solo tú puedes ver tu perfil
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> La configuración de privacidad estará disponible próximamente.
                        </div>
                        
                        <button type="submit" class="btn btn-primary" disabled>
                            <i class="bi bi-save"></i> Guardar Configuración
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>