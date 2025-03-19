<?php
/**
 * Header común para todas las páginas del sistema
 */

// Configuración requerida
require_once __DIR__ . '/../core/Security/Headers.php';
require_once __DIR__ . '/../core/Auth/SessionManager.php';

use Core\Security\Headers;
use Core\Auth\SessionManager;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Iniciar sesión segura
SessionManager::startSecureSession();

// Determinar si el usuario está autenticado
$isLoggedIn = SessionManager::isLoggedIn();
$userName = '';
$userRole = '';

if ($isLoggedIn) {
    $userName = SessionManager::get('user_name');
    $userRole = SessionManager::get('user_role');
}

// Determinar página activa
$pageName = $pageName ?? '';

// Cargar configuración
$config = require_once __DIR__ . '/../config/app.php';
$appName = $config['app']['name'] ?? 'Sistema de Listas de Regalos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= htmlspecialchars($appName) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
    <!-- Estilos personalizados -->
    <link rel="stylesheet" href="/assets/css/styles.css">
    
    <?php if (isset($extraStyles)): ?>
        <?= $extraStyles ?>
    <?php endif; ?>
</head>
<body>
    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/"><?= htmlspecialchars($appName) ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $pageName === 'home' ? 'active' : '' ?>" href="/">Inicio</a>
                    </li>
                    
                    <?php if ($isLoggedIn): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $pageName === 'dashboard' ? 'active' : '' ?>" href="/dashboard">
                                Mi Panel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $pageName === 'create_list' ? 'active' : '' ?>" href="/list/create">
                                Nueva Lista
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login">Crear Lista</a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link <?= $pageName === 'help' ? 'active' : '' ?>" href="/help">
                            Ayuda
                        </a>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-light dropdown-toggle" type="button" id="userMenuButton" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-1"></i>
                                <?= htmlspecialchars($userName) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuButton">
                                <li><a class="dropdown-item" href="/dashboard">Mi Panel</a></li>
                                <li><a class="dropdown-item" href="/profile">Mi Perfil</a></li>
                                <li><a class="dropdown-item" href="/lists">Mis Listas</a></li>
                                <?php if ($userRole === 'admin'): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="/admin">Administración</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form action="/login/logout.php" method="post" id="logoutForm">
                                        <?php
                                        require_once __DIR__ . '/../core/Security/CSRF.php';
                                        use Core\Security\CSRF;
                                        echo CSRF::tokenField();
                                        ?>
                                        <button type="submit" class="dropdown-item">Cerrar Sesión</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="/login" class="btn btn-outline-light me-2">Iniciar Sesión</a>
                        <a href="/register" class="btn btn-light">Registrarse</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Contenedor principal -->
    <main>