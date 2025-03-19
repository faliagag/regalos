<?php
/**
 * Formulario para editar una lista de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Headers.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';

use Core\Security\CSRF;
use Core\Security\Headers;
use Core\Auth\SessionManager;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Verificar que el usuario esté autenticado
SessionManager::checkAccess('/login?redirect=/list/edit');

// Obtener ID de usuario y de lista
$userId = SessionManager::get('user_id');
$listId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Verificar si se proporcionó un ID de lista
if (!$listId) {
    header('Location: /dashboard?error=invalid_list');
    exit;
}

// Obtener mensajes de error/éxito si existen
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$errorMessage = '';
$successMessage = '';

if ($error === 'csrf') {
    $errorMessage = 'Error de seguridad. Por favor, intente nuevamente.';
} elseif ($error === 'upload') {
    $errorMessage = 'Error al subir la imagen. Verifique el tipo y tamaño del archivo.';
} elseif ($error === 'save') {
    $errorMessage = 'Error al guardar la lista. Por favor, intente nuevamente.';
} elseif ($error === 'not_found') {
    $errorMessage = 'Lista no encontrada o sin acceso.';
}

if ($success === 'updated') {
    $successMessage = 'Lista actualizada exitosamente.';
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Obtener la lista a editar
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
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al obtener lista: ' . $e->getMessage());
    
    // Redirigir con error
    header('Location: /dashboard?error=system');
    exit;
}

// Nombre de la página para navegación activa
$pageName = 'edit_list';
$pageTitle = 'Editar Lista';

// Scripts adicionales para el formulario
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Previsualización de imagen
    const coverImage = document.getElementById("cover_image");
    const imagePreview = document.getElementById("imagePreview");
    
    if (coverImage && imagePreview) {
        coverImage.addEventListener("change", function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    imagePreview.querySelector("img").src = e.target.result;
                    imagePreview.classList.remove("d-none");
                };
                
                reader.readAsDataURL(this.files[0]);
            } else {
                imagePreview.classList.add("d-none");
            }
        });
    }
    
    // Mostrar/ocultar campo de contraseña
    const privacySelect = document.getElementById("privacy");
    const passwordField = document.getElementById("passwordField");
    
    if (privacySelect && passwordField) {
        privacySelect.addEventListener("change", function() {
            if (this.value === "password") {
                passwordField.classList.remove("d-none");
                document.getElementById("list_password").setAttribute("required", "");
            } else {
                passwordField.classList.add("d-none");
                document.getElementById("list_password").removeAttribute("required");
            }
        });
        
        // Trigger inicial
        if (privacySelect.value === "password") {
            passwordField.classList.remove("d-none");
        }
    }
    
    // Validación de formulario
    const form = document.getElementById("editListForm");
    
    if (form) {
        form.addEventListener("submit", function(e) {
            let isValid = true;
            
            // Validar título
            const title = document.getElementById("title").value.trim();
            if (!title) {
                isValid = false;
                document.getElementById("title").classList.add("is-invalid");
            } else {
                document.getElementById("title").classList.remove("is-invalid");
            }
            
            // Validar contraseña si es necesaria
            if (privacySelect.value === "password") {
                const passwordInput = document.getElementById("list_password");
                const passwordValue = passwordInput.value.trim();
                const passwordChanged = document.getElementById("password_changed");
                
                // Si se marcó el cambio de contraseña pero no se proporcionó
                if (passwordChanged.checked && !passwordValue) {
                    isValid = false;
                    passwordInput.classList.add("is-invalid");
                } else {
                    passwordInput.classList.remove("is-invalid");
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                document.getElementById("validation-message").classList.remove("d-none");
                window.scrollTo(0, 0);
            }
        });
    }
});
</script>
';

// Incluir header del sitio
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Editar Lista de Regalos</h1>
                </div>
                
                <div class="card-body">
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
                    
                    <div id="validation-message" class="alert alert-danger d-none">
                        Por favor, corrija los errores en el formulario antes de continuar.
                    </div>
                    
                    <form action="/list/edit/save.php" method="POST" enctype="multipart/form-data" id="editListForm">
                        <?= CSRF::tokenField() ?>
                        <input type="hidden" name="list_id" value="<?= $list['id'] ?>">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Título de la Lista <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                required minlength="3" maxlength="100"
                                value="<?= htmlspecialchars($list['title']) ?>"
                                placeholder="Ej: Lista de Boda, Cumpleaños, Baby Shower...">
                            <div class="form-text">El título que verán las personas al recibir tu lista.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="occasion" class="form-label">Ocasión</label>
                            <select class="form-select" id="occasion" name="occasion">
                                <option value="">Seleccionar...</option>
                                <option value="birthday" <?= $list['occasion'] === 'birthday' ? 'selected' : '' ?>>Cumpleaños</option>
                                <option value="wedding" <?= $list['occasion'] === 'wedding' ? 'selected' : '' ?>>Boda</option>
                                <option value="baby" <?= $list['occasion'] === 'baby' ? 'selected' : '' ?>>Baby Shower</option>
                                <option value="graduation" <?= $list['occasion'] === 'graduation' ? 'selected' : '' ?>>Graduación</option>
                                <option value="housewarming" <?= $list['occasion'] === 'housewarming' ? 'selected' : '' ?>>Inauguración de Casa</option>
                                <option value="anniversary" <?= $list['occasion'] === 'anniversary' ? 'selected' : '' ?>>Aniversario</option>
                                <option value="other" <?= $list['occasion'] === 'other' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Fecha del Evento</label>
                            <input type="date" class="form-control" id="event_date" name="event_date" 
                                value="<?= htmlspecialchars($list['event_date'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" 
                                rows="3" maxlength="500"
                                placeholder="Describe brevemente el motivo de tu lista de regalos..."><?= htmlspecialchars($list['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cover_image" class="form-label">Imagen de Portada</label>
                            <input type="file" class="form-control" id="cover_image" name="cover_image" 
                                accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB.</div>
                            
                            <div id="imagePreview" class="mt-2 <?= empty($list['image_path']) ? 'd-none' : '' ?>">
                                <img src="<?= htmlspecialchars($list['image_path'] ?? '/assets/img/placeholder.png') ?>" 
                                    alt="Vista previa" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                            
                            <?php if (!empty($list['image_path'])): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                    <label class="form-check-label" for="remove_image">
                                        Eliminar imagen actual
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="privacy" class="form-label">Privacidad <span class="text-danger">*</span></label>
                            <select class="form-select" id="privacy" name="privacy" required>
                                <option value="public" <?= $list['privacy'] === 'public' ? 'selected' : '' ?>>Pública - Cualquiera con el enlace puede ver</option>
                                <option value="private" <?= $list['privacy'] === 'private' ? 'selected' : '' ?>>Privada - Solo personas invitadas pueden ver</option>
                                <option value="password" <?= $list['privacy'] === 'password' ? 'selected' : '' ?>>Protegida con Contraseña</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 <?= $list['privacy'] !== 'password' ? 'd-none' : '' ?>" id="passwordField">
                            <label for="list_password" class="form-label">Contraseña de Acceso</label>
                            <input type="password" class="form-control" id="list_password" name="list_password" 
                                <?= $list['privacy'] === 'password' ? 'required' : '' ?>>
                            <div class="form-text">Contraseña que compartirás con tus invitados.</div>
                            
                            <?php if ($list['privacy'] === 'password'): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="password_changed" name="password_changed" value="1">
                                    <label class="form-check-label" for="password_changed">
                                        Cambiar la contraseña actual
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" value="1" 
                                    <?= $list['allow_comments'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="allow_comments">
                                    Permitir comentarios en mi lista
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <a href="/list/view/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Información adicional sobre listas -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Información Adicional</h5>
                </div>
                <div class="card-body">
                    <h6>Opciones de Privacidad</h6>
                    <ul class="mb-3">
                        <li><strong>Pública:</strong> Cualquier persona con el enlace puede ver tu lista.</li>
                        <li><strong>Privada:</strong> Solo personas que hayas invitado específicamente pueden verla.</li>
                        <li><strong>Protegida con Contraseña:</strong> Se requerirá una contraseña para acceder.</li>
                    </ul>
                    
                    <h6>URL de tu Lista</h6>
                    <p>Tu lista está disponible en:</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" value="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/list/' . $list['slug'] ?>" readonly>
                        <button class="btn btn-outline-secondary copy-link" type="button">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Script para copiar enlace
document.addEventListener('DOMContentLoaded', function() {
    const copyButton = document.querySelector('.copy-link');
    
    if (copyButton) {
        copyButton.addEventListener('click', function() {
            const input = this.previousElementSibling;
            input.select();
            document.execCommand('copy');
            
            // Cambiar texto del botón temporalmente
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check"></i> Copiado';
            
            setTimeout(() => {
                this.innerHTML = originalHTML;
            }, 2000);
        });
    }
});
</script>

<?php
// Incluir footer del sitio
require_once __DIR__ . '/../../../includes/footer.php';
?>