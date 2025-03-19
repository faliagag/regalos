<?php
/**
 * Clase para validación de datos
 */
class Validator {
    /**
     * Errores de validación
     * @var array
     */
    private array $errors = [];
    
    /**
     * Datos a validar
     * @var array
     */
    private array $data;
    
    /**
     * Constructor
     *
     * @param array $data Datos a validar
     */
    public function __construct(array $data) {
        $this->data = $data;
    }
    
    /**
     * Verifica que los campos requeridos no estén vacíos
     *
     * @param array $fields Campos requeridos
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function required(array $fields, string $message = 'Este campo es obligatorio'): self {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || trim($this->data[$field]) === '') {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo sea un email válido
     *
     * @param string $field Campo a validar
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function email(string $field, string $message = 'Formato de correo electrónico inválido'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida longitud mínima de un campo
     *
     * @param string $field Campo a validar
     * @param int $length Longitud mínima
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function minLength(string $field, int $length, string $message = null): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (mb_strlen($this->data[$field]) < $length) {
                $this->errors[$field] = $message ?? "Este campo debe tener al menos {$length} caracteres";
            }
        }
        
        return $this;
    }
    
    /**
     * Valida longitud máxima de un campo
     *
     * @param string $field Campo a validar
     * @param int $length Longitud máxima
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function maxLength(string $field, int $length, string $message = null): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (mb_strlen($this->data[$field]) > $length) {
                $this->errors[$field] = $message ?? "Este campo no debe exceder {$length} caracteres";
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo sea alfanumérico
     *
     * @param string $field Campo a validar
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function alphaNumeric(string $field, string $message = 'Este campo solo debe contener letras y números'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!ctype_alnum($this->data[$field])) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo coincida con un patrón
     *
     * @param string $field Campo a validar
     * @param string $pattern Patrón de expresión regular
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function pattern(string $field, string $pattern, string $message): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!preg_match($pattern, $this->data[$field])) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo tenga un valor numérico
     *
     * @param string $field Campo a validar
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function numeric(string $field, string $message = 'Este campo debe contener solo números'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!is_numeric($this->data[$field])) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo sea una URL válida
     *
     * @param string $field Campo a validar
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function url(string $field, string $message = 'URL inválida'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo sea una fecha válida
     *
     * @param string $field Campo a validar
     * @param string $format Formato de fecha esperado
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function date(string $field, string $format = 'Y-m-d', string $message = 'Formato de fecha inválido'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $date = \DateTime::createFromFormat($format, $this->data[$field]);
            if (!$date || $date->format($format) !== $this->data[$field]) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que la fecha sea posterior a hoy
     *
     * @param string $field Campo a validar
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function dateAfterToday(string $field, string $message = 'La fecha debe ser posterior a hoy'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $date = \DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            $today = new \DateTime('today');
            
            if ($date && $date <= $today) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Valida que un campo coincida con otro campo
     *
     * @param string $field Campo a validar
     * @param string $matchField Campo con el que debe coincidir
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function match(string $field, string $matchField, string $message = 'Los campos no coinciden'): self {
        if (
            isset($this->data[$field]) && 
            isset($this->data[$matchField]) && 
            $this->data[$field] !== $this->data[$matchField]
        ) {
            $this->errors[$field] = $message;
        }
        
        return $this;
    }
    
    /**
     * Valida si un archivo subido es válido
     *
     * @param string $field Nombre del campo de archivo
     * @param array $allowedTypes Tipos MIME permitidos
     * @param int $maxSize Tamaño máximo en bytes
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function file(string $field, array $allowedTypes, int $maxSize, string $message = null): self {
        // Verificar que existe el archivo y no hay errores
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return $this;
        }
        
        // Verificar tamaño
        if ($_FILES[$field]['size'] > $maxSize) {
            $this->errors[$field] = $message ?? 'El archivo es demasiado grande';
            return $this;
        }
        
        // Verificar tipo MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES[$field]['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            $this->errors[$field] = $message ?? 'El tipo de archivo no está permitido';
        }
        
        return $this;
    }
    
    /**
     * Valida que un valor esté en un conjunto de opciones
     *
     * @param string $field Campo a validar
     * @param array $allowedValues Valores permitidos
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function inArray(string $field, array $allowedValues, string $message = 'Valor no válido'): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowedValues)) {
            $this->errors[$field] = $message;
        }
        
        return $this;
    }
    
    /**
     * Valida un valor decimal/flotante
     *
     * @param string $field Campo a validar
     * @param string $message Mensaje de error personalizado
     * @return self
     */
    public function decimal(string $field, string $message = 'Este campo debe ser un número decimal válido'): self {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!is_numeric($this->data[$field]) || !preg_match('/^\d+(\.\d+)?$/', $this->data[$field])) {
                $this->errors[$field] = $message;
            }
        }
        
        return $this;
    }
    
    /**
     * Verifica si la validación fue exitosa
     *
     * @return bool True si no hay errores
     */
    public function isValid(): bool {
        return empty($this->errors);
    }
    
    /**
     * Obtiene los errores de validación
     *
     * @return array Errores de validación
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Obtiene los datos validados
     *
     * @return array Datos validados
     */
    public function getValidData(): array {
        $validData = [];
        
        foreach ($this->data as $key => $value) {
            if (!isset($this->errors[$key])) {
                $validData[$key] = $value;
            }
        }
        
        return $validData;
    }
    
    /**
     * Obtiene el primer error
     *
     * @return string|null Primer mensaje de error o null
     */
    public function getFirstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        
        return reset($this->errors);
    }
}