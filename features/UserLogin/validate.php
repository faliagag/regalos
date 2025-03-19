<?php
/**
 * Validador de datos para inicio de sesión
 */
namespace Features\UserLogin;

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
     * Valida que los campos requeridos no estén vacíos
     *
     * @param array $fields Campos a verificar
     * @return self
     */
    public function notEmpty(array $fields): self {
        foreach ($fields as $field) {
            if (empty($this->data[$field])) {
                $this->errors[$field] = "El campo es obligatorio.";
            }
        }
        
        return $this;
    }
    
    /**
     * Valida formato de correo electrónico
     *
     * @param string $field Campo a validar
     * @return self
     */
    public function isEmail(string $field): self {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "El formato de correo electrónico no es válido.";
        }
        
        return $this;
    }
    
    /**
     * Valida longitud mínima
     *
     * @param string $field Campo a validar
     * @param int $length Longitud mínima
     * @return self
     */
    public function minLength(string $field, int $length): self {
        if (!empty($this->data[$field]) && mb_strlen($this->data[$field]) < $length) {
            $this->errors[$field] = "El campo debe tener al menos {$length} caracteres.";
        }
        
        return $this;
    }
    
    /**
     * Valida longitud máxima
     *
     * @param string $field Campo a validar
     * @param int $length Longitud máxima
     * @return self
     */
    public function maxLength(string $field, int $length): self {
        if (!empty($this->data[$field]) && mb_strlen($this->data[$field]) > $length) {
            $this->errors[$field] = "El campo no debe exceder {$length} caracteres.";
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
    
    /**
     * Valida datos de inicio de sesión
     *
     * @param array $data Datos a validar
     * @return array Resultado [isValid, errors]
     */
    public static function validateLogin(array $data): array {
        $validator = new self($data);
        
        $validator->notEmpty(['email', 'password'])
            ->isEmail('email')
            ->minLength('password', 6)
            ->maxLength('email', 100);
        
        return [
            'isValid' => $validator->isValid(),
            'errors' => $validator->getErrors()
        ];
    }
    
    /**
     * Valida campos básicos y devuelve URL de redireccionamiento en caso de error
     *
     * @param array $data Datos a validar
     * @return string|null URL de redireccionamiento en caso de error o null
     */
    public static function validateAndGetRedirect(array $data): ?string {
        // Verificar campos obligatorios
        if (empty($data['email']) || empty($data['password'])) {
            return '/login?error=empty&email=' . urlencode($data['email'] ?? '');
        }
        
        // Verificar formato de correo
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return '/login?error=invalid&email=' . urlencode($data['email']);
        }
        
        return null; // Sin errores
    }
}