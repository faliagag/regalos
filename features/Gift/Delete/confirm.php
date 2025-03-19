<?php
/**
 * Confirmación de eliminación de regalo
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
    
    // Verificar si el regalo está reservado
    $isReserved = $gift['status'] === 'reserved';
    
    // Si está reservado, obtener información de la reserva
    $reservation = null;
    if ($isReserved) {
        $reservation = $query->table('gift_reservations')
            ->findOne([
                'gift_id' => $giftId,
                'status' => 'active'
            ]);
    }
    
    // Procesar formulario de eliminación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_gift') {
        // Validar token CSRF
        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            header('Location: /gift/delete/' . $giftId . '?error=csrf');
            exit;
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Eliminar reservas asociadas al regalo
        $query->table('gift_reservations')->delete(['gift_id' => $giftId]);
        
        // Eliminar eventos asociados al regalo
        $query->table('gift_events')->delete(['gift_id' => $giftId]);
        
        // Finalmente, eliminar el regalo
        $query->table('gifts')->delete(['id' => $giftId]);
        
        // Confirmar transacción
        $db->commit();
        
        // Redireccionar a la lista con mensaje de éxito
        header('Location: /list/view/' . $list['slug'] . '?success=gift_deleted');
        exit;
    }
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al obtener regalo: ' . $e->getMessage());
    
    // Redirigir con error
    header('Location: /dashboard?error=system');
    exit;
}

// Definir página activa y título
$pageName = '';
$pageTitle = 'Eliminar Regalo';

// Incluir header
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h1 class="h4 mb-0">Eliminar Regalo</h1>
                </div>
                
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-danger display-1"></i>
                    </div>
                    
                    <h2 class="mb-3">¿Estás seguro?</h2>
                    
                    <p class="mb-4">
                        Estás a punto de eliminar el regalo <strong>"<?= htmlspecialchars($gift['title']) ?>"</strong> 
                        de tu lista <strong>"<?= htmlspecialchars($list['title']) ?>"</strong>. 
                        Esta acción no se puede deshacer.
                    </p>
                    
                    <?php if ($isReserved && $reservation): ?>
                        <div class="alert alert-warning">
                            <p class="mb-0"><strong>Importante:</strong> Este regalo está actualmente <strong>reservado</strong>
                            <?php if (!$reservation['is_anonymous']): ?> 
                                por <strong><?= htmlspecialchars($reservation['name']) ?></strong>
                            <?php else: ?>
                                por un invitado de forma anónima
                            <?php endif; ?>. 
                            Al eliminarlo, la reserva también se eliminará.</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="/list/view/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                        
                        <form action="/gift/delete/<?= $giftId ?>" method="POST">
                            <?= CSRF::tokenField() ?>
                            <input type="hidden" name="action" value="delete_gift">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-trash"></i> Sí, Eliminar Regalo
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Detalles del regalo -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Detalles del Regalo</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (!empty($gift['image_url'])): ?>
                            <div class="col-md-4 mb-3 mb-md-0">
                                <img src="<?= htmlspecialchars($gift['image_url']) ?>" alt="<?= htmlspecialchars($gift['title']) ?>" 
                                     class="img-fluid rounded" style="max-height: 150px;">
                            </div>
                            <div class="col-md-8">
                        <?php else: ?>
                            <div class="col-12">
                        <?php endif; ?>
                                <h5><?= htmlspecialchars($gift['title']) ?></h5>
                                
                                <?php if (!empty($gift['description'])): ?>
                                    <p><?= nl2br(htmlspecialchars($gift['description'])) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($gift['price'])): ?>
                                    <p><strong>Precio aproximado:</strong> $<?= number_format($gift['price'], 2) ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($gift['url'])): ?>
                                    <p><strong>URL:</strong> <a href="<?= htmlspecialchars($gift['url']) ?>" target="_blank"><?= htmlspecialchars($gift['url']) ?></a></p>
                                <?php endif; ?>
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../../includes/footer.php';
?>