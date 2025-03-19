<?php
/**
 * Componente de botones para compartir en redes sociales
 */
require_once __DIR__ . '/../../../core/Security/CSRF.php';

use Core\Security\CSRF;

/**
 * @param int $listId ID de la lista
 * @param string $title Título de la lista
 * @param string|null $currentUrl URL actual para compartir (opcional)
 * @return void
 */
function renderShareButtons(int $listId, string $title, ?string $currentUrl = null): void {
    // Obtener URL actual si no se proporciona
    if (!$currentUrl) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    // Sanitizar datos para uso en atributos
    $safeListId = htmlspecialchars($listId, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');
?>
    <div class="share-container" id="shareContainer_<?= $safeListId ?>">
        <div class="share-buttons-wrapper">
            <h4>Compartir Lista de Regalos</h4>
            
            <div class="input-group mb-3">
                <input type="text" id="shareUrl_<?= $safeListId ?>" class="form-control" 
                    value="<?= $safeUrl ?>" readonly>
                <button class="btn btn-outline-secondary copy-btn" type="button" 
                    data-clipboard-target="#shareUrl_<?= $safeListId ?>">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
            </div>
            
            <div class="share-buttons">
                <!-- WhatsApp -->
                <a href="https://wa.me/?text=<?= urlencode('¡Mira esta lista de regalos! ' . $safeTitle . ' ' . $safeUrl) ?>" 
                    target="_blank" class="btn btn-success btn-sm share-btn" title="Compartir en WhatsApp">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                
                <!-- Facebook -->
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($safeUrl) ?>" 
                    target="_blank" class="btn btn-primary btn-sm share-btn" title="Compartir en Facebook">
                    <i class="bi bi-facebook"></i> Facebook
                </a>
                
                <!-- Twitter -->
                <a href="https://twitter.com/intent/tweet?text=<?= urlencode('¡Mira esta lista de regalos! ' . $safeTitle) ?>&url=<?= urlencode($safeUrl) ?>" 
                    target="_blank" class="btn btn-info btn-sm share-btn" title="Compartir en Twitter">
                    <i class="bi bi-twitter"></i> Twitter
                </a>
                
                <!-- Email -->
                <a href="mailto:?subject=<?= urlencode('Lista de Regalos: ' . $safeTitle) ?>&body=<?= urlencode('Hola, he creado una lista de regalos. Puedes verla en: ' . $safeUrl) ?>" 
                    class="btn btn-secondary btn-sm share-btn" title="Compartir por Email">
                    <i class="bi bi-envelope"></i> Email
                </a>
            </div>
            
            <!-- Botón para generar código QR -->
            <button type="button" class="btn btn-outline-dark mt-3 generate-qr-btn" 
                data-list-id="<?= $safeListId ?>" data-url="<?= $safeUrl ?>">
                <i class="bi bi-qr-code"></i> Generar Código QR
            </button>
            
            <!-- Contenedor para el código QR -->
            <div id="qrCode_<?= $safeListId ?>" class="qr-code-container mt-3 d-none">
                <div class="qr-code"></div>
                <a href="#" class="btn btn-sm btn-outline-primary download-qr mt-2">
                    <i class="bi bi-download"></i> Descargar QR
                </a>
            </div>
            
            <!-- Generar enlaces personalizados -->
            <hr>
            <div class="custom-link-generator">
                <h5>Enlace personalizado</h5>
                <p class="text-muted small">Puedes generar un enlace único para compartir esta lista.</p>
                
                <form id="generateLinkForm_<?= $safeListId ?>" class="generate-link-form">
                    <?= CSRF::tokenField() ?>
                    <input type="hidden" name="list_id" value="<?= $safeListId ?>">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-link-45deg"></i> Generar Enlace Único
                    </button>
                </form>
                
                <!-- Resultado del enlace generado -->
                <div id="customLinkResult_<?= $safeListId ?>" class="custom-link-result mt-3 d-none">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Enlace Generado</h6>
                            <div class="input-group mb-2">
                                <input type="text" id="customUrl_<?= $safeListId ?>" class="form-control custom-url" readonly>
                                <button class="btn btn-outline-secondary copy-custom-btn" type="button">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                            
                            <div class="code-container">
                                <span class="small text-muted">Código de acceso:</span>
                                <span class="access-code fw-bold"></span>
                            </div>
                            
                            <div class="password-note mt-2 small text-danger d-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar clipboard para botones de copia
        const copyButtons = document.querySelectorAll('.copy-btn');
        copyButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const target = document.querySelector(this.dataset.clipboardTarget);
                target.select();
                document.execCommand('copy');
                
                // Cambiar texto temporalmente para feedback
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="bi bi-check2"></i> Copiado';
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                }, 2000);
            });
        });
        
        // Generación de código QR
        const qrButtons = document.querySelectorAll('.generate-qr-btn');
        qrButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const listId = this.dataset.listId;
                const url = this.dataset.url;
                const qrContainer = document.querySelector(`#qrCode_${listId}`);
                const qrElement = qrContainer.querySelector('.qr-code');
                
                // Mostrar contenedor
                qrContainer.classList.remove('d-none');
                
                // Generar QR usando API externa
                const qrImageUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
                qrElement.innerHTML = `<img src="${qrImageUrl}" alt="Código QR" class="img-fluid">`;
                
                // Configurar botón de descarga
                const downloadBtn = qrContainer.querySelector('.download-qr');
                downloadBtn.href = qrImageUrl;
                downloadBtn.download = `lista-regalos-qr-${listId}.png`;
            });
        });
        
        // Formulario para generar enlaces personalizados
        const generateForms = document.querySelectorAll('.generate-link-form');
        generateForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const listId = formData.get('list_id');
                const resultContainer = document.querySelector(`#customLinkResult_${listId}`);
                
                // Mostrar indicador de carga
                this.querySelector('button').innerHTML = '<i class="bi bi-hourglass-split"></i> Generando...';
                this.querySelector('button').disabled = true;
                
                // Enviar petición AJAX
                fetch('/api/v1/share/generate_link.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Restaurar botón
                    this.querySelector('button').innerHTML = '<i class="bi bi-link-45deg"></i> Generar Enlace Único';
                    this.querySelector('button').disabled = false;
                    
                    if (data.success) {
                        // Mostrar resultados
                        resultContainer.classList.remove('d-none');
                        
                        // Actualizar campos
                        const urlInput = resultContainer.querySelector('.custom-url');
                        urlInput.value = data.data.url;
                        
                        const accessCode = resultContainer.querySelector('.access-code');
                        accessCode.textContent = data.data.access_code;
                        
                        // Mostrar nota de contraseña si es necesario
                        const passwordNote = resultContainer.querySelector('.password-note');
                        if (data.data.password_note) {
                            passwordNote.textContent = data.data.password_note;
                            passwordNote.classList.remove('d-none');
                        } else {
                            passwordNote.classList.add('d-none');
                        }
                        
                        // Configurar botón de copia
                        const copyBtn = resultContainer.querySelector('.copy-custom-btn');
                        copyBtn.addEventListener('click', function() {
                            urlInput.select();
                            document.execCommand('copy');
                            
                            // Feedback
                            this.innerHTML = '<i class="bi bi-check2"></i>';
                            setTimeout(() => {
                                this.innerHTML = '<i class="bi bi-clipboard"></i>';
                            }, 2000);
                        });
                    } else {
                        alert('Error: ' + (data.error || 'No se pudo generar el enlace'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al procesar la solicitud');
                    
                    // Restaurar botón
                    this.querySelector('button').innerHTML = '<i class="bi bi-link-45deg"></i> Generar Enlace Único';
                    this.querySelector('button').disabled = false;
                });
            });
        });
    });
    </script>
<?php
}