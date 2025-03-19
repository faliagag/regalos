<?php
/**
 * Confirmación de eliminación de lista de regalos
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

// Obtener ID de usuario y de lista
$userId = SessionManager::get('user_id');
$listId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Verificar si se proporcionó un ID de lista
if (!$listId) {
    header('Location: /dashboard?error=invalid_list');
    exit;
}

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Obtener la lista a eliminar
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
    
    // Obtener conteo de regalos asociados a la lista
    $giftCount = $query->table('gifts')
        ->count(['list_id' => $listId]);
    
    // Obtener conteo de reservas asociadas a la lista
    $reservationCount = $query->raw(
        "SELECT COUNT(*) as count FROM gift_reservations WHERE list_id = :list_id",
        ['list_id' => $listId]
    );
    
    $hasReservations = ($reservationCount[0]['count'] ?? 0) > 0;
    
    // Procesar formulario de eliminación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_list') {
        // Validar token CSRF
        if (!CSRF::validate($_POST['csrf_token'] ?? '')) {
            header('Location: /list/delete/' . $listId . '?error=csrf');
            exit;
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        // Eliminar primero las reservas asociadas a los regalos de la lista
        $query->raw(
            "DELETE FROM gift_reservations WHERE list_id = :list_id",
            ['list_id' => $listId]
        );
        
        // Eliminar eventos de regalos asociados a la lista
        $query->raw(
            "DELETE FROM gift_events WHERE list_id = :list_id",
            ['list_id' => $listId]
        );
        
        // Eliminar regalos asociados a la lista
        $query->table('gifts')->delete(['list_id' => $listId]);
        
        // Eliminar eventos de compartir asociados a la lista
        $query->table('share_events')->delete(['list_id' => $listId]);
        
        // Eliminar códigos de acceso asociados a la lista
        $query->table('list_access_codes')->delete(['list_id' => $listId]);
        
        // Finalmente, eliminar la lista
        $query->table('gift_lists')->delete(['id' => $listId]);
        
        // Confirmar transacción
        $db->commit();
        
        // Redireccionar al dashboard con mensaje de éxito
        header('Location: /dashboard?success=list_deleted');
        exit;
    }
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error al obtener lista: ' . $e->getMessage());
    
    // Redirigir con error
    header('Location: /dashboard?error=system');
    exit;
}

// Definir página activa y título
$pageName = '';
$pageTitle = 'Eliminar Lista';

// Incluir header
require_once __DIR__ . '/../../../includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-danger text-white">
                    <h1 class="h4 mb-0">Eliminar Lista de Regalos</h1>
                </div>
                
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <i class="bi bi-exclamation-triangle-fill text-danger display-1"></i>
                    </div>
                    
                    <h2 class="mb-3">¿Estás seguro?</h2>
                    
                    <p class="mb-4">
                        Estás a punto de eliminar la lista <strong>"<?= htmlspecialchars($list['title']) ?>"</strong>. 
                        Esta acción no se puede deshacer.
                    </p>
                    
                    <div class="alert alert-warning">
                        <ul class="mb-0 text-start">
                            <li>Se eliminarán <strong><?= $giftCount ?> regalos</strong> asociados a esta lista.</li>
                            <?php if ($hasReservations): ?>
                                <li>Se perderán todas las reservas hechas por tus invitados.</li>
                            <?php endif; ?>
                            <li>Los enlaces compartidos dejarán de funcionar.</li>
                            <li>No podrás recuperar esta información después de eliminarla.</li>
                        </ul>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="/list/view/<?= htmlspecialchars($list['slug']) ?>" class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-arrow-left"></i> Cancelar
                        </a>
                        
                        <form action="/list/delete/<?= $listId ?>" method="POST">
                            <?= CSRF::tokenField() ?>
                            <input type="hidden" name="action" value="delete_list">
                            <button type="submit" class="btn btn-danger btn-lg">
                                <i class="bi bi-trash"></i> Sí, Eliminar Lista
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <p class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    ¿Quieres conservar tu lista pero ya no usarla? Considera 
                    <a href="/list/archive/<?= $listId ?>">archivarla</a> en lugar de eliminarla.
                </p>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../../includes/footer.php';
?>