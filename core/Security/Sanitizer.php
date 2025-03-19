<?php
/**
 * Clase para la limpieza y sanitización de datos de entrada
 */
namespace Core\Security;

class Sanitizer {
    /**
     * Limpia los datos de entrada para prevenir XSS y otros ataques
     *
     * @param mixed $data Datos a limpiar (string, array, etc)
     * @return mixed Datos limpios del mismo tipo que la entrada
     */
    public static function cleanInput($data) {
        if (is_array($data)) {
            $cleanData = [];
            foreach ($data as $key => $value) {
                // Sanitizar claves y valores recursivamente
                $cleanKey = self::cleanString($key);
                $cleanData[$cleanKey] = self::cleanInput($value);
            }
            return $cleanData;
        } else {
            return self::cleanString($data);
        }
    }
    
    /**
     * Sanitiza una cadena para prevenir XSS
     *
     * @param mixed $input String a sanitizar
     * @return string String sanitizado
     */
    private static function cleanString($input): string {
        if (is_string($input)) {
            // Eliminar espacios en blanco al inicio y final
            $input = trim($input);
            // Convertir caracteres especiales a entidades HTML
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        } else if ($input === null) {
            return '';
        } else {
            // Convertir a string si no lo es
            $input = (string) $input;
        }
        
        return $input;
    }
    
    /**
     * Limpia y filtra un correo electrónico
     *
     * @param string $email Email a sanitizar
     * @return string Email sanitizado
     */
    public static function cleanEmail(?string $email): string {
        if (!$email) {
            return '';
        }
        
        $email = trim(strtolower($email));
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        return $email;
    }
    
    /**
     * Sanitiza datos para ser usados en una consulta SQL
     *
     * @param string $string Texto a sanitizar para SQL
     * @return string Texto sanitizado para SQL
     */
    public static function cleanForSQL(?string $string): string {
        if (!$string) {
            return '';
        }
        
        // Nota: Esta función no reemplaza prepared statements, 
        // solo proporciona una capa adicional de seguridad
        $string = trim($string);
        $string = str_replace(['\\', "\0", "'", '"', "\x1a"], ['\\\\', '\\0', "\\'", '\\"', '\\Z'], $string);
        
        return $string;
    }
}