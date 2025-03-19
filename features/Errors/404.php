<?php
/**
 * Página de error 404 - No encontrado
 */
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';

use Core\Security\Headers;
use Core\Auth\SessionManager;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Establecer cabecera HTTP 404
http_response_code(404);

// Iniciar sesión segura
SessionManager::startSecureSession();

// Definir variables para el header
$pageTitle = 'Página no encontrada';
$pageName = '';

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-6 offset-md-3 text-center">
            <div class="error-container">
                <h1 class="display-1 fw-bold text-primary">404</h1>
                <div class="mb-4 lead">
                    Oops! Página no encontrada.
                </div>
                <div class="mb-5">
                    <p>Lo sentimos, pero la página que buscas no existe o ha sido movida.</p>
                    <p>Puedes intentar una de las siguientes opciones:</p>
                </div>
                
                <div class="row mb-4">
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="bi bi-house-door text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h5>Volver al inicio</h5>
                                <p class="small text-muted">Regresa a la página principal del sitio.</p>
                                <a href="/" class="btn btn-primary btn-sm">Ir al inicio</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="bi bi-search text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h5>Búsqueda</h5>
                                <p class="small text-muted">Busca lo que necesitas en nuestro sitio.</p>
                                <a href="/search" class="btn btn-outline-primary btn-sm">Buscar</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="bi bi-card-list text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h5>Explorar listas</h5>
                                <p class="small text-muted">Descubre listas populares de regalos.</p>
                                <a href="/explore" class="btn btn-outline-primary btn-sm">Explorar</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="mb-3">
                                    <i class="bi bi-question-circle text-primary" style="font-size: 2rem;"></i>
                                </div>
                                <h5>Ayuda</h5>
                                <p class="small text-muted">Consulta nuestras guías y soporte.</p>
                                <a href="/help" class="btn btn-outline-primary btn-sm">Centro de ayuda</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>