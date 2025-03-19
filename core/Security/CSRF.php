<?php
/**
 * Clase para la protección contra ataques CSRF (Cross-Site Request Forgery)
 */
namespace Core\Security;

class CSRF {
    /**
     * Genera un token CSRF para formularios
     * 
     * @return string Token generado
     */
    public static function generateToken(): string {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        // Generar un token aleatorio si no existe
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Inserta un campo de token CSRF en un formulario HTML
     * 
     * @return string Campo de formulario HTML con el token
     */
    public static function tokenField(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Valida un token CSRF recibido
     * 
     * @param string $token Token recibido para validar
     * @return bool True si el token es válido
     */
    public static function validate(?string $token): bool {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!$token || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        // Verificar si el token coincide con el almacenado en sesión
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        
        // Regenerar el token para formularios posteriores (one-time use)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        return $valid;
    }
}