<?php
/**
 * Página de inicio del Sistema de Listas de Regalos
 */
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../core/Database/Connection.php';
require_once __DIR__ . '/../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../includes/Cache.php';

use Core\Security\Headers;
use Core\Auth\SessionManager;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Permitir caché para esta página
Headers::setCacheHeaders(1800); // 30 minutos

// Iniciar sesión segura
SessionManager::startSecureSession();

// Obtener configuración
$config = require_once __DIR__ . '/../../config/app.php';
$appName = $config['app']['name'] ?? 'Sistema de Listas de Regalos';
$isDebug = $config['app']['debug'] ?? false;

// Determinar si el usuario está autenticado
$isLoggedIn = SessionManager::isLoggedIn();

// Definir página activa para el menú
$pageName = 'home';
$pageTitle = 'Inicio';

// Cargar estadísticas de listas públicas si hay caché
$listStats = null;
$popularLists = [];
$recentLists = [];

// Intentar cargar estadísticas desde caché
if (Cache::has('home_stats')) {
    $listStats = Cache::get('home_stats');
} else {
    try {
        // Conexión a base de datos
        $db = Connection::getInstance();
        $query = new QueryBuilder($db);
        
        // Obtener estadísticas
        $statsQuery = "
            SELECT 
                COUNT(DISTINCT l.id) AS total_lists,
                COUNT(DISTINCT l.user_id) AS total_users,
                COUNT(DISTINCT g.id) AS total_gifts,
                COUNT(DISTINCT gr.id) AS total_reservations
            FROM 
                gift_lists l
                LEFT JOIN gifts g ON l.id = g.list_id
                LEFT JOIN gift_reservations gr ON g.id = gr.gift_id
            WHERE 
                l.privacy = 'public'
                AND l.status = 'active'
        ";
        
        $result = $query->raw($statsQuery);
        $listStats = !empty($result) ? $result[0] : [
            'total_lists' => 0,
            'total_users' => 0,
            'total_gifts' => 0,
            'total_reservations' => 0
        ];
        
        // Guardar en caché por 1 hora
        Cache::set('home_stats', $listStats, 3600);
    } catch (\Exception $e) {
        // Manejar error silenciosamente en producción, mostrar en debug
        if ($isDebug) {
            $error = 'Error al cargar estadísticas: ' . $e->getMessage();
        }
    }
}

// Cargar listas populares
if (Cache::has('home_popular_lists')) {
    $popularLists = Cache::get('home_popular_lists');
} else {
    try {
        // Conexión a base de datos
        if (!isset($db)) {
            $db = Connection::getInstance();
            $query = new QueryBuilder($db);
        }
        
        // Consulta de listas populares
        $popularQuery = "
            SELECT 
                l.id, l.title, l.occasion, l.slug, l.image_path, l.created_at,
                u.name AS user_name,
                COUNT(DISTINCT g.id) AS gift_count,
                COUNT(DISTINCT gr.id) AS reservation_count
            FROM 
                gift_lists l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN gifts g ON l.id = g.list_id
                LEFT JOIN gift_reservations gr ON g.id = gr.gift_id AND gr.status = 'active'
            WHERE 
                l.privacy = 'public'
                AND l.status = 'active'
            GROUP BY 
                l.id
            ORDER BY 
                reservation_count DESC, gift_count DESC
            LIMIT 4
        ";
        
        $popularLists = $query->raw($popularQuery);
        
        // Guardar en caché por 1 hora
        Cache::set('home_popular_lists', $popularLists, 3600);
    } catch (\Exception $e) {
        if ($isDebug) {
            $error = 'Error al cargar listas populares: ' . $e->getMessage();
        }
    }
}

// Cargar listas recientes
if (Cache::has('home_recent_lists')) {
    $recentLists = Cache::get('home_recent_lists');
} else {
    try {
        // Conexión a base de datos
        if (!isset($db)) {
            $db = Connection::getInstance();
            $query = new QueryBuilder($db);
        }
        
        // Consulta de listas recientes
        $recentQuery = "
            SELECT 
                l.id, l.title, l.occasion, l.slug, l.image_path, l.created_at,
                u.name AS user_name,
                COUNT(DISTINCT g.id) AS gift_count
            FROM 
                gift_lists l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN gifts g ON l.id = g.list_id
            WHERE 
                l.privacy = 'public'
                AND l.status = 'active'
            GROUP BY 
                l.id
            ORDER BY 
                l.created_at DESC
            LIMIT 4
        ";
        
        $recentLists = $query->raw($recentQuery);
        
        // Guardar en caché por 1 hora
        Cache::set('home_recent_lists', $recentLists, 3600);
    } catch (\Exception $e) {
        if ($isDebug) {
            $error = 'Error al cargar listas recientes: ' . $e->getMessage();
        }
    }
}

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Banner principal -->
<section class="hero-banner bg-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-3">Crea y comparte tus listas de regalos</h1>
                <p class="lead mb-4">La forma más fácil de organizar regalos para bodas, baby showers, cumpleaños y más. Evita duplicados y recibe exactamente lo que deseas.</p>
                <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                    <?php if ($isLoggedIn): ?>
                        <a href="/list/create" class="btn btn-light btn-lg px-4 me-md-2">Crear Lista</a>
                        <a href="/dashboard" class="btn btn-outline-light btn-lg px-4">Mi Panel</a>
                    <?php else: ?>
                        <a href="/register" class="btn btn-light btn-lg px-4 me-md-2">Registrarse</a>
                        <a href="/login" class="btn btn-outline-light btn-lg px-4">Iniciar Sesión</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 d-none d-lg-block">
                <img src="/assets/img/hero-image.svg" alt="Listas de regalos" class="img-fluid">
            </div>
        </div>
    </div>
</section>

<!-- Estadísticas -->
<?php if ($listStats): ?>
<section class="py-4 bg-light">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card border-0 bg-transparent">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-primary"><?= number_format($listStats['total_lists']) ?></div>
                        <p class="text-muted mb-0">Listas Creadas</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card border-0 bg-transparent">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-primary"><?= number_format($listStats['total_users']) ?></div>
                        <p class="text-muted mb-0">Usuarios Activos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card border-0 bg-transparent">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-primary"><?= number_format($listStats['total_gifts']) ?></div>
                        <p class="text-muted mb-0">Regalos Añadidos</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 bg-transparent">
                    <div class="card-body">
                        <div class="display-4 fw-bold text-primary"><?= number_format($listStats['total_reservations']) ?></div>
                        <p class="text-muted mb-0">Regalos Reservados</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Características -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">¿Por qué usar nuestro sistema?</h2>
            <p class="lead text-muted">Características diseñadas para hacer tu experiencia más fácil</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-gift fs-3"></i>
                        </div>
                        <h3 class="card-title h5 mb-3">Crea listas para cualquier ocasión</h3>
                        <p class="card-text text-muted">Bodas, baby showers, cumpleaños, aniversarios y más. Organiza tus regalos por evento.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-share fs-3"></i>
                        </div>
                        <h3 class="card-title h5 mb-3">Comparte fácilmente</h3>
                        <p class="card-text text-muted">Envía tu lista a través de WhatsApp, correo electrónico, redes sociales o genera un código QR.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-check-circle fs-3"></i>
                        </div>
                        <h3 class="card-title h5 mb-3">Evita regalos duplicados</h3>
                        <p class="card-text text-muted">Los invitados pueden reservar regalos para que nadie más los elija, evitando duplicados.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-shield-lock fs-3"></i>
                        </div>
                        <h3 class="card-title h5 mb-3">Privacidad garantizada</h3>
                        <p class="card-text text-muted">Elige entre listas públicas o privadas. Incluso puedes proteger tu lista con contraseña.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-cart fs-3"></i>
                        </div>
                        <h3 class="card-title h5 mb-3">Añade regalos de cualquier tienda</h3>
                        <p class="card-text text-muted">Agrega productos de cualquier tienda online o física. Incluye detalles y enlaces directos.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                            <i class="bi bi-graph-up fs-3"></i>
                        </div>
                        <h3 class="card-title h5 mb-3">Seguimiento en tiempo real</h3>
                        <p class="card-text text-muted">Visualiza estadísticas y recibe notificaciones cuando reserven tus regalos.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Ocasiones -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Ideal para cualquier ocasión</h2>
            <p class="lead text-muted">Crea listas personalizadas para todos tus eventos especiales</p>
        </div>
        
        <div class="row g-4">
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-heart text-danger fs-1 mb-3"></i>
                        <h3 class="h5">Bodas</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-balloon text-primary fs-1 mb-3"></i>
                        <h3 class="h5">Cumpleaños</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-snow2 text-info fs-1 mb-3"></i>
                        <h3 class="h5">Baby Shower</h3>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <i class="bi bi-mortarboard text-success fs-1 mb-3"></i>
                        <h3 class="h5">Graduaciones</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Listas populares -->
<?php if (!empty($popularLists)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Listas populares</h2>
            <a href="/explore" class="btn btn-outline-primary">Ver todas</a>
        </div>
        
        <div class="row g-4">
            <?php foreach ($popularLists as $list): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm">
                    <?php if (!empty($list['image_path'])): ?>
                    <img src="<?= htmlspecialchars($list['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($list['title']) ?>" style="height: 160px; object-fit: cover;">
                    <?php else: ?>
                    <div class="bg-light text-center py-4" style="height: 160px;">
                        <i class="bi bi-gift text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <?php if (!empty($list['occasion'])): ?>
                        <span class="badge bg-primary mb-2"><?= htmlspecialchars(ucfirst($list['occasion'])) ?></span>
                        <?php endif; ?>
                        
                        <h3 class="card-title h5"><?= htmlspecialchars($list['title']) ?></h3>
                        <p class="card-text text-muted small">
                            Creada por: <?= htmlspecialchars($list['user_name']) ?><br>
                            Regalos: <?= $list['gift_count'] ?? 0 ?> | 
                            Reservados: <?= $list['reservation_count'] ?? 0 ?>
                        </p>
                    </div>
                    
                    <div class="card-footer bg-white border-0">
                        <a href="/list/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-sm btn-outline-primary w-100">Ver lista</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Listas recientes -->
<?php if (!empty($recentLists)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Listas recientes</h2>
            <a href="/explore?sort=newest" class="btn btn-outline-primary">Ver más</a>
        </div>
        
        <div class="row g-4">
            <?php foreach ($recentLists as $list): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100 border-0 shadow-sm">
                    <?php if (!empty($list['image_path'])): ?>
                    <img src="<?= htmlspecialchars($list['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($list['title']) ?>" style="height: 160px; object-fit: cover;">
                    <?php else: ?>
                    <div class="bg-light text-center py-4" style="height: 160px;">
                        <i class="bi bi-gift text-primary" style="font-size: 4rem;"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <?php if (!empty($list['occasion'])): ?>
                        <span class="badge bg-primary mb-2"><?= htmlspecialchars(ucfirst($list['occasion'])) ?></span>
                        <?php endif; ?>
                        
                        <h3 class="card-title h5"><?= htmlspecialchars($list['title']) ?></h3>
                        <p class="card-text text-muted small">
                            Creada por: <?= htmlspecialchars($list['user_name']) ?><br>
                            Fecha: <?= (new DateTime($list['created_at']))->format('d/m/Y') ?>
                        </p>
                    </div>
                    
                    <div class="card-footer bg-white border-0">
                        <a href="/list/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-sm btn-outline-primary w-100">Ver lista</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call-to-Action -->
<section class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mx-auto text-center">
                <h2 class="fw-bold mb-3">¿Listo para crear tu lista de regalos?</h2>
                <p class="lead mb-4">Crear una lista es gratis, rápido y muy sencillo. Comienza ahora y organiza tus regalos de forma eficiente.</p>
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <?php if ($isLoggedIn): ?>
                        <a href="/list/create" class="btn btn-light btn-lg px-4 me-md-2">Crear Lista</a>
                        <a href="/explore" class="btn btn-outline-light btn-lg px-4">Explorar Listas</a>
                    <?php else: ?>
                        <a href="/register" class="btn btn-light btn-lg px-4 me-md-2">Registrarse</a>
                        <a href="/login" class="btn btn-outline-light btn-lg px-4">Iniciar Sesión</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonios -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Lo que dicen nuestros usuarios</h2>
            <p class="lead text-muted">Historias reales de personas que han usado nuestro sistema</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3 text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <p class="card-text">«Usé este sistema para mi boda y fue perfecto. Mis invitados pudieron elegir regalos fácilmente y no recibimos duplicados. ¡Lo recomiendo totalmente!»</p>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">ML</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0 fs-6">María López</h5>
                                <p class="mb-0 small text-muted">Boda, Junio 2023</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3 text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-half"></i>
                        </div>
                        <p class="card-text">«Organicé el baby shower de mi hermana con esta plataforma. La interfaz es intuitiva y todos pudieron ver y reservar regalos sin problemas. Incluso los familiares mayores lo encontraron fácil de usar.»</p>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">JR</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0 fs-6">Juan Rodríguez</h5>
                                <p class="mb-0 small text-muted">Baby Shower, Marzo 2023</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="mb-3 text-warning">
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                            <i class="bi bi-star-fill"></i>
                        </div>
                        <p class="card-text">«Para mi cumpleaños número 30 quería algo especial y esta plataforma fue perfecta. Pude añadir regalos de diferentes tiendas y compartir con mis amigos. La experiencia fue excelente.»</p>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">AG</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0 fs-6">Ana García</h5>
                                <p class="mb-0 small text-muted">Cumpleaños, Agosto 2023</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>