<?php
/**
 * Procesamiento de carga de imágenes para listas de regalos
 */
namespace Features\GiftList\Create;

class ImageUploader {
    /**
     * Directorio de destino para subida de imágenes
     * @var string
     */
    private string $uploadDir;
    
    /**
     * Tipos MIME permitidos
     * @var array
     */
    private array $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif'
    ];
    
    /**
     * Tamaño máximo permitido en bytes (2MB)
     * @var int
     */
    private int $maxSize = 2 * 1024 * 1024;
    
    /**
     * Errores durante la subida
     * @var array
     */
    private array $errors = [];
    
    /**
     * Constructor
     * 
     * @param string $uploadDir Directorio de destino
     */
    public function __construct(string $uploadDir = '/uploads/lists') {
        // Convertir ruta relativa a absoluta
        $this->uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . ltrim($uploadDir, '/');
        
        // Asegurar que el directorio existe
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Procesa la subida de una imagen
     * 
     * @param array $file Archivo $_FILES['input_name']
     * @param string $newFilename Nombre personalizado (opcional)
     * @return string|null Ruta relativa de la imagen o null
     */
    public function upload(array $file, ?string $newFilename = null): ?string {
        // Verificar que el archivo existe y no hay errores
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $this->errors[] = "No se ha subido ningún archivo.";
            return null;
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return null;
        }
        
        // Verificar tamaño
        if ($file['size'] > $this->maxSize) {
            $this->errors[] = "El archivo excede el tamaño máximo permitido (2MB).";
            return null;
        }
        
        // Verificar tipo MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!array_key_exists($mime, $this->allowedTypes)) {
            $this->errors[] = "Tipo de archivo no permitido. Solo se aceptan imágenes JPG, PNG y GIF.";
            return null;
        }
        
        // Generar nombre único si no se proporciona uno personalizado
        if (!$newFilename) {
            $extension = $this->allowedTypes[$mime];
            $newFilename = uniqid('list_', true) . '.' . $extension;
        } else {
            // Asegurar que el nombre personalizado tenga la extensión correcta
            $pathInfo = pathinfo($newFilename);
            $extension = $this->allowedTypes[$mime];
            $newFilename = $pathInfo['filename'] . '.' . $extension;
        }
        
        // Ruta completa para el archivo
        $destination = $this->uploadDir . '/' . $newFilename;
        
        // Mover archivo subido a destino
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $this->errors[] = "Error al guardar el archivo. Verifique los permisos.";
            return null;
        }
        
        // Optimizar imagen (redimensionar si es demasiado grande)
        $this->optimizeImage($destination, $mime);
        
        // Devolver ruta relativa (para almacenar en base de datos)
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $destination);
        return $relativePath;
    }
    
    /**
     * Optimiza una imagen redimensionándola si es demasiado grande
     * 
     * @param string $filePath Ruta del archivo a optimizar
     * @param string $mime Tipo MIME de la imagen
     * @return bool Éxito de la operación
     */
    private function optimizeImage(string $filePath, string $mime): bool {
        // Verificar si GD está disponible
        if (!extension_loaded('gd')) {
            return false;
        }
        
        // Dimensiones máximas
        $maxWidth = 1200;
        $maxHeight = 1200;
        
        // Cargar imagen según su tipo
        $sourceImage = null;
        
        switch ($mime) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($filePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($filePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // Obtener dimensiones originales
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        
        // Verificar si es necesario redimensionar
        if ($width <= $maxWidth && $height <= $maxHeight) {
            imagedestroy($sourceImage);
            return true; // No es necesario redimensionar
        }
        
        // Calcular nuevas dimensiones manteniendo proporción
        if ($width > $height) {
            $newWidth = $maxWidth;
            $newHeight = ($height / $width) * $maxWidth;
        } else {
            $newHeight = $maxHeight;
            $newWidth = ($width / $height) * $maxHeight;
        }
        
        // Crear imagen redimensionada
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preservar transparencia para PNG y GIF
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Redimensionar
        imagecopyresampled(
            $newImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight, $width, $height
        );
        
        // Guardar imagen optimizada
        $success = false;
        
        switch ($mime) {
            case 'image/jpeg':
                $success = imagejpeg($newImage, $filePath, 85); // 85% de calidad
                break;
            case 'image/png':
                $success = imagepng($newImage, $filePath, 6); // Compresión 6 (0-9)
                break;
            case 'image/gif':
                $success = imagegif($newImage, $filePath);
                break;
        }
        
        // Liberar memoria
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return $success;
    }
    
    /**
     * Obtiene mensajes de error de subida de archivos
     * 
     * @param int $errorCode Código de error de subida
     * @return string Mensaje descriptivo del error
     */
    private function getUploadErrorMessage(int $errorCode): string {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "El archivo excede el tamaño máximo permitido por el servidor.";
            case UPLOAD_ERR_FORM_SIZE:
                return "El archivo excede el tamaño máximo permitido por el formulario.";
            case UPLOAD_ERR_PARTIAL:
                return "El archivo se subió parcialmente.";
            case UPLOAD_ERR_NO_FILE:
                return "No se subió ningún archivo.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Falta la carpeta temporal en el servidor.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Error al escribir el archivo en el servidor.";
            case UPLOAD_ERR_EXTENSION:
                return "Una extensión de PHP detuvo la subida del archivo.";
            default:
                return "Error desconocido en la subida del archivo.";
        }
    }
    
    /**
     * Obtiene errores de subida
     * 
     * @return array Errores durante la subida
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Verifica si hay errores
     * 
     * @return bool True si hay errores
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Elimina una imagen
     * 
     * @param string $relativePath Ruta relativa de la imagen
     * @return bool Éxito de la operación
     */
    public function deleteImage(string $relativePath): bool {
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;
        
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
}