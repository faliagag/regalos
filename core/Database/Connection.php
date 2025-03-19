<?php
/**
 * Clase Singleton para manejo de conexión a base de datos MySQL
 */
namespace Core\Database;

class Connection {
    /**
     * Instancia única de PDO
     * @var \PDO|null
     */
    private static ?\PDO $instance = null;
    
    /**
     * Configuración de la base de datos
     * @var array
     */
    private static array $config = [
        'host' => 'localhost',
        'dbname' => 'gift_lists',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ];
    
    /**
     * Constructor privado para prevenir instanciación directa
     */
    private function __construct() {
        // Clase Singleton, no se permite instanciación
    }
    
    /**
     * Evitar la clonación del objeto
     */
    private function __clone() {
        // Clase Singleton, no se permite clonación
    }
    
    /**
     * Obtiene la instancia única de la conexión
     *
     * @return \PDO Instancia de PDO
     * @throws \Exception Si hay un error en la conexión
     */
    public static function getInstance(): \PDO {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    self::$config['host'],
                    self::$config['dbname'],
                    self::$config['charset']
                );
                
                self::$instance = new \PDO(
                    $dsn,
                    self::$config['username'],
                    self::$config['password'],
                    self::$config['options']
                );
            } catch (\PDOException $e) {
                // Registrar error sin exponer detalles sensibles
                error_log('Error de conexión a la base de datos: ' . $e->getMessage());
                throw new \Exception('Error al conectar con la base de datos');
            }
        }
        
        return self::$instance;
    }
    
    /**
     * Establece la configuración de la base de datos
     *
     * @param array $config Configuración de conexión
     * @return void
     */
    public static function setConfig(array $config): void {
        self::$config = array_merge(self::$config, $config);
        self::$instance = null; // Reset instance to apply new config
    }
    
    /**
     * Cierra la conexión actual a la base de datos
     *
     * @return void
     */
    public static function close(): void {
        self::$instance = null;
    }
}