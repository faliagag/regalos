<?php
/**
 * Panel de control del usuario
 */
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';
require_once __DIR__ . '/../../core/Database/Connection.php';
require_once __DIR__ . '/../../core/Database/QueryBuilder.php';

use Core\Security\Headers;
use Core\Auth\SessionManager;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Verificar autenticación
SessionManager::checkAccess('/login?redirect=/dashboard');

// Obtener datos de usuario
$userId = SessionManager::get('user_id');
$userName = SessionManager::get('user_name');

// Definir página activa para el menú
$pageName = 'dashboard';
$pageTitle = 'Mi Panel';

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Obtener listas del usuario
    $lists = $query->table('gift_lists')
        ->find(
            ['user_id' => $userId],
            ['id', 'title', 'slug', 'occasion', 'event_date', 'privacy', 'created_at', 'status']
        );
    
    // Preparar datos de listas
    foreach ($lists as &$list) {
        // Obtener conteo de regalos y reservas para cada lista
        $giftStats = $query->raw(
            "SELECT 
                COUNT(*) as total_gifts,
                SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_gifts
            FROM gifts
            WHERE list_id = :list_id",
            ['list_id' => $list['id']]
        );
        
        $list['gift_count'] = $giftStats[0]['total_gifts'] ?? 0;
        $list['reserved_count'] = $giftStats[0]['reserved_gifts'] ?? 0;
        
        // Calcular porcentaje de reservas
        $list['reservation_percentage'] = $list['gift_count'] > 0
            ? round(($list['reserved_count'] / $list['gift_count']) * 100)
            : 0;
            
        // Formatear fecha del evento
        $list['event_date_formatted'] = !empty($list['event_date'])
            ? (new DateTime($list['event_date']))->format('d/m/Y')
            : 'No definida';
            
        // Formatear fecha de creación
        $list['created_at_formatted'] = !empty($list['created_at'])
            ? (new DateTime($list['created_at']))->format('d/m/Y')
            : '';
    }
    
    // Obtener notificaciones recientes
    $notifications = $query->table('notifications')
        ->find(
            ['user_id' => $userId, 'is_read' => 0],
            ['id', 'type', 'title', 'message', 'created_at']
        );
    
    // Formatear notificaciones
    foreach ($notifications as &$notification) {
        $notification['created_at_formatted'] = !empty($notification['created_at'])
            ? (new DateTime($notification['created_at']))->format('d/m/Y H:i')
            : '';
    }
    
    // Obtener estadísticas generales
    $stats = $query->raw(
        "SELECT 
            (SELECT COUNT(*) FROM gift_lists WHERE user_id = :user_id) as total_lists,
            (SELECT COUNT(*) FROM gifts WHERE list_id IN (SELECT id FROM gift_lists WHERE user_id = :user_id)) as total_gifts,
            (SELECT COUNT(*) FROM gift_reservations WHERE list_id IN (SELECT id FROM gift_lists WHERE user_id = :user_id)) as total_reservations,
            (SELECT COUNT(*) FROM share_events WHERE user_id = :user_id) as total_shares
        ",
        ['user_id' => $userId]
    );
    
    // Estadísticas del usuario
    $userStats = [
        'total_lists' => $stats[0]['total_lists'] ?? 0,
        'total_gifts' => $stats[0]['total_gifts'] ?? 0,
        'total_reservations' => $stats[0]['total_reservations'] ?? 0,
        'total_shares' => $stats[0]['total_shares'] ?? 0
    ];
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error en dashboard: ' . $e->getMessage());
    
    // Inicializar variables vacías para evitar errores
    $lists = [];
    $notifications = [];
    $userStats = [
        'total_lists' => 0,
        'total_gifts' => 0,
        'total_reservations' => 0,
        'total_shares' => 0
    ];
}

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="mb-0">Mi Panel</h1>
            <p class="text-muted">Bienvenido, <?= htmlspecialchars($userName) ?></p>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="/list/create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Lista
            </a>
        </div>
    </div>
    
    <!-- Estadísticas rápidas -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-primary rounded-circle p-3 text-white">
                            <i class="bi bi-card-list"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Listas</h6>
                            <h3 class="mb-0"><?= $userStats['total_lists'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-success rounded-circle p-3 text-white">
                            <i class="bi bi-gift"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Regalos</h6>
                            <h3 class="mb-0"><?= $userStats['total_gifts'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-info rounded-circle p-3 text-white">
                            <i class="bi bi-bookmark-check"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Reservas</h6>
                            <h3 class="mb-0"><?= $userStats['total_reservations'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 bg-warning rounded-circle p-3 text-white">
                            <i class="bi bi-share"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Compartidos</h6>
                            <h3 class="mb-0"><?= $userStats['total_shares'] ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Listas de regalos -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Mis Listas de Regalos</h5>
                        <a href="/lists" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($lists)): ?>
                        <div class="text-center py-5">
                            <div class="display-1 text-muted">
                                <i class="bi bi-card-list"></i>
                            </div>
                            <p class="lead">No tienes listas de regalos todavía</p>
                            <a href="/list/create" class="btn btn-primary">Crear mi primera lista</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Título</th>
                                        <th scope="col">Ocasión</th>
                                        <th scope="col">Fecha</th>
                                        <th scope="col">Reservas</th>
                                        <th scope="col">Estado</th>
                                        <th scope="col">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lists as $list): ?>
                                    <tr>
                                        <td>
                                            <a href="/list/view/<?= htmlspecialchars($list['slug']) ?>" class="text-decoration-none fw-bold text-dark">
                                                <?= htmlspecialchars($list['title']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars(ucfirst($list['occasion'] ?? 'General')) ?></td>
                                        <td><?= htmlspecialchars($list['event_date_formatted']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                        style="width: <?= $list['reservation_percentage'] ?>%;" 
                                                        aria-valuenow="<?= $list['reservation_percentage'] ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100"></div>
                                                </div>
                                                <span class="text-muted small"><?= $list['reserved_count'] ?>/<?= $list['gift_count'] ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($list['status'] === 'active'): ?>
                                                <span class="badge bg-success">Activa</span>
                                            <?php elseif ($list['status'] === 'archived'): ?>
                                                <span class="badge bg-secondary">Archivada</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Eliminada</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($list['privacy'] === 'public'): ?>
                                                <span class="badge bg-info">Pública</span>
                                            <?php elseif ($list['privacy'] === 'private'): ?>
                                                <span class="badge bg-warning">Privada</span>
                                            <?php else: ?>
                                                <span class="badge bg-dark">Con contraseña</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="/list/view/<?= htmlspecialchars($list['slug']) ?>">Ver</a></li>
                                                    <li><a class="dropdown-item" href="/list/edit/<?= $list['id'] ?>">Editar</a></li>
                                                    <li><a class="dropdown-item" href="/gift/add?list_id=<?= $list['id'] ?>">Añadir Regalo</a></li>
                                                    <li><a class="dropdown-item" href="/list/share/<?= $list['id'] ?>">Compartir</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <?php if ($list['status'] === 'active'): ?>
                                                        <li><a class="dropdown-item" href="/list/archive/<?= $list['id'] ?>">Archivar</a></li>
                                                    <?php elseif ($list['status'] === 'archived'): ?>
                                                        <li><a class="dropdown-item" href="/list/activate/<?= $list['id'] ?>">Reactivar</a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item text-danger" href="/list/delete/<?= $list['id'] ?>">Eliminar</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Actividad reciente y notificaciones -->
        <div class="col-lg-4">
            <!-- Notificaciones -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Notificaciones</h5>
                        <?php if (!empty($notifications)): ?>
                            <a href="/notifications" class="btn btn-sm btn-outline-primary">Ver Todas</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-4">
                            <div class="display-4 text-muted">
                                <i class="bi bi-bell"></i>
                            </div>
                            <p class="mb-0">No tienes notificaciones nuevas</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($notifications, 0, 5) as $notification): ?>
                                <a href="/notifications/view/<?= $notification['id'] ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?= htmlspecialchars($notification['title']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($notification['created_at_formatted']) ?></small>
                                    </div>
                                    <p class="mb-1 small text-truncate"><?= htmlspecialchars($notification['message']) ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Enlaces rápidos -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Enlaces Rápidos</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="/list/create" class="list-group-item list-group-item-action">
                            <i class="bi bi-plus-circle me-2"></i> Crear nueva lista
                        </a>
                        <a href="/gift/add" class="list-group-item list-group-item-action">
                            <i class="bi bi-gift me-2"></i> Añadir regalo
                        </a>
                        <a href="/lists" class="list-group-item list-group-item-action">
                            <i class="bi bi-card-list me-2"></i> Ver todas mis listas
                        </a>
                        <a href="/profile" class="list-group-item list-group-item-action">
                            <i class="bi bi-person me-2"></i> Mi perfil
                        </a>
                        <a href="/help" class="list-group-item list-group-item-action">
                            <i class="bi bi-question-circle me-2"></i> Centro de ayuda
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>