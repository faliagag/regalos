<?php
/**
 * Instalador para el sistema de listas de regalos
 */

// Iniciar sesión para controlar progreso
session_start();

// Definir paso actual
$step = $_GET['step'] ?? ($_SESSION['install_step'] ?? 1);
$_SESSION['install_step'] = $step;

// Función para convertir valores de configuración a bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

// Función para verificar requisitos del sistema
function checkSystemRequirements() {
    $requirements = [
        'php_version' => [
            'required' => '7.4.0',
            'current' => PHP_VERSION,
            'result' => version_compare(PHP_VERSION, '7.4.0', '>=')
        ],
        'extensions' => [
            'pdo' => [
                'required' => true,
                'current' => extension_loaded('pdo'),
                'result' => extension_loaded('pdo')
            ],
            'pdo_mysql' => [
                'required' => true,
                'current' => extension_loaded('pdo_mysql'),
                'result' => extension_loaded('pdo_mysql')
            ],
            'json' => [
                'required' => true,
                'current' => extension_loaded('json'),
                'result' => extension_loaded('json')
            ],
            'gd' => [
                'required' => true,
                'current' => extension_loaded('gd'),
                'result' => extension_loaded('gd')
            ],
            'mbstring' => [
                'required' => true,
                'current' => extension_loaded('mbstring'),
                'result' => extension_loaded('mbstring')
            ]
        ],
        'functions' => [
            'file_put_contents' => [
                'required' => true,
                'current' => function_exists('file_put_contents'),
                'result' => function_exists('file_put_contents')
            ],
            'password_hash' => [
                'required' => true,
                'current' => function_exists('password_hash'),
                'result' => function_exists('password_hash')
            ]
        ],
        'directories' => [
            'cache' => [
                'required' => true,
                'current' => null,
                'result' => null
            ],
            'uploads' => [
                'required' => true,
                'current' => null,
                'result' => null
            ],
            'config' => [
                'required' => true,
                'current' => null,
                'result' => null
            ]
        ],
        'php_settings' => [
            'memory_limit' => [
                'required' => '64M',
                'current' => ini_get('memory_limit'),
                'result' => null
            ],
            'post_max_size' => [
                'required' => '8M',
                'current' => ini_get('post_max_size'),
                'result' => null
            ],
            'upload_max_filesize' => [
                'required' => '8M',
                'current' => ini_get('upload_max_filesize'),
                'result' => null
            ],
            'max_execution_time' => [
                'required' => '30',
                'current' => ini_get('max_execution_time'),
                'result' => null
            ]
        ]
    ];

    // Crear y verificar directorios
    $directories = [
        '../cache' => 'cache',
        '../public/uploads' => 'uploads',
        '../config' => 'config'
    ];
    
    foreach ($directories as $path => $key) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_writable($path)) {
            chmod($path, 0755);
        }
        $requirements['directories'][$key]['current'] = is_writable($path) ? 'Escritura habilitada' : 'Sin permisos';
        $requirements['directories'][$key]['result'] = is_writable($path);
    }

    // Verificar configuraciones PHP
    $requirements['php_settings']['memory_limit']['result'] = 
        (return_bytes(ini_get('memory_limit')) >= return_bytes('64M') || ini_get('memory_limit') == '-1';
    
    $requirements['php_settings']['post_max_size']['result'] = 
        return_bytes(ini_get('post_max_size')) >= return_bytes('8M');
    
    $requirements['php_settings']['upload_max_filesize']['result'] = 
        return_bytes(ini_get('upload_max_filesize')) >= return_bytes('8M');
    
    $requirements['php_settings']['max_execution_time']['result'] = 
        (int)ini_get('max_execution_time') >= 30 || ini_get('max_execution_time') == '0';

    // Verificar si se cumplen todos los requisitos
    $allRequirementsMet = true;

    // Verificar versión PHP
    if (!$requirements['php_version']['result']) {
        $allRequirementsMet = false;
    }

    // Verificar extensiones
    foreach ($requirements['extensions'] as $extension) {
        if (!$extension['result']) {
            $allRequirementsMet = false;
            break;
        }
    }

    // Verificar funciones
    foreach ($requirements['functions'] as $function) {
        if (!$function['result']) {
            $allRequirementsMet = false;
            break;
        }
    }

    // Verificar directorios
    foreach ($requirements['directories'] as $directory) {
        if (!$directory['result']) {
            $allRequirementsMet = false;
            break;
        }
    }

    // Verificar configuraciones PHP
    foreach ($requirements['php_settings'] as $setting) {
        if (!$setting['result']) {
            $allRequirementsMet = false;
            break;
        }
    }

    return [
        'requirements' => $requirements,
        'passed' => $allRequirementsMet
    ];
}

// Resto del script permanece igual desde aquí...
// [Las funciones testDatabaseConnection, installDatabase, createConfigFile,
//  createAdminUser, finalizeInstallation y el HTML permanecen sin cambios]

/* ... El resto del código HTML y de procesamiento permanece igual ... */