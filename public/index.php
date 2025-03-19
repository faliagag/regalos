<?php
/**
 * Front Controller para el sistema de listas de regalos
 */

// Definir raíz del proyecto
define('ROOT_PATH', dirname(__DIR__));

// Autocargar clases básicas
require_once ROOT_PATH . '/core/Security/Headers.php';
require_once ROOT_PATH . '/core/Auth/SessionManager.php';
require_once ROOT_PATH . '/includes/Cache.php';

use Core\Security\Headers;
use Core\Auth\SessionManager;

// Establecer cabeceras de seguridad por defecto
Headers::setSecureHeaders();

// Iniciar sesión segura
SessionManager::startSecureSession();

// Obtener ruta solicitada (limpiada para seguridad)
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remover path base del script para obtener ruta relativa
$baseDir = dirname($scriptName);
if ($baseDir != '/') {
    $requestUri = substr($requestUri, strlen($baseDir));
}

// Remover parámetros de consulta
$requestPath = parse_url($requestUri, PHP_URL_PATH);

// Normalizar la ruta
$requestPath = '/' . trim($requestPath, '/');

// Enrutamiento básico
$routes = [
    // Páginas públicas
    '/' => '/features/Home/index.php',
    '/login' => '/features/UserLogin/form.php',
    '/register' => '/features/UserRegister/form.php',
    '/reset-password' => '/features/UserRecovery/form.php',
    
    // Procesamiento de autenticación
    '/login/process.php' => '/features/UserLogin/process.php',
    '/login/logout.php' => '/features/UserLogin/logout.php',
    
    // Área de usuario
    '/dashboard' => '/features/UserDashboard/index.php',
    '/profile' => '/features/UserProfile/index.php',
    
    // Gestión de listas
    '/list/create' => '/features/GiftList/Create/form.php',
    '/list/create/save.php' => '/features/GiftList/Create/save.php',
    '/list/edit/{id}' => '/features/GiftList/Edit/form.php',
    '/list/delete/{id}' => '/features/GiftList/Delete/confirm.php',
    
    // Visualización de listas
    '/list/{slug}' => '/features/GiftList/View/public.php',
    '/list/view/{slug}' => '/features/GiftList/View/index.php',
    
    // Gestión de regalos
    '/gift/add' => '/features/Gift/Add/form.php',
    '/gift/edit/{id}' => '/features/Gift/Edit/form.php',
    '/gift/delete/{id}' => '/features/Gift/Delete/confirm.php',
];

// Variables para parámetros dinámicos
$params = [];

// Verificar si la ruta solicitada está en las rutas definidas
$scriptFile = null;

// Primero buscar coincidencia exacta
if (isset($routes[$requestPath])) {
    $scriptFile = $routes[$requestPath];
} else {
    // Buscar coincidencia con parámetros dinámicos
    foreach ($routes as $route => $file) {
        // Convertir patrones {param} a expresiones regulares
        if (strpos($route, '{') !== false) {
            $pattern = preg_replace('/{([^\/]+)}/', '([^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            
            if (preg_match($pattern, $requestPath, $matches)) {
                $scriptFile = $file;
                
                // Extraer nombres de parámetros
                preg_match_all('/{([^\/]+)}/', $route, $paramNames);
                
                // Asignar valores capturados a los nombres de parámetros
                for ($i = 0; $i < count($paramNames[1]); $i++) {
                    $params[$paramNames[1][$i]] = $matches[$i + 1];
                }
                
                break;
            }
        }
    }
}

// Si no se encontró ruta, buscar en la carpeta API
if (!$scriptFile && strpos($requestPath, '/api/') === 0) {
    $apiPath = ROOT_PATH . $requestPath . '.php';
    
    if (file_exists($apiPath)) {
        $scriptFile = $requestPath . '.php';
    }
}

// Verificar si es una solicitud para un archivo estático
if (!$scriptFile) {
    $staticPath = __DIR__ . $requestPath;
    
    if (file_exists($staticPath) && is_file($staticPath)) {
        // Determinar tipo MIME
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        ];
        
        $extension = strtolower(pathinfo($staticPath, PATHINFO_EXTENSION));
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
        
        // Establecer cabeceras para caché según el tipo de archivo
        $cacheTime = 604800; // 1 semana
        Headers::setCacheHeaders($cacheTime);
        
        // Establecer tipo de contenido
        header('Content-Type: ' . $contentType);
        
        // Enviar archivo
        readfile($staticPath);
        exit;
    }
}

// Si se encontró una ruta válida, incluir el archivo correspondiente
if ($scriptFile) {
    // Establecer parámetros como variables para usar en los scripts
    foreach ($params as $key => $value) {
        $_GET[$key] = $value;
    }
    
    // Incluir el archivo del controlador
    $fullPath = ROOT_PATH . $scriptFile;
    
    if (file_exists($fullPath)) {
        // Intentar usar caché para algunas páginas públicas
        $cacheable = [
            '/features/Home/index.php',
            '/features/GiftList/View/public.php'
        ];
        
        if (in_array($scriptFile, $cacheable) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // Parámetros que afectan caché
            $cacheKey = 'page_' . md5($requestPath . serialize($_GET));
            
            // Verificar si hay versión en caché
            $cachedContent = Cache::get($cacheKey);
            
            if ($cachedContent !== null) {
                echo $cachedContent;
                exit;
            }
            
            // Iniciar buffer para almacenar en caché
            ob_start();
            include $fullPath;
            $content = ob_get_clean();
            
            // Guardar en caché (TTL: 1 hora)
            Cache::set($cacheKey, $content, 3600);
            
            echo $content;
            exit;
        }
        
        // Para páginas no cacheables, incluir directamente
        include $fullPath;
        exit;
    }
}

// Si llegamos aquí, la ruta no se encontró
http_response_code(404);
include ROOT_PATH . '/features/Errors/404.php';