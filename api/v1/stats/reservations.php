<?php
/**
 * API Endpoint para estadísticas de reservas
 */
require_once __DIR__ . '/../../../core/Security/Headers.php';
require_once __DIR__ . '/../../../core/Database/Connection.php';
require_once __DIR__ . '/../../../core/Database/QueryBuilder.php';
require_once __DIR__ . '/../../../core/Auth/SessionManager.php';

use Core\Security\Headers;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Auth\SessionManager;

// Establecer cabeceras para API
Headers::setAPIHeaders();

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Verificar autenticación
SessionManager::startSecureSession();
$userId = SessionManager::get('user_id');

if (!$userId) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

// Obtener parámetros
$listId = isset($_GET['list_id']) && is_numeric($_GET['list_id']) ? (int) $_GET['list_id'] : null;
$period = isset($_GET['period']) ? $_GET['period'] : 'all'; // all, week, month, year

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Verificar que el usuario tenga acceso a la lista
    if ($listId) {
        $list = $query->table('gift_lists')->findOne([
            'id' => $listId,
            'user_id' => $userId
        ]);
        
        if (!$list) {
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'error' => 'No tienes acceso a esta lista']);
            exit;
        }
    }
    
    // Construir consulta según parámetros
    $sql = "
    SELECT 
        COUNT(*) as total_reservations,
        SUM(CASE WHEN gr.status = 'active' THEN 1 ELSE 0 END) as active_reservations,
        SUM(CASE WHEN gr.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_reservations,
        SUM(CASE WHEN gr.is_anonymous = 1 THEN 1 ELSE 0 END) as anonymous_reservations,
        COUNT(DISTINCT gr.gift_id) as reserved_gifts_count,
        COALESCE(SUM(g.price), 0) as total_reserved_value,
        DATE_FORMAT(gr.reservation_date, '%Y-%m-%d') as reservation_day,
        DATE_FORMAT(gr.reservation_date, '%Y-%m') as reservation_month
    FROM 
        gift_reservations gr
    JOIN 
        gifts g ON gr.gift_id = g.id
    JOIN 
        gift_lists gl ON g.list_id = gl.id
    WHERE 
        gl.user_id = :user_id
    ";
    
    $params = ['user_id' => $userId];
    
    // Filtrar por lista específica
    if ($listId) {
        $sql .= " AND gl.id = :list_id";
        $params['list_id'] = $listId;
    }
    
    // Filtrar por período
    switch ($period) {
        case 'week':
            $sql .= " AND gr.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $sql .= " AND gr.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'year':
            $sql .= " AND gr.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
    }
    
    // Agrupar por día o mes según el período
    if ($period == 'all' || $period == 'year') {
        $sql .= " GROUP BY reservation_month ORDER BY reservation_month";
    } else {
        $sql .= " GROUP BY reservation_day ORDER BY reservation_day";
    }
    
    // Ejecutar consulta
    $stats = $query->raw($sql, $params);
    
    // Obtener estadísticas generales
    $generalStats = [];
    
    // Consulta para obtener el total de regalos y valor total
    $totalsSql = "
    SELECT 
        COUNT(*) as total_gifts,
        SUM(CASE WHEN g.status = 'reserved' THEN 1 ELSE 0 END) as reserved_count,
        SUM(CASE WHEN g.status = 'available' THEN 1 ELSE 0 END) as available_count,
        COALESCE(SUM(g.price), 0) as total_value,
        COALESCE(SUM(CASE WHEN g.status = 'reserved' THEN g.price ELSE 0 END), 0) as reserved_value,
        COUNT(DISTINCT g.list_id) as list_count
    FROM 
        gifts g
    JOIN 
        gift_lists gl ON g.list_id = gl.id
    WHERE 
        gl.user_id = :user_id
    ";
    
    if ($listId) {
        $totalsSql .= " AND gl.id = :list_id";
    }
    
    $totals = $query->raw($totalsSql, $params);
    
    if (!empty($totals)) {
        $generalStats = $totals[0];
        
        // Calcular porcentajes
        if ($generalStats['total_gifts'] > 0) {
            $generalStats['reservation_percentage'] = round(
                ($generalStats['reserved_count'] / $generalStats['total_gifts']) * 100, 
                2
            );
        } else {
            $generalStats['reservation_percentage'] = 0;
        }
    }
    
    // Obtener reservantes más frecuentes (no anónimos)
    $topReserversSql = "
    SELECT 
        gr.name,
        COUNT(*) as reservation_count
    FROM 
        gift_reservations gr
    JOIN 
        gifts g ON gr.gift_id = g.id
    JOIN 
        gift_lists gl ON g.list_id = gl.id
    WHERE 
        gl.user_id = :user_id
        AND gr.is_anonymous = 0
    ";
    
    if ($listId) {
        $topReserversSql .= " AND gl.id = :list_id";
    }
    
    $topReserversSql .= "
    GROUP BY 
        gr.name
    ORDER BY 
        reservation_count DESC
    LIMIT 5
    ";
    
    $topReservers = $query->raw($topReserversSql, $params);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'data' => [
            'time_series' => $stats,
            'general' => $generalStats,
            'top_reservers' => $topReservers
        ]
    ];
    
    // Respuesta exitosa
    echo json_encode($response);
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error en API de estadísticas: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
    exit;
}