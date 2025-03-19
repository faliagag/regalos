<?php
/**
 * API Endpoint para obtener regalos populares
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

// Verificar tipo de solicitud (pública o privada)
$isPublic = isset($_GET['public']) && $_GET['public'] == '1';

// Si es solicitud privada, verificar autenticación
if (!$isPublic) {
    SessionManager::startSecureSession();
    $userId = SessionManager::get('user_id');
    
    if (!$userId) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
        exit;
    }
}

// Obtener parámetros
$category = $_GET['category'] ?? '';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? min((int) $_GET['limit'], 50) : 10;
$period = $_GET['period'] ?? 'month'; // week, month, year, all

try {
    // Conexión a base de datos
    $db = Connection::getInstance();
    $query = new QueryBuilder($db);
    
    // Construir consulta según parámetros
    $sql = "
    SELECT 
        g.id,
        g.title,
        g.description,
        g.price,
        g.image_url,
        g.category,
        g.url,
        COUNT(DISTINCT gr.id) as reservation_count,
        COUNT(DISTINCT ge.id) as view_count,
        (
            (COUNT(DISTINCT gr.id) * 2) + 
            (COUNT(DISTINCT ge.id) * 0.5)
        ) as popularity_score
    FROM 
        gifts g
    LEFT JOIN 
        gift_reservations gr ON g.id = gr.gift_id
    LEFT JOIN 
        gift_events ge ON g.id = ge.gift_id AND ge.event_type = 'viewed'
    JOIN 
        gift_lists gl ON g.list_id = gl.id
    WHERE 
        1=1
    ";
    
    $params = [];
    
    // Filtrar por visibilidad según tipo de solicitud
    if ($isPublic) {
        // Para solicitudes públicas, solo mostrar de listas públicas
        $sql .= " AND gl.privacy = 'public'";
    } else {
        // Para solicitudes privadas, mostrar solo de listas del usuario
        $sql .= " AND gl.user_id = :user_id";
        $params['user_id'] = $userId;
    }
    
    // Filtrar por categoría si se especifica
    if (!empty($category)) {
        $sql .= " AND g.category = :category";
        $params['category'] = $category;
    }
    
    // Filtrar por período
    switch ($period) {
        case 'week':
            $sql .= " AND (
                gr.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) OR
                ge.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            )";
            break;
        case 'month':
            $sql .= " AND (
                gr.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR
                ge.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            )";
            break;
        case 'year':
            $sql .= " AND (
                gr.reservation_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR) OR
                ge.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            )";
            break;
    }
    
    // Agrupar y ordenar
    $sql .= "
    GROUP BY 
        g.id
    HAVING 
        popularity_score > 0
    ORDER BY 
        popularity_score DESC, reservation_count DESC, view_count DESC
    LIMIT :limit
    ";
    
    $params['limit'] = $limit;
    
    // Ejecutar consulta
    $popularGifts = $query->raw($sql, $params);
    
    // Si no hay resultados con popularidad, obtener los más recientes
    if (empty($popularGifts)) {
        $recentSql = "
        SELECT 
            g.id,
            g.title,
            g.description,
            g.price,
            g.image_url,
            g.category,
            g.url,
            0 as reservation_count,
            0 as view_count,
            0 as popularity_score
        FROM 
            gifts g
        JOIN 
            gift_lists gl ON g.list_id = gl.id
        WHERE 
            1=1
        ";
        
        $recentParams = [];
        
        // Filtrar por visibilidad
        if ($isPublic) {
            $recentSql .= " AND gl.privacy = 'public'";
        } else {
            $recentSql .= " AND gl.user_id = :user_id";
            $recentParams['user_id'] = $userId;
        }
        
        // Filtrar por categoría
        if (!empty($category)) {
            $recentSql .= " AND g.category = :category";
            $recentParams['category'] = $category;
        }
        
        // Ordenar por fecha de creación
        $recentSql .= "
        ORDER BY 
            g.created_at DESC
        LIMIT :limit
        ";
        
        $recentParams['limit'] = $limit;
        
        // Ejecutar consulta
        $popularGifts = $query->raw($recentSql, $recentParams);
    }
    
    // Obtener categorías disponibles
    $categoriesSql = "
    SELECT 
        DISTINCT g.category,
        COUNT(*) as gift_count
    FROM 
        gifts g
    JOIN 
        gift_lists gl ON g.list_id = gl.id
    WHERE 
        g.category != ''
    ";
    
    $categoriesParams = [];
    
    // Filtrar por visibilidad
    if ($isPublic) {
        $categoriesSql .= " AND gl.privacy = 'public'";
    } else {
        $categoriesSql .= " AND gl.user_id = :user_id";
        $categoriesParams['user_id'] = $userId;
    }
    
    $categoriesSql .= "
    GROUP BY 
        g.category
    ORDER BY 
        gift_count DESC
    ";
    
    $categories = $query->raw($categoriesSql, $categoriesParams);
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'data' => [
            'gifts' => $popularGifts,
            'categories' => $categories,
            'filters' => [
                'category' => $category,
                'period' => $period,
                'limit' => $limit,
                'is_public' => $isPublic
            ]
        ]
    ];
    
    // Respuesta exitosa
    echo json_encode($response);
    exit;
    
} catch (\Exception $e) {
    // Registrar error
    error_log('Error en API de regalos populares: ' . $e->getMessage());
    
    // Respuesta de error
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'error' => 'Error en el servidor']);
    exit;
}