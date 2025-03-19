<?php
/**
 * Archivo de configuración principal
 * Fecha: 2025-03-18
 */
return [
    // Configuración de la base de datos
    'database' => [
        'host' => 'localhost',
        'name' => 'gift_lists',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    
    // Configuración general
    'app' => [
        'name' => 'Sistema de Listas de Regalos',
        'url' => 'http://localhost',
        'debug' => true,
        'timezone' => 'America/Mexico_City',
        'locale' => 'es',
        'secret' => '3c568b5e9a7a5c8f5c8f5c8f5c8f5c8f5c8f5c8f5c8f5c8f5c8f5c8f5c8f5c8f'
    ],
    
    // Configuración de correo
    'mail' => [
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => 'notifications@example.com',
        'password' => 'your_password',
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Sistema de Listas de Regalos',
        'encryption' => 'tls'
    ],
    
    // Configuración de subida de archivos
    'uploads' => [
        'max_size' => 2 * 1024 * 1024, // 2MB en bytes
        'allowed_images' => ['image/jpeg', 'image/png', 'image/gif'],
        'path' => '/uploads'
    ],
    
    // Configuración de sesión
    'session' => [
        'lifetime' => 3600, // 1 hora en segundos
        'remember_me' => 30 * 24 * 3600 // 30 días en segundos
    ],
    
    // Configuración de caché
    'cache' => [
        'enabled' => true,
        'lifetime' => 3600, // 1 hora en segundos
        'path' => '/cache'
    ],
    
    // Configuración de seguridad
    'security' => [
        'min_password_length' => 8,
        'password_policy' => 'medium', // options: weak, medium, strong
        'max_login_attempts' => 5,
        'lockout_time' => 15 * 60 // 15 minutos en segundos
    ],
    
    // Configuración de API
    'api' => [
        'enabled' => true,
        'rate_limit' => 100, // peticiones por hora
        'token_lifetime' => 24 * 3600 // 24 horas en segundos
    ],
    
    // Configuración de notificaciones
    'notifications' => [
        'email' => true,
        'web' => true,
        'retention_period' => 30 // días
    ],
    
    // Límites del sistema
    'limits' => [
        'max_lists_per_user' => 50,
        'max_gifts_per_list' => 200,
        'max_description_length' => 5000
    ]
];