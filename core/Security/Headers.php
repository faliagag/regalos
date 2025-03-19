<?php
/**
 * Clase para la gestión de cabeceras HTTP de seguridad
 */
namespace Core\Security;

class Headers {
    /**
     * Establece todas las cabeceras de seguridad recomendadas
     *
     * @return void
     */
    public static function setSecureHeaders(): void {
        // Prevenir que el navegador "adivine" el tipo MIME
        header('X-Content-Type-Options: nosniff');
        
        // Habilitar la protección XSS en navegadores antiguos
        header('X-XSS-Protection: 1; mode=block');
        
        // Evitar que el sitio sea embebido en frames (clickjacking)
        header('X-Frame-Options: SAMEORIGIN');
        
        // Content Security Policy (CSP)
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; connect-src 'self'; img-src 'self'; style-src 'self';");
        
        // Strict Transport Security (HSTS) - forzar conexiones HTTPS
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        
        // Referrer Policy - controlar información enviada en cabecera Referer
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Deshabilitar almacenamiento en caché para contenido dinámico
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        // Deshabilitar la exposición de información del servidor
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Establece cabeceras para API JSON
     *
     * @return void
     */
    public static function setAPIHeaders(): void {
        // Establecer tipo de contenido a JSON
        header('Content-Type: application/json; charset=UTF-8');
        
        // Permitir CORS específico si es necesario 
        // (modificar según necesidades de seguridad)
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        // Agregar también las cabeceras de seguridad básicas
        self::setSecureHeaders();
    }
    
    /**
     * Establece cabeceras para contenido público cacheable
     *
     * @param int $maxAge Tiempo máximo de caché en segundos
     * @return void
     */
    public static function setCacheHeaders(int $maxAge = 3600): void {
        // Cabeceras para habilitar caché
        header('Cache-Control: public, max-age=' . $maxAge);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
        
        // Añadir ETag para validación de caché
        $etag = md5($_SERVER['REQUEST_URI'] . filemtime($_SERVER['SCRIPT_FILENAME']));
        header('ETag: "' . $etag . '"');
        
        // Verificar si el cliente tiene una versión en caché válida
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === '"' . $etag . '"') {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }
    }
}