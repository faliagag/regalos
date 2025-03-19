<?php
/**
 * Clase para el manejo seguro de cookies
 */
namespace Core\Auth;

class CookieHandler {
    /**
     * Configuración por defecto para cookies
     * @var array
     */
    private static array $defaultOptions = [
        'expires' => 0,           // 0 = hasta que se cierre el navegador
        'path' => '/',            // Disponible en todo el sitio
        'domain' => '',           // Dominio actual
        'secure' => true,         // Solo a través de HTTPS
        'httponly' => true,       // No accesible por JavaScript
        'samesite' => 'Lax'       // Protección contra CSRF
    ];
    
    /**
     * Establece una cookie de forma segura
     *
     * @param string $name Nombre de la cookie
     * @param string $value Valor a almacenar
     * @param int $expires Tiempo de expiración (segundos desde ahora)
     * @param array $options Opciones adicionales para la cookie
     * @return bool Éxito de la operación
     */
    public static function set(
        string $name,
        string $value,
        int $expires = 0,
        array $options = []
    ): bool {
        // Fusionar opciones con los valores por defecto
        $cookieOptions = array_merge(self::$defaultOptions, $options);
        
        // Si se proporciona un tiempo de expiración, calcular el timestamp
        if ($expires > 0) {
            $cookieOptions['expires'] = time() + $expires;
        }
        
        // Ajustar la opción secure según el entorno
        if (!isset($options['secure'])) {
            $cookieOptions['secure'] = isset($_SERVER['HTTPS']);
        }
        
        // PHP 7.3+ soporta el array de opciones
        return setcookie(
            $name,
            $value,
            $cookieOptions
        );
    }
    
    /**
     * Establece una cookie con valor cifrado
     *
     * @param string $name Nombre de la cookie
     * @param string $value Valor a almacenar (será cifrado)
     * @param int $expires Tiempo de expiración (segundos desde ahora)
     * @param array $options Opciones adicionales para la cookie
     * @return bool Éxito de la operación
     */
    public static function setEncrypted(
        string $name,
        string $value,
        int $expires = 0,
        array $options = []
    ): bool {
        // Generar clave de cifrado basada en un secreto de la aplicación
        $secret = getenv('APP_SECRET') ?: 'default_secret_key_change_in_production';
        $key = substr(hash('sha256', $secret), 0, 32);
        
        // Generar IV aleatorio
        $iv = random_bytes(16);
        
        // Cifrar el valor
        $encrypted = openssl_encrypt(
            $value,
            'AES-256-CBC',
            $key,
            0,
            $iv
        );
        
        // Combinar IV y valor cifrado
        $encryptedValue = base64_encode($iv . $encrypted);
        
        // Establecer la cookie con el valor cifrado
        return self::set($name, $encryptedValue, $expires, $options);
    }
    
    /**
     * Obtiene el valor de una cookie
     *
     * @param string $name Nombre de la cookie
     * @param mixed $default Valor por defecto si no existe
     * @return string|mixed Valor de la cookie o default
     */
    public static function get(string $name, $default = null) {
        return $_COOKIE[$name] ?? $default;
    }
    
    /**
     * Obtiene y descifra el valor de una cookie cifrada
     *
     * @param string $name Nombre de la cookie
     * @param mixed $default Valor por defecto si no existe
     * @return string|mixed Valor descifrado o default
     */
    public static function getDecrypted(string $name, $default = null) {
        $encryptedValue = self::get($name);
        
        if ($encryptedValue === null) {
            return $default;
        }
        
        try {
            // Decodificar el valor
            $decodedValue = base64_decode($encryptedValue);
            
            // Extraer IV (primeros 16 bytes)
            $iv = substr($decodedValue, 0, 16);
            $encrypted = substr($decodedValue, 16);
            
            // Generar clave de cifrado
            $secret = getenv('APP_SECRET') ?: 'default_secret_key_change_in_production';
            $key = substr(hash('sha256', $secret), 0, 32);
            
            // Descifrar
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $key,
                0,
                $iv
            );
            
            return $decrypted !== false ? $decrypted : $default;
        } catch (\Exception $e) {
            // Registrar error sin exponer detalles sensibles
            error_log('Error al descifrar cookie: ' . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Verifica si existe una cookie
     *
     * @param string $name Nombre de la cookie
     * @return bool True si existe
     */
    public static function has(string $name): bool {
        return isset($_COOKIE[$name]);
    }
    
    /**
     * Elimina una cookie
     *
     * @param string $name Nombre de la cookie
     * @return bool Éxito de la operación
     */
    public static function delete(string $name): bool {
        if (!self::has($name)) {
            return true;
        }
        
        // Establecer tiempo de expiración en el pasado
        return self::set($name, '', -3600);
    }
    
    /**
     * Establece una cookie para mantener sesión (remember me)
     *
     * @param int $userId ID del usuario
     * @param string $token Token único para validación
     * @param int $days Días de validez
     * @return bool Éxito de la operación
     */
    public static function setRememberMe(int $userId, string $token, int $days = 30): bool {
        // Crear valor con formato userId|token para validación posterior
        $value = $userId . '|' . $token;
        
        // Establecer cookie cifrada que expira en los días especificados
        return self::setEncrypted(
            'remember_me',
            $value,
            $days * 86400 // Convertir días a segundos
        );
    }
    
    /**
     * Verifica y obtiene datos de remember me
     *
     * @return array|null Array con [userId, token] o null
     */
    public static function getRememberMe(): ?array {
        $value = self::getDecrypted('remember_me');
        
        if (!$value) {
            return null;
        }
        
        // Dividir en userId y token
        $parts = explode('|', $value, 2);
        
        if (count($parts) !== 2) {
            return null;
        }
        
        return [
            'user_id' => (int) $parts[0],
            'token' => $parts[1]
        ];
    }
}