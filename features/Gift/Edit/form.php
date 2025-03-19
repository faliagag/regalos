<?php
/**
 * Formulario para editar un regalo
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
SessionManager::checkAccess('/login?redirect=/dashboard');

// Obtener ID de usuario y de regalo
$userId = SessionManager::get('user_id');
$giftId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Verificar si se proporcionó un ID de regalo
if (!$giftId) {
    header('Location: /dashboard?error=invalid_gift');
    exit;
}

// Obtener mensajes de error/éxito si existen
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$errorMessage = '';
$successMessage = '';

if ($error === 'csrf') {
    $errorMessage = 'Error de seguridad. Por favor, intente nuevamente.';
} elseif ($error === 'validation') {
    $errorMessage = 'Por favor, complete todos los campos obligatorios.';
} elseif ($error === 'save') {
    $errorMessage = 'Error al guardar el regalo. Por favor, intente nuevamente.';
} elseif ($error === 'not_found') {
    $errorMessage = 'Regalo no encontrado o sin acceso.';
}

if ($success === 'updated') {
    $successMessage = 'Regalo actualizado exitosamente.';
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Obtener la información del regalo
    $gift = $query->raw(
        "SELECT g.* FROM gifts g
        JOIN gift_lists l ON g.list_id = l.id
        WHERE g.id = :gift_id AND l.user_id = :user_id",
        ['gift_id' => $giftId, 'user_id' => $userId]
    );
    
    // Verificar si se encontró el regalo y el usuario tiene acceso
    if (empty($gift)) {
        header('Location: /dashboard?error=gift_not_found');
        exit;
    }
    
    $gift = $gift[0]; // Obtener el primer resultado
    
    // Obtener la lista a la que pertenece el regalo
    $list = $query->table('gift_lists')
        ->findOne(['id' => $gift['list_id']]);
    
    // Definir categorías predefinidas
    $categories = [
        'electronics' => 'Electrónica',
        'home' => 'Hogar y Decoración',
        'kitchen' => 'Cocina',
        'fashion' => 'Moda y Accesorios',
        'beauty' => 'Belleza y Cuidado Personal',
        'baby' => 'Bebé y Niños',
        'toys' => 'Juguetes y Juegos',
        'sports' => 'Deportes y Aire Libre',
        'books' => 'Libros y Entretenimiento',
        'other' => 'Otros'
    ];
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al obtener regalo: ' . $e->getMessage());
    
    // Redirigir con error
    header('Location: /dashboard?error=system');
    exit;
}

// Nombre de la página para navegación activa
$pageName = 'edit_gift';
$pageTitle = 'Editar Regalo';

// Scripts adicionales para el formulario
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Previsualización de imagen de URL
    const imageUrlInput = document.getElementById("image_url");
    const previewUrlBtn = document.getElementById("preview_url_image");
    const imageUrlPreview = document.getElementById("image_url_preview");
    
    if (previewUrlBtn && imageUrlPreview) {
        previewUrlBtn.addEventListener("click", function(e) {
            e.preventDefault();
            const url = imageUrlInput.value.trim();
            
            if (url) {
                const img = imageUrlPreview.querySelector("img");
                img.src = url;
                img.onerror = function() {
                    alert("No se pudo cargar la imagen. Verifica la URL.");
                    img.src = "/assets/img/placeholder.png";
                };
                imageUrlPreview.classList.remove("d-none");
            } else {
                alert("Por favor, introduce una URL de imagen válida.");
            }
        });
    }
    
    // Función para formatear precio
    const priceInput = document.getElementById("price");
    
    if (priceInput) {
        priceInput.addEventListener("input", function() {
            let value = this.value.replace(/[^0-9.]/g, "");
            
            // Permitir solo un punto decimal
            const parts = value.split(".");
            if (parts.length > 2) {
                value = parts[0] + "." + parts.slice(1).join("");
            }
            
            // Limitar a 2 decimales
            if (parts.length === 2 && parts[1].length > 2) {
                value = parts[0] + "." + parts[1].substring(0, 2);
            }
            
            this.value = value;
        });
    }
    
    // Validación de formulario
    const form = document.getElementById("editGiftForm");
    
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
            
            // Validar precio si está presente
            const price = document.getElementById("price").value.trim();
            if (price && isNaN(parseFloat(price))) {
                isValid = false;
                document.getElementById("price").classList.add("is-invalid");
            } else {
                document.getElementById("price").classList.remove("is-invalid");
            }
            
            // Validar URL si está presente
            const url = document.getElementById("url").value.trim();
            if (url) {
                try {
                    new URL(url);
                    document.getElementById("url").classList.remove("is-invalid");
                } catch (e) {
                    isValid = false;
                    document.getElementById("url").classList.add("is-invalid");
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
                    <h1 class="h4 mb-0">Editar Regalo</h1>
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
                    
                    <form action="/gift/edit/save.php" method="POST" id="editGiftForm">
                        <?= CSRF::tokenField() ?>
                        <input type="hidden" name="gift_id" value="<?= $giftId ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Lista de Regalos</label>
                            <p class="form-control-plaintext">
                                <strong><?= htmlspecialchars($list['title']) ?></strong>
                            </p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Título del Regalo <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="title" name="title" 
                                required minlength="3" maxlength="255"
                                value="<?= htmlspecialchars($gift['title']) ?>"
                                placeholder="Ej: Cafetera Automática, Juego de Toallas, etc.">
                            <div class="invalid-feedback">
                                Por favor, ingresa un título para el regalo.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea class="form-control" id="description" name="description" 
                                rows="3" maxlength="1000"
                                placeholder="Describe el regalo con detalles como color, tamaño, modelo, etc."><?= htmlspecialchars($gift['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="price" class="form-label">Precio Aproximado</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control" id="price" name="price" 
                                        placeholder="0.00" pattern="^\d+(\.\d{1,2})?$"
                                        value="<?= htmlspecialchars($gift['price'] ?? '') ?>">
                                </div>
                                <div class="invalid-feedback">
                                    Por favor, ingresa un precio válido.
                                </div>
                                <div class="form-text">Formato: 123.45</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="priority" class="form-label">Prioridad</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low" <?= ($gift['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Baja</option>
                                    <option value="medium" <?= ($gift['priority'] ?? '') === 'medium' ? 'selected' : '' ?>>Media</option>
                                    <option value="high" <?= ($gift['priority'] ?? '') === 'high' ? 'selected' : '' ?>>Alta</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="url" class="form-label">URL (Tienda o Referencia)</label>
                            <input type="url" class="form-control" id="url" name="url" 
                                placeholder="https://ejemplo.com/producto"
                                value="<?= htmlspecialchars($gift['url'] ?? '') ?>">
                            <div class="invalid-feedback">
                                Por favor, ingresa una URL válida.
                            </div>
                            <div class="form-text">Si el regalo se puede comprar online, añade el enlace.</div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="image_url" class="form-label">URL de Imagen</label>
                                <div class="input-group">
                                    <input type="url" class="form-control" id="image_url" name="image_url" 
                                        placeholder="https://ejemplo.com/imagen.jpg"
                                        value="<?= htmlspecialchars($gift['image_url'] ?? '') ?>">
                                    <button class="btn btn-outline-secondary" type="button" id="preview_url_image">
                                        <i class="bi bi-eye"></i> Previsualizar
                                    </button>
                                </div>
                                <div class="form-text">URL de una imagen del regalo.</div>
                            </div>
                            
                            <div class="col-md-4">
                                <div id="image_url_preview" class="mt-2 <?= empty($gift['image_url']) ? 'd-none' : '' ?>">
                                    <img src="<?= htmlspecialchars($gift['image_url'] ?? '/assets/img/placeholder.png') ?>" 
                                        alt="Vista previa" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Categoría</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">Seleccionar categoría...</option>
                                <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= ($gift['category'] ?? '') === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <a href="/list/view/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php if ($gift['status'] === 'reserved'): ?>
            <!-- Información de reserva -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Estado: Reservado</h5>
                </div>
                <div class="card-body">
                    <p>Este regalo ha sido reservado. Si realizas cambios, la persona que lo reservó seguirá viendo la información actualizada.</p>
                    <a href="/list/view/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-eye"></i> Ver Detalles de Reserva
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Incluir footer del sitio
require_once __DIR__ . '/../../../includes/footer.php';
?>