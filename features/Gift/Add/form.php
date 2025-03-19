<?php
/**
 * Formulario para añadir un regalo a una lista
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
SessionManager::checkAccess('/login?redirect=/gift/add');

// Obtener ID de usuario y de lista (si se proporciona)
$userId = SessionManager::get('user_id');
$listId = isset($_GET['list_id']) && is_numeric($_GET['list_id']) ? (int)$_GET['list_id'] : null;

// Obtener mensajes de error/éxito si existen
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
$errorMessage = '';
$successMessage = '';

if ($error === 'validation') {
    $errorMessage = 'Por favor, complete todos los campos obligatorios.';
} elseif ($error === 'upload') {
    $errorMessage = 'Error al subir la imagen. Verifique el tipo y tamaño del archivo.';
} elseif ($error === 'save') {
    $errorMessage = 'Error al guardar el regalo. Por favor, intente nuevamente.';
} elseif ($error === 'list') {
    $errorMessage = 'La lista seleccionada no existe o no tienes acceso a ella.';
}

if ($success === 'created') {
    $successMessage = 'Regalo añadido exitosamente.';
}

// Verificar si el usuario tiene listas para añadir regalos
$userLists = [];
$selectedList = null;

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Obtener listas del usuario
    $userLists = $query->table('gift_lists')
        ->find(
            ['user_id' => $userId, 'status' => 'active'],
            ['id', 'title']
        );
    
    // Si se proporcionó un ID de lista, verificar acceso
    if ($listId) {
        $selectedList = $query->table('gift_lists')
            ->findOne([
                'id' => $listId,
                'user_id' => $userId
            ]);
        
        if (!$selectedList) {
            $errorMessage = 'La lista seleccionada no existe o no tienes acceso a ella.';
            $listId = null;
        }
    }
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al obtener listas: ' . $e->getMessage());
    $errorMessage = 'Error al cargar tus listas. Por favor, intenta nuevamente.';
}

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

// Nombre de la página para navegación activa
$pageName = 'add_gift';
$pageTitle = 'Añadir Regalo';

// Scripts adicionales para el formulario
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Previsualización de imagen de URL
    const imageUrlInput = document.getElementById("image_url");
    const previewUrlBtn = document.getElementById("preview_url_image");
    const imageUrlPreview = document.getElementById("image_url_preview");
    
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
    
    // Función para formatear precio
    const priceInput = document.getElementById("price");
    
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
    
    // Validación de formulario
    const form = document.getElementById("addGiftForm");
    
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
        
        // Validar lista seleccionada
        const listId = document.getElementById("list_id").value;
        if (!listId) {
            isValid = false;
            document.getElementById("list_id").classList.add("is-invalid");
        } else {
            document.getElementById("list_id").classList.remove("is-invalid");
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
    
    // Botón de "Añadir más" después de guardar
    const addMoreButton = document.getElementById("addMoreButton");
    if (addMoreButton) {
        addMoreButton.addEventListener("click", function() {
            const form = document.getElementById("addGiftForm");
            const hiddenInput = document.createElement("input");
            hiddenInput.type = "hidden";
            hiddenInput.name = "add_more";
            hiddenInput.value = "1";
            
            form.appendChild(hiddenInput);
            form.submit();
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
                    <h1 class="h4 mb-0">Añadir Regalo</h1>
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
                    
                    <?php if (empty($userLists)): ?>
                        <div class="alert alert-warning">
                            <p>No tienes listas de regalos activas. Debes crear una lista antes de añadir regalos.</p>
                            <a href="/list/create" class="btn btn-primary mt-2">Crear Lista</a>
                        </div>
                    <?php else: ?>
                        <form action="/gift/add/save.php" method="POST" enctype="multipart/form-data" id="addGiftForm">
                            <?= CSRF::tokenField() ?>
                            
                            <div class="mb-3">
                                <label for="list_id" class="form-label">Lista de Regalos <span class="text-danger">*</span></label>
                                <select class="form-select" id="list_id" name="list_id" required>
                                    <option value="">Seleccionar lista...</option>
                                    <?php foreach ($userLists as $list): ?>
                                        <option value="<?= $list['id'] ?>" <?= $listId == $list['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($list['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">
                                    Por favor, selecciona una lista.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Título del Regalo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                                    required minlength="3" maxlength="255"
                                    placeholder="Ej: Cafetera Automática, Juego de Toallas, etc.">
                                <div class="invalid-feedback">
                                    Por favor, ingresa un título para el regalo.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea class="form-control" id="description" name="description" 
                                    rows="3" maxlength="1000"
                                    placeholder="Describe el regalo con detalles como color, tamaño, modelo, etc."></textarea>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="price" class="form-label">Precio Aproximado</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" id="price" name="price" 
                                            placeholder="0.00" pattern="^\d+(\.\d{1,2})?$">
                                    </div>
                                    <div class="invalid-feedback">
                                        Por favor, ingresa un precio válido.
                                    </div>
                                    <div class="form-text">Formato: 123.45</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="priority" class="form-label">Prioridad</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low">Baja</option>
                                        <option value="medium" selected>Media</option>
                                        <option value="high">Alta</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="url" class="form-label">URL (Tienda o Referencia)</label>
                                <input type="url" class="form-control" id="url" name="url" 
                                    placeholder="https://ejemplo.com/producto">
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
                                            placeholder="https://ejemplo.com/imagen.jpg">
                                        <button class="btn btn-outline-secondary" type="button" id="preview_url_image">
                                            <i class="bi bi-eye"></i> Previsualizar
                                        </button>
                                    </div>
                                    <div class="form-text">URL de una imagen del regalo.</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div id="image_url_preview" class="mt-2 d-none">
                                        <img src="/assets/img/placeholder.png" alt="Vista previa" class="img-thumbnail" style="max-height: 100px;">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="category" class="form-label">Categoría</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">Seleccionar categoría...</option>
                                    <?php foreach ($categories as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                <a href="<?= $listId ? '/list/view/' . ($selectedList['slug'] ?? $listId) : '/dashboard' ?>" class="btn btn-outline-secondary">Cancelar</a>
                                <div>
                                    <button type="button" id="addMoreButton" class="btn btn-success">Guardar y Añadir Otro</button>
                                    <button type="submit" class="btn btn-primary">Guardar Regalo</button>
                                </div>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Consejos para añadir regalos -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Consejos para añadir regalos</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li>Incluye detalles específicos como color, talla o modelo.</li>
                                <li>Añade enlaces para facilitar la compra a tus invitados.</li>
                                <li>Establece prioridades para los regalos más importantes.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="mb-0">
                                <li>Usa imágenes para que sea más fácil identificar el regalo.</li>
                                <li>Incluye opciones para diferentes presupuestos.</li>
                                <li>Si es posible, añade alternativas o tiendas recomendadas.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer del sitio
require_once __DIR__ . '/../../../includes/footer.php';
?>