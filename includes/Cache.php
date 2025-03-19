<?php
/**
 * Clase para manejo de caché
 */
class Cache {
    /**
     * Directorio para almacenar archivos de caché
     * @var string
     */
    private static string $cacheDir = __DIR__ . '/../cache/';
    
    /**
     * Obtiene un valor de caché
     *
     * @param string $key Clave de la caché
     * @return mixed|null Valor almacenado o null si no existe o expiró
     */
    public static function get(string $key) {
        $filePath = self::getFilePath($key);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        $cache = unserialize($content);
        
        // Verificar si ha expirado
        if ($cache['expires'] !== 0 && $cache['expires'] < time()) {
            self::delete($key);
            return null;
        }
        
        return $cache['data'];
    }
    
    /**
     * Almacena un valor en caché
     *
     * @param string $key Clave para almacenar
     * @param mixed $data Datos a almacenar
     * @param int $ttl Tiempo de vida en segundos (0 = nunca expira)
     * @return bool Éxito de la operación
     */
    public static function set(string $key, $data, int $ttl = 3600): bool {
        self::ensureCacheDir();
        
        $filePath = self::getFilePath($key);
        $expires = $ttl > 0 ? time() + $ttl : 0;
        
        $cache = [
            'expires' => $expires,
            'data' => $data
        ];
        
        return file_put_contents($filePath, serialize($cache)) !== false;
    }
    
    /**
     * Elimina un valor de caché
     *
     * @param string $key Clave a eliminar
     * @return bool Éxito de la operación
     */
    public static function delete(string $key): bool {
        $filePath = self::getFilePath($key);
        
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        
        return true;
    }
    
    /**
     * Limpia toda la caché o por patrón
     *
     * @param string|null $pattern Patrón para limpieza selectiva
     * @return bool Éxito de la operación
     */
    public static function clear(?string $pattern = null): bool {
        self::ensureCacheDir();
        
        if ($pattern) {
            $files = glob(self::$cacheDir . md5($pattern) . '*');
        } else {
            $files = glob(self::$cacheDir . '*');
        }
        
        $success = true;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $success = $success && unlink($file);
            }
        }
        
        return $success;
    }
    
    /**
     * Obtiene o almacena un valor en caché mediante callback
     *
     * @param string $key Clave de caché
     * @param callable $callback Función a ejecutar si no hay caché
     * @param int $ttl Tiempo de vida en segundos
     * @return mixed Valor de caché o resultado del callback
     */
    public static function remember(string $key, callable $callback, int $ttl = 3600) {
        $cachedData = self::get($key);
        
        if ($cachedData !== null) {
            return $cachedData;
        }
        
        $data = $callback();
        self::set($key, $data, $ttl);
        
        return $data;
    }
    
    /**
     * Verifica si una clave existe en caché
     *
     * @param string $key Clave a verificar
     * @return bool True si existe y no ha expirado
     */
    public static function has(string $key): bool {
        return self::get($key) !== null;
    }
    
    /**
     * Incrementa un valor numérico en caché
     *
     * @param string $key Clave a incrementar
     * @param int $value Cantidad a incrementar
     * @param int $ttl Tiempo de vida si se crea nuevo
     * @return int|false Nuevo valor o false en error
     */
    public static function increment(string $key, int $value = 1, int $ttl = 3600) {
        $current = self::get($key);
        
        if ($current === null) {
            self::set($key, $value, $ttl);
            return $value;
        }
        
        if (!is_numeric($current)) {
            return false;
        }
        
        $new = $current + $value;
        
        // Obtener TTL restante
        $filePath = self::getFilePath($key);
        $content = file_get_contents($filePath);
        $cache = unserialize($content);
        $ttlRemaining = $cache['expires'] > 0 ? $cache['expires'] - time() : 0;
        
        self::set($key, $new, $ttlRemaining > 0 ? $ttlRemaining : $ttl);
        
        return $new;
    }
    
    /**
     * Decrementa un valor numérico en caché
     *
     * @param string $key Clave a decrementar
     * @param int $value Cantidad a decrementar
     * @return int|false Nuevo valor o false en error
     */
    public static function decrement(string $key, int $value = 1) {
        return self::increment($key, -$value);
    }
    
    /**
     * Obtiene la ruta al archivo de caché
     *
     * @param string $key Clave de caché
     * @return string Ruta del archivo
     */
    private static function getFilePath(string $key): string {
        return self::$cacheDir . md5($key);
    }
    
    /**
     * Asegura que el directorio de caché exista
     *
     * @return void
     */
    private static function ensureCacheDir(): void {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }
    
    /**
     * Inicia el buffer de salida para caché de fragmentos
     * 
     * @param string $key Clave de caché
     * @param int $ttl Tiempo de vida en segundos
     * @return bool True si se está renderizando desde caché
     */
    public static function startFragment(string $key, int $ttl = 3600): bool {
        $cached = self::get($key);
        
        if ($cached !== null) {
            echo $cached;
            return true;
        }
        
        ob_start();
        return false;
    }
    
    /**
     * Finaliza y almacena el buffer de salida en caché
     * 
     * @param string $key Clave de caché
     * @param int $ttl Tiempo de vida en segundos
     * @return void
     */
    public static function endFragment(string $key, int $ttl = 3600): void {
        $content = ob_get_clean();
        self::set($key, $content, $ttl);
        
        echo $content;
    }
}