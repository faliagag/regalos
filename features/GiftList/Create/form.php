<?php
/**
 * Formulario para crear nueva lista de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Headers.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';

use Core\Security\CSRF;
use Core\Security\Headers;
use Core\Auth\SessionManager;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Verificar que el usuario esté autenticado
SessionManager::checkAccess('/login?redirect=/list/create');

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
}

if ($success === 'created') {
    $successMessage = 'Lista creada exitosamente.';
}

// Nombre de la página para navegación activa
$pageName = 'create_list';

// Incluir header del sitio
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0">Crear Nueva Lista de Regalos</h1>
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
                    
                    <form action="/list/create/save.php" method="POST" enctype="multipart/form-data" id="createListForm">
                        <?= CSRF::tokenField() ?>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Título de la Lista <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                required minlength="3" maxlength="100"
                                placeholder="Ej: Lista de Boda, Cumpleaños, Baby Shower...">
                            <div class="form-text">El título que verán las personas al recibir tu lista.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="occasion" class="form-label">Ocasión</label>
                            <select class="form-select" id="occasion" name="occasion">
                                <option value="">Seleccionar...</option>
                                <option value="birthday">Cumpleaños</option>
                                <option value="wedding">Boda</option>
                                <option value="baby">Baby Shower</option>
                                <option value="graduation">Graduación</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_date" class="form-label">Fecha del Evento</label>
                            <input type="date" class="form-control" id="event_date" name="event_date">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" 
                                rows="3" maxlength="500"
                                placeholder="Describe brevemente el motivo de tu lista de regalos..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cover_image" class="form-label">Imagen de Portada</label>
                            <input type="file" class="form-control" id="cover_image" name="cover_image" 
                                accept="image/jpeg,image/png,image/gif">
                            <div class="form-text">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 2MB.</div>
                            <div id="imagePreview" class="mt-2 d-none">
                                <img src="#" alt="Vista previa" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="privacy" class="form-label">Privacidad <span class="text-danger">*</span></label>
                            <select class="form-select" id="privacy" name="privacy" required>
                                <option value="public">Pública - Cualquiera con el enlace puede ver</option>
                                <option value="private">Privada - Solo personas invitadas pueden ver</option>
                                <option value="password">Protegida con Contraseña</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 d-none" id="passwordField">
                            <label for="list_password" class="form-label">Contraseña de Acceso</label>
                            <input type="password" class="form-control" id="list_password" name="list_password">
                            <div class="form-text">Contraseña que compartirás con tus invitados.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="allow_comments" name="allow_comments" value="1" checked>
                                <label class="form-check-label" for="allow_comments">
                                    Permitir comentarios en mi lista
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <a href="/dashboard" class="btn btn-outline-secondary">Cancelar</a>
                            <div>
                                <button type="button" id="addItemsButton" class="btn btn-success">Crear y Añadir Regalos</button>
                                <button type="submit" class="btn btn-primary">Crear Lista</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Previsualización de imagen
    const coverImage = document.getElementById('cover_image');
    const imagePreview = document.getElementById('imagePreview');
    
    coverImage.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                imagePreview.querySelector('img').src = e.target.result;
                imagePreview.classList.remove('d-none');
            };
            
            reader.readAsDataURL(this.files[0]);
        } else {
            imagePreview.classList.add('d-none');
        }
    });
    
    // Mostrar/ocultar campo de contraseña
    const privacySelect = document.getElementById('privacy');
    const passwordField = document.getElementById('passwordField');
    
    privacySelect.addEventListener('change', function() {
        if (this.value === 'password') {
            passwordField.classList.remove('d-none');
            document.getElementById('list_password').setAttribute('required', '');
        } else {
            passwordField.classList.add('d-none');
            document.getElementById('list_password').removeAttribute('required');
        }
    });
    
    // Botón para crear y añadir regalos
    const addItemsButton = document.getElementById('addItemsButton');
    const createListForm = document.getElementById('createListForm');
    
    addItemsButton.addEventListener('click', function() {
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'redirect_to_items';
        hiddenInput.value = '1';
        
        createListForm.appendChild(hiddenInput);
        createListForm.submit();
    });
});
</script>

<?php
// Incluir footer del sitio
require_once __DIR__ . '/../../../includes/footer.php';
?>