</main>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Sistema de Listas de Regalos</h5>
                    <p class="text-muted">
                        Crea y comparte tus listas de regalos para cualquier ocasión.
                        Bodas, cumpleaños, baby showers y más.
                    </p>
                </div>
                
                <div class="col-md-2 mb-4 mb-md-0">
                    <h5>Enlaces</h5>
                    <ul class="list-unstyled">
                        <li><a href="/" class="text-decoration-none text-muted">Inicio</a></li>
                        <li><a href="/list/create" class="text-decoration-none text-muted">Crear Lista</a></li>
                        <li><a href="/help" class="text-decoration-none text-muted">Ayuda</a></li>
                        <li><a href="/privacy" class="text-decoration-none text-muted">Privacidad</a></li>
                        <li><a href="/terms" class="text-decoration-none text-muted">Términos</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4 mb-md-0">
                    <h5>Contacto</h5>
                    <ul class="list-unstyled text-muted">
                        <li><i class="bi bi-envelope me-2"></i> contact@example.com</li>
                        <li><i class="bi bi-telephone me-2"></i> (123) 456-7890</li>
                        <li><i class="bi bi-geo-alt me-2"></i> Calle Principal #123, Ciudad</li>
                    </ul>
                </div>
                
                <div class="col-md-3">
                    <h5>Síguenos</h5>
                    <div class="d-flex gap-3 fs-4">
                        <a href="#" class="text-decoration-none text-muted">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="text-decoration-none text-muted">
                            <i class="bi bi-instagram"></i>
                        </a>
                        <a href="#" class="text-decoration-none text-muted">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" class="text-decoration-none text-muted">
                            <i class="bi bi-pinterest"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0 text-muted">
                        &copy; <?= date('Y') ?> Sistema de Listas de Regalos. Todos los derechos reservados.
                    </p>
                </div>
                
                <div class="col-md-6 text-center text-md-end">
                    <img src="/assets/img/payment-methods.png" alt="Métodos de pago" class="img-fluid" style="max-height: 30px;">
                </div>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript personalizado -->
    <script src="/assets/js/main.js"></script>
    
    <?php if (isset($extraScripts)): ?>
        <?= $extraScripts ?>
    <?php endif; ?>
    
    <!-- Notificaciones toast -->
    <?php if (isset($_SESSION['toast_message'])): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="liveToast" class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header bg-<?= $_SESSION['toast_type'] ?? 'primary' ?> text-white">
                <strong class="me-auto"><?= $_SESSION['toast_title'] ?? 'Notificación' ?></strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?= htmlspecialchars($_SESSION['toast_message']) ?>
            </div>
        </div>
    </div>
    <script>
        // Auto ocultar toast después de 5 segundos
        setTimeout(() => {
            const toast = document.getElementById('liveToast');
            const bsToast = bootstrap.Toast.getInstance(toast);
            if (bsToast) {
                bsToast.hide();
            }
        }, 5000);
    </script>
    <?php 
    // Limpiar mensajes de notificación
    unset($_SESSION['toast_message'], $_SESSION['toast_type'], $_SESSION['toast_title']);
    endif; 
    ?>
</body>
</html>