<?php
/**
 * Clase para el manejo seguro de sesiones
 */
namespace Core\Auth;

class SessionManager {
    /**
     * Inicia una sesión segura
     * 
     * @return bool Success status
     */
    public static function startSecureSession(): bool {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        
        // Configuración de seguridad para sesiones
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.gc_maxlifetime', 3600); // 1 hora
        ini_set('session.cookie_lifetime', 0); // Hasta que se cierre el navegador
        
        // Iniciar sesión
        return session_start();
    }
    
    /**
     * Regenera el ID de sesión para prevenir ataques de fijación
     * 
     * @param bool $deleteOldSession Si se elimina la sesión anterior
     * @return bool Éxito de la operación
     */
    public static function regenerateId(bool $deleteOldSession = true): bool {
        return session_regenerate_id($deleteOldSession);
    }
    
    /**
     * Establece una variable de sesión
     * 
     * @param string $key Clave a establecer
     * @param mixed $value Valor a almacenar
     * @return void
     */
    public static function set(string $key, $value): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        
        $_SESSION[$key] = $value;
    }
    
    /**
     * Obtiene un valor de sesión
     * 
     * @param string $key Clave a obtener
     * @param mixed $default Valor por defecto si no existe
     * @return mixed Valor almacenado o default
     */
    public static function get(string $key, $default = null) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Verifica si existe una clave en sesión
     * 
     * @param string $key Clave a verificar
     * @return bool True si existe
     */
    public static function has(string $key): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        
        return isset($_SESSION[$key]);
    }
    
    /**
     * Elimina una variable de sesión
     * 
     * @param string $key Clave a eliminar
     * @return void
     */
    public static function remove(string $key): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        
        if (isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Destruye la sesión actual
     * 
     * @return bool Éxito de la operación
     */
    public static function destroy(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true; // No hay sesión que destruir
        }
        
        // Limpiar variables de sesión
        $_SESSION = [];
        
        // Eliminar cookie de sesión
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destruir sesión
        return session_destroy();
    }
    
    /**
     * Establece datos de usuario en sesión
     * 
     * @param array $userData Datos de usuario a almacenar
     * @return void
     */
    public static function setUser(array $userData): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        
        // Almacenar ID y otros datos básicos (evitar datos sensibles)
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['user_name'] = $userData['name'] ?? '';
        $_SESSION['user_email'] = $userData['email'] ?? '';
        $_SESSION['user_role'] = $userData['role'] ?? 'user';
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        
        // Regenerar ID para prevenir ataques de session fixation
        self::regenerateId();
    }
    
    /**
     * Verifica si un usuario está logueado
     * 
     * @return bool True si hay sesión de usuario activa
     */
    public static function isLoggedIn(): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::startSecureSession();
        }
        
        // Verificar existencia de variables de sesión de usuario
        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            return false;
        }
        
        // Verificar tiempo de inactividad (30 minutos)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            self::destroy();
            return false;
        }
        
        // Actualizar tiempo de actividad
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    /**
     * Verifica acceso y redirige si no está logueado
     * 
     * @param string $redirectTo URL de redirección si no hay acceso
     * @return bool True si tiene acceso
     */
    public static function checkAccess(string $redirectTo = '/login'): bool {
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirectTo);
            exit;
        }
        
        return true;
    }
}