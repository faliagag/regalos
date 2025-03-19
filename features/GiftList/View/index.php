<?php
/**
 * Vista principal de una lista de regalos
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';
require_once __DIR__ . '/../../../core/Security/Headers.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../includes/Cache.php';
require_once __DIR__ . '/../Share/social_buttons.php';

use Core\Security\CSRF;
use Core\Security\Headers;
use Core\Auth\SessionManager;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Iniciar sesión segura
SessionManager::startSecureSession();

// Verificar si el usuario está autenticado
$isLoggedIn = SessionManager::isLoggedIn();
$userId = $isLoggedIn ? SessionManager::get('user_id') : null;

// Obtener slug de la lista de la URL
$slug = $_GET['slug'] ?? '';

// Verificar si se proporcionó un slug
if (empty($slug)) {
    header('Location: /dashboard');
    exit;
}

// Obtener mensajes de éxito/error
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
$successMessage = '';
$errorMessage = '';

if ($success === 'gift_added') {
    $successMessage = 'Regalo añadido exitosamente.';
} elseif ($success === 'gift_updated') {
    $successMessage = 'Regalo actualizado exitosamente.';
} elseif ($success === 'gift_deleted') {
    $successMessage = 'Regalo eliminado exitosamente.';
} elseif ($success === 'list_updated') {
    $successMessage = 'Lista actualizada exitosamente.';
}

if ($error === 'access') {
    $errorMessage = 'No tienes permiso para ver esta lista.';
} elseif ($error === 'password') {
    $errorMessage = 'Contraseña incorrecta.';
} elseif ($error === 'not_found') {
    $errorMessage = 'Lista no encontrada.';
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Buscar la lista por slug
    $list = $query->table('gift_lists')->findOne(['slug' => $slug]);
    
    // Verificar si la lista existe
    if (!$list) {
        // Redirigir a página de error
        header('Location: /dashboard?error=list_not_found');
        exit;
    }
    
    // Verificar acceso a la lista
    $hasAccess = false;
    $isOwner = $userId && $userId == $list['user_id'];
    
    // El propietario siempre tiene acceso
    if ($isOwner) {
        $hasAccess = true;
    } 
    // Si la lista es pública, todos tienen acceso
    elseif ($list['privacy'] === 'public') {
        $hasAccess = true;
    }
    // Para listas privadas, verificar acceso
    else {
        // Si el usuario proporcionó contraseña, verificarla
        if (isset($_POST['list_password']) && $list['privacy'] === 'password') {
            if (password_verify($_POST['list_password'], $list['password_hash'])) {
                // Guardar acceso en sesión
                $_SESSION['list_access'][$list['id']] = true;
                $hasAccess = true;
            } else {
                $errorMessage = 'Contraseña incorrecta.';
            }
        }
        // Verificar si ya tiene acceso guardado en sesión
        elseif (isset($_SESSION['list_access'][$list['id']])) {
            $hasAccess = true;
        }
    }
    
    // Si no tiene acceso, mostrar formulario de contraseña o error
    if (!$hasAccess && $list['privacy'] === 'password') {
        $showPasswordForm = true;
    } elseif (!$hasAccess) {
        header('Location: /dashboard?error=list_access_denied');
        exit;
    }
    
    // Obtener datos del propietario de la lista
    $owner = $query->table('users')->findOne(
        ['id' => $list['user_id']],
        ['id', 'name', 'email']
    );
    
    // Obtener regalos de la lista
    $gifts = $query->raw(
        "SELECT 
            g.id, g.title, g.description, g.price, g.url, g.image_url,
            g.category, g.priority, g.status, g.created_at,
            gr.id as reservation_id, gr.name as reserver_name, 
            gr.is_anonymous, gr.reservation_date
        FROM 
            gifts g
            LEFT JOIN gift_reservations gr ON g.id = gr.gift_id AND gr.status = 'active'
        WHERE 
            g.list_id = :list_id
        ORDER BY 
            CASE g.priority 
                WHEN 'high' THEN 1 
                WHEN 'medium' THEN 2 
                WHEN 'low' THEN 3 
            END,
            g.created_at DESC",
        ['list_id' => $list['id']]
    );
    
    // Preparar categorías para filtrado
    $categories = [];
    foreach ($gifts as $gift) {
        if (!empty($gift['category']) && !in_array($gift['category'], $categories)) {
            $categories[] = $gift['category'];
        }
    }
    
    // Formatear datos de la lista
    $list['event_date_formatted'] = !empty($list['event_date']) 
        ? (new DateTime($list['event_date']))->format('d/m/Y') 
        : null;
    
    $list['created_at_formatted'] = !empty($list['created_at']) 
        ? (new DateTime($list['created_at']))->format('d/m/Y') 
        : null;
    
    $list['updated_at_formatted'] = !empty($list['updated_at']) 
        ? (new DateTime($list['updated_at']))->format('d/m/Y') 
        : null;
    
    // Calcular estadísticas
    $totalGifts = count($gifts);
    $reservedGifts = 0;
    
    foreach ($gifts as $gift) {
        if ($gift['status'] === 'reserved') {
            $reservedGifts++;
        }
    }
    
    $reservationPercentage = $totalGifts > 0 ? round(($reservedGifts / $totalGifts) * 100) : 0;
    
    // Categorías traducidas
    $categoryNames = [
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
    
    // Si el usuario puede ver la lista, registrar evento de visualización
    if ($hasAccess && !$isOwner) {
        try {
            $query->table('gift_events')->insert([
                'gift_id' => 0, // 0 indica evento de lista completa
                'list_id' => $list['id'],
                'user_id' => $userId,
                'event_type' => 'viewed',
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // Ignorar errores de registro, no afectan funcionalidad principal
            error_log('Error al registrar visualización: ' . $e->getMessage());
        }
    }
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al cargar lista: ' . $e->getMessage());
    
    // Redirigir a página de error
    header('Location: /dashboard?error=system');
    exit;
}

// Definir página activa y título
$pageName = '';
$pageTitle = $list['title'] ?? 'Lista de Regalos';

// Scripts y estilos adicionales
$extraScripts = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Funcionalidad de filtrado por categoría
    const filterButtons = document.querySelectorAll(".filter-btn");
    const giftItems = document.querySelectorAll(".gift-item");
    
    filterButtons.forEach(button => {
        button.addEventListener("click", function() {
            const filter = this.getAttribute("data-filter");
            
            // Actualizar botones activos
            filterButtons.forEach(btn => btn.classList.remove("active"));
            this.classList.add("active");
            
            // Filtrar elementos
            if (filter === "all") {
                giftItems.forEach(item => item.style.display = "block");
            } else {
                giftItems.forEach(item => {
                    if (item.getAttribute("data-category") === filter) {
                        item.style.display = "block";
                    } else {
                        item.style.display = "none";
                    }
                });
            }
        });
    });
    
    // Modales de reserva y cancelación
    const reserveButtons = document.querySelectorAll(".reserve-btn");
    const unreserveButtons = document.querySelectorAll(".unreserve-btn");
    const reserveForm = document.getElementById("reserveForm");
    const unreserveForm = document.getElementById("unreserveForm");
    
    reserveButtons.forEach(button => {
        button.addEventListener("click", function() {
            const giftId = this.getAttribute("data-gift-id");
            const giftTitle = this.getAttribute("data-gift-title");
            
            document.getElementById("reserve_gift_id").value = giftId;
            document.getElementById("reserve_gift_title").textContent = giftTitle;
        });
    });
    
    unreserveButtons.forEach(button => {
        button.addEventListener("click", function() {
            const giftId = this.getAttribute("data-gift-id");
            const giftTitle = this.getAttribute("data-gift-title");
            
            document.getElementById("unreserve_gift_id").value = giftId;
            document.getElementById("unreserve_gift_title").textContent = giftTitle;
        });
    });
    
    // Validación del formulario de reserva
    if (reserveForm) {
        reserveForm.addEventListener("submit", function(e) {
            const nameInput = document.getElementById("reserver_name");
            const emailInput = document.getElementById("reserver_email");
            const isAnonymous = document.getElementById("anonymous").checked;
            
            // Si no es anónimo, el nombre es obligatorio
            if (!isAnonymous && nameInput.value.trim() === "") {
                e.preventDefault();
                alert("Por favor, ingresa tu nombre para reservar este regalo.");
                nameInput.focus();
            }
        });
        
        // Actualizar campos requeridos según opción anónima
        const anonymousCheckbox = document.getElementById("anonymous");
        if (anonymousCheckbox) {
            anonymousCheckbox.addEventListener("change", function() {
                const nameInput = document.getElementById("reserver_name");
                if (this.checked) {
                    nameInput.removeAttribute("required");
                    nameInput.parentElement.classList.add("text-muted");
                } else {
                    nameInput.setAttribute("required", "");
                    nameInput.parentElement.classList.remove("text-muted");
                }
            });
        }
    }
});
</script>
';

// Incluir header
require_once __DIR__ . '/../../../includes/header.php';
?>

<?php if (isset($showPasswordForm)): ?>
<!-- Formulario de contraseña para listas protegidas -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <i class="bi bi-lock-fill text-primary display-1"></i>
                    </div>
                    <h2 class="mb-3">Lista Protegida</h2>
                    <p class="mb-4">Esta lista de regalos está protegida con contraseña. Ingresa la contraseña para acceder.</p>
                    
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($errorMessage) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <?= CSRF::tokenField() ?>
                        <div class="mb-3">
                            <input type="password" class="form-control form-control-lg" 
                                name="list_password" placeholder="Contraseña" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Acceder</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Vista de la lista -->
<div class="container-fluid py-3">
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
    
    <div class="row">
        <!-- Información de la lista -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm sticky-top" style="top: 20px; z-index: 1000;">
                <div class="card-header bg-primary text-white">
                    <h1 class="h4 mb-0"><?= htmlspecialchars($list['title']) ?></h1>
                </div>
                
                <?php if (!empty($list['image_path'])): ?>
                    <img src="<?= htmlspecialchars($list['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($list['title']) ?>">
                <?php endif; ?>
                
                <div class="card-body">
                    <?php if (!empty($list['occasion'])): ?>
                        <div class="mb-3">
                            <span class="badge bg-primary"><?= htmlspecialchars(ucfirst($list['occasion'])) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($list['description'])): ?>
                        <p><?= nl2br(htmlspecialchars($list['description'])) ?></p>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <small class="text-muted">Creada por: <?= htmlspecialchars($owner['name']) ?></small>
                    </div>
                    
                    <?php if (!empty($list['event_date'])): ?>
                        <div class="mb-3">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-calendar-event text-primary me-2"></i>
                                <div>
                                    <small class="text-muted">Fecha del evento:</small><br>
                                    <strong><?= htmlspecialchars($list['event_date_formatted']) ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Estadísticas -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Regalos reservados:</span>
                            <span><?= $reservedGifts ?> de <?= $totalGifts ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-success" role="progressbar" 
                                style="width: <?= $reservationPercentage ?>%;" 
                                aria-valuenow="<?= $reservationPercentage ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100">
                                <?= $reservationPercentage ?>%
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isOwner): ?>
                        <div class="d-grid gap-2 mb-3">
                            <a href="/gift/add?list_id=<?= $list['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> Añadir Regalo
                            </a>
                            <a href="/list/edit/<?= $list['id'] ?>" class="btn btn-outline-primary">
                                <i class="bi bi-pencil"></i> Editar Lista
                            </a>
                            <a href="/list/share/<?= $list['id'] ?>" class="btn btn-outline-primary">
                                <i class="bi bi-share"></i> Opciones para Compartir
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Compartir lista -->
                        <div class="mb-3">
                            <button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#shareOptions">
                                <i class="bi bi-share"></i> Compartir Lista
                            </button>
                            <div class="collapse mt-2" id="shareOptions">
                                <div class="d-flex gap-2">
                                    <a href="https://wa.me/?text=<?= urlencode('Mira esta lista de regalos: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/list/' . $list['slug']) ?>" 
                                        target="_blank" class="btn btn-success btn-sm">
                                        <i class="bi bi-whatsapp"></i>
                                    </a>
                                    <a href="mailto:?subject=<?= urlencode('Lista de Regalos: ' . $list['title']) ?>&body=<?= urlencode('Hola, quiero compartir contigo esta lista de regalos: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/list/' . $list['slug']) ?>" 
                                        class="btn btn-primary btn-sm">
                                        <i class="bi bi-envelope"></i>
                                    </a>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/list/' . $list['slug']) ?>" 
                                        target="_blank" class="btn btn-info btn-sm">
                                        <i class="bi bi-facebook"></i>
                                    </a>
                                    <button type="button" class="btn btn-dark btn-sm copy-link" 
                                        data-url="<?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/list/' . $list['slug'] ?>">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Filtros por categoría -->
                    <?php if (!empty($categories)): ?>
                        <div class="mb-3">
                            <h6>Filtrar por categoría:</h6>
                            <div class="d-flex flex-wrap gap-1">
                                <button class="btn btn-sm btn-outline-primary filter-btn active" data-filter="all">
                                    Todos
                                </button>
                                <?php foreach ($categories as $category): ?>
                                    <button class="btn btn-sm btn-outline-primary filter-btn" data-filter="<?= htmlspecialchars($category) ?>">
                                        <?= htmlspecialchars($categoryNames[$category] ?? ucfirst($category)) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <div class="card bg-light">
                            <div class="card-body p-3">
                                <h6 class="card-title">Reserva anónima</h6>
                                <p class="card-text small mb-0">
                                    Puedes reservar regalos de forma anónima para que el creador de la lista no sepa quién lo reservó.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card-footer bg-white">
                    <small class="text-muted">
                        Última actualización: <?= htmlspecialchars($list['updated_at_formatted']) ?>
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Lista de regalos -->
        <div class="col-lg-9">
            <?php if (empty($gifts)): ?>
                <div class="alert alert-info">
                    <?php if ($isOwner): ?>
                        <p>No has añadido ningún regalo a esta lista. ¡Empieza a añadir regalos ahora!</p>
                        <a href="/gift/add?list_id=<?= $list['id'] ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Añadir mi primer regalo
                        </a>
                    <?php else: ?>
                        <p>Esta lista no tiene regalos disponibles todavía.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($gifts as $gift): ?>
                        <div class="col gift-item" data-category="<?= htmlspecialchars($gift['category']) ?>">
                            <div class="card h-100 border-0 shadow-sm">
                                <?php if ($gift['status'] === 'reserved'): ?>
                                    <div class="ribbon ribbon-top-right">
                                        <span>Reservado</span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($gift['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($gift['image_url']) ?>" class="card-img-top" alt="<?= htmlspecialchars($gift['title']) ?>" style="height: 180px; object-fit: contain;">
                                <?php else: ?>
                                    <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 180px;">
                                        <i class="bi bi-gift text-primary" style="font-size: 4rem;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <?php if (!empty($gift['category'])): ?>
                                        <span class="badge bg-secondary mb-2">
                                            <?= htmlspecialchars($categoryNames[$gift['category']] ?? ucfirst($gift['category'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ($gift['priority'] === 'high'): ?>
                                        <span class="badge bg-danger mb-2">Alta Prioridad</span>
                                    <?php endif; ?>
                                    
                                    <h5 class="card-title"><?= htmlspecialchars($gift['title']) ?></h5>
                                    
                                    <?php if (!empty($gift['description'])): ?>
                                        <p class="card-text small">
                                            <?= nl2br(htmlspecialchars(substr($gift['description'], 0, 100) . (strlen($gift['description']) > 100 ? '...' : ''))) ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($gift['price'])): ?>
                                        <p class="card-text">
                                            <strong>Precio aproximado:</strong> 
                                            <span class="text-primary">$<?= number_format($gift['price'], 2) ?></span>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($gift['status'] === 'reserved'): ?>
                                        <div class="alert alert-success p-2 mb-3 small">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-check-circle-fill me-2"></i>
                                                <div>
                                                    <span>Reservado por:</span><br>
                                                    <strong>
                                                        <?= $gift['is_anonymous'] ? 'Reserva anónima' : htmlspecialchars($gift['reserver_name']) ?>
                                                    </strong>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer bg-white border-0">
                                    <div class="d-flex justify-content-between">
                                        <?php if (!empty($gift['url'])): ?>
                                            <a href="<?= htmlspecialchars($gift['url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-box-arrow-up-right"></i> Ver en tienda
                                            </a>
                                        <?php else: ?>
                                            <div></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($gift['status'] === 'available' && !$isOwner): ?>
                                            <button type="button" class="btn btn-sm btn-success reserve-btn" 
                                                data-bs-toggle="modal" data-bs-target="#reserveModal"
                                                data-gift-id="<?= $gift['id'] ?>"
                                                data-gift-title="<?= htmlspecialchars($gift['title']) ?>">
                                                <i class="bi bi-bookmark-plus"></i> Reservar
                                            </button>
                                        <?php elseif ($gift['status'] === 'reserved' && ($isOwner || ($userId && $gift['reservation_id'] && !$gift['is_anonymous']))): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger unreserve-btn"
                                                data-bs-toggle="modal" data-bs-target="#unreserveModal"
                                                data-gift-id="<?= $gift['id'] ?>"
                                                data-gift-title="<?= htmlspecialchars($gift['title']) ?>">
                                                <i class="bi bi-bookmark-dash"></i> Liberar
                                            </button>
                                        <?php else: ?>
                                            <div></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($isOwner): ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="/gift/edit/<?= $gift['id'] ?>">Editar</a></li>
                                                    <li><a class="dropdown-item text-danger" href="/gift/delete/<?= $gift['id'] ?>">Eliminar</a></li>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de Reserva -->
<?php if (!$isOwner): ?>
<div class="modal fade" id="reserveModal" tabindex="-1" aria-labelledby="reserveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="reserveModalLabel">Reservar Regalo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="/api/v1/gifts/reserve.php" method="POST" id="reserveForm">
                    <?= CSRF::tokenField() ?>
                    <input type="hidden" name="gift_id" id="reserve_gift_id">
                    
                    <p>Estás por reservar: <strong id="reserve_gift_title"></strong></p>
                    <p class="text-muted small">Al reservar este regalo, estás indicando que planeas adquirirlo. Esto evita duplicados.</p>
                    
                    <div class="mb-3">
                        <label for="reserver_name" class="form-label">Tu Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reserver_name" name="reserver_name" 
                            <?= $isLoggedIn ? 'value="' . htmlspecialchars(SessionManager::get('user_name')) . '"' : '' ?> 
                            required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reserver_email" class="form-label">Tu Correo Electrónico</label>
                        <input type="email" class="form-control" id="reserver_email" name="reserver_email"
                            <?= $isLoggedIn ? 'value="' . htmlspecialchars(SessionManager::get('user_email')) . '"' : '' ?>>
                        <div class="form-text">Opcional, solo se usa para contacto si es necesario.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="message" class="form-label">Mensaje (Opcional)</label>
                        <textarea class="form-control" id="message" name="message" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="anonymous" name="anonymous" value="1">
                        <label class="form-check-label" for="anonymous">Reservar anónimamente</label>
                        <div class="form-text">El creador de la lista no sabrá quién reservó este regalo.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success">Confirmar Reserva</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Cancelación de Reserva -->
<div class="modal fade" id="unreserveModal" tabindex="-1" aria-labelledby="unreserveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="unreserveModalLabel">Liberar Regalo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="/api/v1/gifts/unreserve.php" method="POST" id="unreserveForm">
                    <?= CSRF::tokenField() ?>
                    <input type="hidden" name="gift_id" id="unreserve_gift_id">
                    
                    <p>Estás por liberar: <strong id="unreserve_gift_title"></strong></p>
                    <p class="text-muted">Al liberar este regalo, otras personas podrán reservarlo.</p>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Motivo (Opcional)</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3"
                            placeholder="¿Por qué liberas este regalo?"></textarea>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger">Liberar Regalo</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* Estilos para la cinta de "Reservado" */
.ribbon {
    position: absolute;
    z-index: 1;
    overflow: hidden;
    width: 150px;
    height: 150px;
    pointer-events: none;
}

.ribbon-top-right {
    top: -10px;
    right: -10px;
}

.ribbon-top-right::before,
.ribbon-top-right::after {
    border-top-color: transparent;
    border-right-color: transparent;
}

.ribbon-top-right::before {
    top: 0;
    left: 0;
}

.ribbon-top-right::after {
    bottom: 0;
    right: 0;
}

.ribbon-top-right span {
    position: absolute;
    top: 30px;
    right: -25px;
    transform: rotate(45deg);
    width: 200px;
    background-color: #28a745;
    color: white;
    text-align: center;
    font-size: 0.8rem;
    font-weight: bold;
    padding: 5px 0;
    box-shadow: 0 5px 10px rgba(0,0,0,0.1);
}
</style>

<script>
// Script para copiar enlace
document.addEventListener('DOMContentLoaded', function() {
    const copyButtons = document.querySelectorAll('.copy-link');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const url = this.getAttribute('data-url');
            
            // Crear elemento temporal para copiar
            const temp = document.createElement('input');
            temp.value = url;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            
            // Cambiar ícono temporalmente para indicar éxito
            const originalContent = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check2"></i>';
            
            setTimeout(() => {
                this.innerHTML = originalContent;
            }, 2000);
        });
    });
});
</script>
<?php endif; ?>

<?php
// Incluir footer
require_once __DIR__ . '/../../../includes/footer.php';
?>