<?php
/**
 * Página para explorar listas públicas
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
Headers::setCacheHeaders(600); // 10 minutos

// Iniciar sesión segura
SessionManager::startSecureSession();

// Verificar si el usuario está autenticado
$isLoggedIn = SessionManager::isLoggedIn();
$userId = $isLoggedIn ? SessionManager::get('user_id') : null;

// Obtener filtros y parámetros de paginación
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12; // Número de listas por página

$occasion = $_GET['occasion'] ?? '';
$sort = $_GET['sort'] ?? 'newest'; // newest, popular, upcoming
$query = $_GET['q'] ?? '';

// Clave de caché basada en los parámetros
$cacheKey = 'explore_' . $page . '_' . $perPage . '_' . $occasion . '_' . $sort . '_' . md5($query);

// Intentar cargar desde caché
$cacheData = Cache::get($cacheKey);

if ($cacheData !== null) {
    $lists = $cacheData['lists'];
    $totalLists = $cacheData['totalLists'];
    $totalPages = $cacheData['totalPages'];
    $occasions = $cacheData['occasions'];
} else {
    try {
        // Conexión a base de datos
        $db = Connection::getInstance();
        $queryBuilder = new QueryBuilder($db);
        
        // Construir consulta base
        $baseQuery = "
            SELECT 
                l.id, l.title, l.slug, l.occasion, l.description, l.event_date, 
                l.image_path, l.privacy, l.created_at, l.updated_at, l.status,
                u.name AS user_name,
                COUNT(DISTINCT g.id) AS gift_count,
                COUNT(DISTINCT gr.id) AS reservation_count,
                COUNT(DISTINCT ge.id) AS view_count
            FROM 
                gift_lists l
                JOIN users u ON l.user_id = u.id
                LEFT JOIN gifts g ON l.id = g.list_id
                LEFT JOIN gift_reservations gr ON g.id = gr.gift_id AND gr.status = 'active'
                LEFT JOIN gift_events ge ON l.id = ge.list_id AND ge.event_type = 'viewed'
            WHERE 
                l.privacy = 'public'
                AND l.status = 'active'
        ";
        
        $countQuery = "
            SELECT COUNT(DISTINCT l.id) AS total
            FROM 
                gift_lists l
            WHERE 
                l.privacy = 'public'
                AND l.status = 'active'
        ";
        
        $params = [];
        
        // Aplicar filtro por ocasión
        if (!empty($occasion)) {
            $baseQuery .= " AND l.occasion = :occasion";
            $countQuery .= " AND l.occasion = :occasion";
            $params['occasion'] = $occasion;
        }
        
        // Aplicar búsqueda
        if (!empty($query)) {
            $baseQuery .= " AND (l.title LIKE :query OR l.description LIKE :query)";
            $countQuery .= " AND (l.title LIKE :query OR l.description LIKE :query)";
            $params['query'] = '%' . $query . '%';
        }
        
        // Agrupar resultados
        $baseQuery .= " GROUP BY l.id";
        
        // Aplicar ordenamiento
        switch ($sort) {
            case 'popular':
                $baseQuery .= " ORDER BY reservation_count DESC, view_count DESC, l.created_at DESC";
                break;
            case 'upcoming':
                $baseQuery .= " ORDER BY CASE WHEN l.event_date IS NULL THEN 1 ELSE 0 END, l.event_date ASC, l.created_at DESC";
                break;
            case 'newest':
            default:
                $baseQuery .= " ORDER BY l.created_at DESC";
                break;
        }
        
        // Aplicar paginación
        $baseQuery .= " LIMIT :offset, :limit";
        $params['offset'] = ($page - 1) * $perPage;
        $params['limit'] = $perPage;
        
        // Ejecutar consultas
        $lists = $queryBuilder->raw($baseQuery, $params);
        $totalResult = $queryBuilder->raw($countQuery, array_diff_key($params, ['offset' => 1, 'limit' => 1]));
        $totalLists = $totalResult[0]['total'] ?? 0;
        $totalPages = ceil($totalLists / $perPage);
        
        // Obtener lista de ocasiones disponibles
        $occasionsQuery = "
            SELECT DISTINCT occasion, COUNT(*) as count
            FROM gift_lists
            WHERE privacy = 'public' AND status = 'active' AND occasion IS NOT NULL AND occasion != ''
            GROUP BY occasion
            ORDER BY count DESC
        ";
        
        $occasions = $queryBuilder->raw($occasionsQuery);
        
        // Formatear fechas y datos adicionales
        foreach ($lists as &$list) {
            $list['event_date_formatted'] = !empty($list['event_date']) 
                ? (new DateTime($list['event_date']))->format('d/m/Y') 
                : null;
            
            $list['created_at_formatted'] = !empty($list['created_at']) 
                ? (new DateTime($list['created_at']))->format('d/m/Y') 
                : null;
            
            // Calcular porcentaje de reservas
            $list['reservation_percentage'] = $list['gift_count'] > 0
                ? round(($list['reservation_count'] / $list['gift_count']) * 100)
                : 0;
            
            // Truncar descripción si es muy larga
            if (!empty($list['description']) && strlen($list['description']) > 100) {
                $list['description'] = substr($list['description'], 0, 100) . '...';
            }
        }
        
        // Guardar en caché
        Cache::set($cacheKey, [
            'lists' => $lists,
            'totalLists' => $totalLists,
            'totalPages' => $totalPages,
            'occasions' => $occasions
        ], 600); // 10 minutos de caché
        
    } catch (\Exception $e) {
        // Registrar error
        error_log('Error al cargar listas: ' . $e->getMessage());
        
        // Valores por defecto en caso de error
        $lists = [];
        $totalLists = 0;
        $totalPages = 0;
        $occasions = [];
    }
}

// Nombres de ocasiones traducidas
$occasionLabels = [
    'birthday' => 'Cumpleaños',
    'wedding' => 'Boda',
    'baby' => 'Baby Shower',
    'graduation' => 'Graduación',
    'housewarming' => 'Inauguración de Casa',
    'anniversary' => 'Aniversario',
    'christmas' => 'Navidad',
    'other' => 'Otros'
];

// Definir página activa y título
$pageName = 'explore';
$pageTitle = 'Explorar Listas';

// Construir URL base para paginación y filtros
$baseUrl = '/explore?';
$queryParams = $_GET;
unset($queryParams['page']); // Eliminar página para evitar duplicados
$baseUrl .= http_build_query($queryParams);
$baseUrl = !empty($queryParams) ? $baseUrl . '&' : $baseUrl;

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <!-- Encabezado y buscador -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>Explorar Listas de Regalos</h1>
            <p class="text-muted">Descubre listas públicas creadas por nuestra comunidad.</p>
        </div>
        <div class="col-md-4">
            <form action="/explore" method="GET" class="d-flex">
                <!-- Preservar filtros actuales -->
                <?php if (!empty($occasion)): ?>
                    <input type="hidden" name="occasion" value="<?= htmlspecialchars($occasion) ?>">
                <?php endif; ?>
                <?php if (!empty($sort)): ?>
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                <?php endif; ?>
                
                <input type="search" class="form-control me-2" placeholder="Buscar listas..." 
                    name="q" value="<?= htmlspecialchars($query) ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Filtros y opciones de ordenamiento -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <!-- Filtro por ocasión -->
                <div class="col-md-6">
                    <h5 class="mb-3">Filtrar por ocasión</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="/explore?<?= !empty($sort) ? 'sort=' . urlencode($sort) : '' ?><?= !empty($query) ? '&q=' . urlencode($query) : '' ?>" 
                            class="btn btn-sm <?= empty($occasion) ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Todas
                        </a>
                        
                        <?php foreach ($occasions as $occ): ?>
                            <a href="/explore?occasion=<?= urlencode($occ['occasion']) ?><?= !empty($sort) ? '&sort=' . urlencode($sort) : '' ?><?= !empty($query) ? '&q=' . urlencode($query) : '' ?>" 
                                class="btn btn-sm <?= $occasion === $occ['occasion'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <?= htmlspecialchars($occasionLabels[$occ['occasion']] ?? ucfirst($occ['occasion'])) ?>
                                <span class="badge bg-light text-dark ms-1"><?= $occ['count'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Opciones de ordenamiento -->
                <div class="col-md-6">
                    <h5 class="mb-3">Ordenar por</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="/explore?sort=newest<?= !empty($occasion) ? '&occasion=' . urlencode($occasion) : '' ?><?= !empty($query) ? '&q=' . urlencode($query) : '' ?>" 
                            class="btn btn-sm <?= $sort === 'newest' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Más recientes
                        </a>
                        <a href="/explore?sort=popular<?= !empty($occasion) ? '&occasion=' . urlencode($occasion) : '' ?><?= !empty($query) ? '&q=' . urlencode($query) : '' ?>" 
                            class="btn btn-sm <?= $sort === 'popular' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Más populares
                        </a>
                        <a href="/explore?sort=upcoming<?= !empty($occasion) ? '&occasion=' . urlencode($occasion) : '' ?><?= !empty($query) ? '&q=' . urlencode($query) : '' ?>" 
                            class="btn btn-sm <?= $sort === 'upcoming' ? 'btn-primary' : 'btn-outline-primary' ?>">
                            Próximos eventos
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Resultados -->
    <div class="mb-4">
        <?php if (!empty($query)): ?>
            <p>Resultados para: <strong><?= htmlspecialchars($query) ?></strong> (<?= $totalLists ?> encontrados)</p>
        <?php elseif (!empty($occasion)): ?>
            <p>Mostrando listas para: <strong><?= htmlspecialchars($occasionLabels[$occasion] ?? ucfirst($occasion)) ?></strong> (<?= $totalLists ?>)</p>
        <?php else: ?>
            <p>Mostrando <?= $totalLists ?> listas públicas</p>
        <?php endif; ?>
    </div>
    
    <!-- Grid de listas -->
    <?php if (empty($lists)): ?>
        <div class="alert alert-info">
            <p class="mb-0">No se encontraron listas que coincidan con los criterios de búsqueda.</p>
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
            <?php foreach ($lists as $list): ?>
                <div class="col">
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
                                <span class="badge bg-primary mb-2"><?= htmlspecialchars($occasionLabels[$list['occasion']] ?? ucfirst($list['occasion'])) ?></span>
                            <?php endif; ?>
                            
                            <?php if (!empty($list['event_date'])): ?>
                                <span class="badge bg-info mb-2">
                                    <i class="bi bi-calendar-event"></i> <?= htmlspecialchars($list['event_date_formatted']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <h3 class="card-title h5"><?= htmlspecialchars($list['title']) ?></h3>
                            
                            <?php if (!empty($list['description'])): ?>
                                <p class="card-text small text-muted"><?= htmlspecialchars($list['description']) ?></p>
                            <?php endif; ?>
                            
                            <p class="card-text small">
                                Creada por: <?= htmlspecialchars($list['user_name']) ?><br>
                                Regalos: <?= $list['gift_count'] ?> | 
                                Reservados: <?= $list['reservation_count'] ?> (<?= $list['reservation_percentage'] ?>%)
                            </p>
                            
                            <div class="progress mb-2" style="height: 5px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                    style="width: <?= $list['reservation_percentage'] ?>%;" 
                                    aria-valuenow="<?= $list['reservation_percentage'] ?>" 
                                    aria-valuemin="0" 
                                    aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-white border-0">
                            <a href="/list/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-sm btn-primary w-100">Ver Lista</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Paginación de listas">
                <ul class="pagination justify-content-center">
                    <!-- Botón anterior -->
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page > 1 ? $baseUrl . 'page=' . ($page - 1) : '#' ?>" <?= $page <= 1 ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                            <i class="bi bi-chevron-left"></i> Anterior
                        </a>
                    </li>
                    
                    <!-- Páginas numeradas -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    // Mostrar primera página si no está en el rango
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    // Mostrar páginas en el rango
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '">
                                <a class="page-link" href="' . $baseUrl . 'page=' . $i . '">' . $i . '</a>
                              </li>';
                    }
                    
                    // Mostrar última página si no está en el rango
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="' . $baseUrl . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <!-- Botón siguiente -->
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $page < $totalPages ? $baseUrl . 'page=' . ($page + 1) : '#' ?>" <?= $page >= $totalPages ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                            Siguiente <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Llamada a la acción -->
    <div class="card border-0 shadow-sm bg-primary text-white mt-5">
        <div class="card-body p-4 text-center">
            <h2 class="h4 mb-3">¿Quieres crear tu propia lista de regalos?</h2>
            <p class="mb-4">Es fácil, rápido y totalmente gratis. Crea listas para cualquier ocasión especial.</p>
            
            <?php if ($isLoggedIn): ?>
                <a href="/list/create" class="btn btn-light btn-lg">Crear Mi Lista</a>
            <?php else: ?>
                <div class="d-flex justify-content-center gap-3">
                    <a href="/register" class="btn btn-light btn-lg">Registrarme</a>
                    <a href="/login" class="btn btn-outline-light btn-lg">Iniciar Sesión</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>