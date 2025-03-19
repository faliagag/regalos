<?php
/**
 * Página de ayuda del sistema
 */
require_once __DIR__ . '/../../core/Security/Headers.php';
require_once __DIR__ . '/../../core/Auth/SessionManager.php';

use Core\Security\Headers;
use Core\Auth\SessionManager;

// Establecer cabeceras de seguridad
Headers::setSecureHeaders();

// Iniciar sesión segura (opcional para la página de ayuda)
SessionManager::startSecureSession();

// Verificar si el usuario está autenticado
$isLoggedIn = SessionManager::isLoggedIn();

// Definir página activa y título
$pageName = 'help';
$pageTitle = 'Centro de Ayuda';

// Definir categorías de ayuda
$categories = [
    'getting-started' => 'Primeros Pasos',
    'creating-lists' => 'Crear Listas de Regalos',
    'managing-gifts' => 'Gestionar Regalos',
    'sharing' => 'Compartir Listas',
    'reservations' => 'Reservas de Regalos',
    'account' => 'Cuenta y Perfil',
    'troubleshooting' => 'Solución de Problemas'
];

// Definir artículos de ayuda por categoría
$helpArticles = [
    'getting-started' => [
        [
            'id' => 'what-is',
            'title' => '¿Qué es el Sistema de Listas de Regalos?',
            'summary' => 'Aprende cómo funciona nuestra plataforma y sus principales características.',
            'icon' => 'info-circle'
        ],
        [
            'id' => 'registration',
            'title' => 'Crear una cuenta',
            'summary' => 'Cómo registrarte y empezar a utilizar nuestro sistema.',
            'icon' => 'person-plus'
        ],
        [
            'id' => 'dashboard',
            'title' => 'Conoce tu panel de control',
            'summary' => 'Guía sobre las diferentes secciones y opciones de tu panel.',
            'icon' => 'grid'
        ]
    ],
    'creating-lists' => [
        [
            'id' => 'create-first-list',
            'title' => 'Crear tu primera lista de regalos',
            'summary' => 'Aprende a crear una lista para cualquier ocasión especial.',
            'icon' => 'card-list'
        ],
        [
            'id' => 'list-settings',
            'title' => 'Opciones de privacidad de listas',
            'summary' => 'Configura quién puede ver tus listas y cómo.',
            'icon' => 'shield-lock'
        ],
        [
            'id' => 'edit-list',
            'title' => 'Editar o eliminar listas',
            'summary' => 'Cómo modificar o eliminar una lista existente.',
            'icon' => 'pencil-square'
        ]
    ],
    'managing-gifts' => [
        [
            'id' => 'add-gifts',
            'title' => 'Añadir regalos a tu lista',
            'summary' => 'Aprende a añadir, describir y categorizar tus regalos deseados.',
            'icon' => 'gift'
        ],
        [
            'id' => 'gift-images',
            'title' => 'Añadir imágenes a los regalos',
            'summary' => 'Cómo incluir imágenes para que tus invitados identifiquen los regalos.',
            'icon' => 'image'
        ],
        [
            'id' => 'priorities',
            'title' => 'Establecer prioridades en los regalos',
            'summary' => 'Organiza tus regalos por nivel de importancia.',
            'icon' => 'sort-numeric-down'
        ]
    ],
    'sharing' => [
        [
            'id' => 'share-methods',
            'title' => 'Métodos para compartir tu lista',
            'summary' => 'Descubre las diferentes formas de compartir tu lista con amigos y familiares.',
            'icon' => 'share'
        ],
        [
            'id' => 'custom-links',
            'title' => 'Crear enlaces personalizados',
            'summary' => 'Genera enlaces únicos para compartir con diferentes grupos de personas.',
            'icon' => 'link'
        ],
        [
            'id' => 'social-sharing',
            'title' => 'Compartir en redes sociales',
            'summary' => 'Cómo compartir directamente en plataformas como WhatsApp, Facebook o correo electrónico.',
            'icon' => 'chat-square-text'
        ]
    ],
    'reservations' => [
        [
            'id' => 'reserve-gift',
            'title' => '¿Cómo reservar un regalo?',
            'summary' => 'Guía para tus invitados sobre cómo reservar regalos de tu lista.',
            'icon' => 'bookmark-check'
        ],
        [
            'id' => 'anonymous-reservation',
            'title' => 'Reservas anónimas',
            'summary' => 'Cómo funciona el sistema de reservas anónimas para sorpresas.',
            'icon' => 'incognito'
        ],
        [
            'id' => 'cancel-reservation',
            'title' => 'Cancelar una reserva',
            'summary' => 'Cómo liberar un regalo que ya no se va a comprar.',
            'icon' => 'bookmark-x'
        ]
    ],
    'account' => [
        [
            'id' => 'profile-settings',
            'title' => 'Gestionar tu perfil',
            'summary' => 'Actualiza tu información personal y preferencias.',
            'icon' => 'person-gear'
        ],
        [
            'id' => 'security',
            'title' => 'Seguridad de la cuenta',
            'summary' => 'Aprende a mantener tu cuenta segura y cambiar contraseñas.',
            'icon' => 'lock'
        ],
        [
            'id' => 'notifications',
            'title' => 'Configurar notificaciones',
            'summary' => 'Personaliza cómo y cuándo quieres recibir notificaciones.',
            'icon' => 'bell'
        ]
    ],
    'troubleshooting' => [
        [
            'id' => 'login-issues',
            'title' => 'Problemas de inicio de sesión',
            'summary' => 'Soluciones a problemas comunes al acceder a tu cuenta.',
            'icon' => 'door-closed'
        ],
        [
            'id' => 'missing-gifts',
            'title' => 'Regalos o listas desaparecidas',
            'summary' => 'Qué hacer si no encuentras tus listas o regalos.',
            'icon' => 'question-circle'
        ],
        [
            'id' => 'contact-support',
            'title' => 'Contactar con soporte',
            'summary' => 'Cómo comunicarte con nuestro equipo de ayuda.',
            'icon' => 'envelope'
        ]
    ]
];

// Obtener categoría activa
$activeCategory = $_GET['category'] ?? 'getting-started';
if (!array_key_exists($activeCategory, $categories)) {
    $activeCategory = 'getting-started';
}

// Obtener artículo activo
$activeArticle = $_GET['article'] ?? null;
$articleContent = [];

// Contenido del artículo de ejemplo (para demostración)
if ($activeArticle === 'what-is') {
    $articleContent = [
        'title' => '¿Qué es el Sistema de Listas de Regalos?',
        'content' => '<p>El Sistema de Listas de Regalos es una plataforma online que te permite crear, gestionar y compartir listas de regalos para cualquier ocasión especial como bodas, cumpleaños, baby showers, graduaciones y más.</p>
                     <p>La principal ventaja es que evita duplicados de regalos, ya que los invitados pueden ver qué regalos ya han sido reservados por otros.</p>
                     <h4>Principales características:</h4>
                     <ul>
                         <li><strong>Creación de listas personalizadas</strong> para diferentes ocasiones</li>
                         <li><strong>Gestión de privacidad</strong> de cada lista (pública, privada o protegida con contraseña)</li>
                         <li><strong>Añadir regalos</strong> con descripciones detalladas, imágenes y enlaces a tiendas</li>
                         <li><strong>Sistema de reservas</strong> para que tus invitados marquen lo que van a regalar</li>
                         <li><strong>Compartir fácilmente</strong> por WhatsApp, correo electrónico o redes sociales</li>
                         <li><strong>Estadísticas</strong> para ver la actividad de tus listas</li>
                     </ul>
                     <h4>¿Cómo funciona?</h4>
                     <ol>
                         <li>Creas una cuenta gratuita</li>
                         <li>Creas una lista para tu evento especial</li>
                         <li>Añades los regalos que te gustaría recibir</li>
                         <li>Compartes la lista con tus amigos y familiares</li>
                         <li>Ellos pueden reservar los regalos que van a comprar</li>
                         <li>Tú puedes ver el progreso y estadísticas de tu lista</li>
                     </ol>
                     <p>Este sistema es ideal para evitar regalos duplicados, asegurarte de recibir lo que realmente quieres o necesitas, y facilitar a tus invitados la elección del regalo perfecto.</p>'
    ];
} elseif ($activeArticle === 'create-first-list') {
    $articleContent = [
        'title' => 'Crear tu primera lista de regalos',
        'content' => '<p>Crear una lista de regalos en nuestra plataforma es un proceso sencillo que solo toma unos minutos. Sigue estos pasos para crear tu primera lista:</p>
                     <h4>Paso 1: Accede a la opción de creación</h4>
                     <p>Una vez que hayas iniciado sesión en tu cuenta, tienes varias formas de comenzar:</p>
                     <ul>
                         <li>Haz clic en el botón "<strong>Nueva Lista</strong>" en tu panel de control</li>
                         <li>Usa el menú de navegación y selecciona "<strong>Crear Lista</strong>"</li>
                     </ul>
                     <h4>Paso 2: Completa la información básica</h4>
                     <p>En el formulario de creación, deberás proporcionar la siguiente información:</p>
                     <ul>
                         <li><strong>Título</strong>: Nombre descriptivo para tu lista (ej. "Boda de Juan y María", "Cumpleaños de Pedro")</li>
                         <li><strong>Ocasión</strong>: Selecciona el tipo de evento (boda, cumpleaños, baby shower, etc.)</li>
                         <li><strong>Fecha del evento</strong>: Indica cuándo ocurrirá el evento (opcional)</li>
                         <li><strong>Descripción</strong>: Añade información adicional sobre tu lista o evento</li>
                     </ul>
                     <h4>Paso 3: Configura la privacidad</h4>
                     <p>Puedes elegir entre tres niveles de privacidad:</p>
                     <ul>
                         <li><strong>Pública</strong>: Cualquier persona con el enlace puede ver tu lista</li>
                         <li><strong>Privada</strong>: Solo personas invitadas específicamente pueden verla</li>
                         <li><strong>Protegida con contraseña</strong>: Se requiere una contraseña para acceder</li>
                     </ul>
                     <h4>Paso 4: Personaliza la apariencia</h4>
                     <p>Opcionalmente, puedes añadir una imagen de portada para personalizar tu lista y hacerla más atractiva.</p>
                     <h4>Paso 5: Guarda tu lista</h4>
                     <p>Haz clic en el botón "Crear Lista" para finalizar. Serás redirigido automáticamente a tu nueva lista vacía, donde podrás comenzar a añadir regalos.</p>
                     <div class="alert alert-info">
                         <strong>Consejo:</strong> Si estás creando una lista para un evento próximo, considera habilitarla con varias semanas o meses de antelación para dar tiempo a tus invitados.
                     </div>'
    ];
}

// Incluir header
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm bg-primary text-white">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="display-5 fw-bold">Centro de Ayuda</h1>
                            <p class="lead mb-3">Encuentra respuestas a todas tus dudas sobre el Sistema de Listas de Regalos</p>
                            <form class="d-flex">
                                <input class="form-control form-control-lg me-2" type="search" placeholder="Buscar ayuda..." aria-label="Buscar">
                                <button class="btn btn-light btn-lg" type="submit">Buscar</button>
                            </form>
                        </div>
                        <div class="col-md-4 d-none d-md-block text-end">
                            <i class="bi bi-question-circle" style="font-size: 8rem; opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Sidebar de categorías -->
        <div class="col-md-3 mb-4">
            <div class="list-group shadow-sm">
                <?php foreach ($categories as $id => $name): ?>
                    <a href="?category=<?= $id ?>" class="list-group-item list-group-item-action <?= $activeCategory === $id ? 'active' : '' ?>">
                        <?= htmlspecialchars($name) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Bloque de contacto -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h5 class="card-title">¿No encuentras tu respuesta?</h5>
                    <p class="card-text">Nuestro equipo de soporte está listo para ayudarte con cualquier duda.</p>
                    <a href="/contact" class="btn btn-primary w-100">
                        <i class="bi bi-envelope"></i> Contactar Soporte
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenido principal -->
        <div class="col-md-9">
            <?php if (!empty($articleContent)): ?>
                <!-- Visualización de artículo específico -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0"><?= htmlspecialchars($articleContent['title']) ?></h2>
                            <a href="?category=<?= $activeCategory ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?= $articleContent['content'] ?>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">¿Te resultó útil este artículo?</span>
                                <button class="btn btn-sm btn-outline-success ms-2">
                                    <i class="bi bi-hand-thumbs-up"></i> Sí
                                </button>
                                <button class="btn btn-sm btn-outline-danger ms-1">
                                    <i class="bi bi-hand-thumbs-down"></i> No
                                </button>
                            </div>
                            <div>
                                <button class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-printer"></i> Imprimir
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Artículos relacionados -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-light">
                        <h3 class="h5 mb-0">Artículos Relacionados</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            // Mostrar 3 artículos aleatorios de la misma categoría
                            $categoryArticles = $helpArticles[$activeCategory];
                            shuffle($categoryArticles);
                            $relatedArticles = array_slice($categoryArticles, 0, 3);
                            
                            foreach ($relatedArticles as $article):
                                if ($article['id'] !== $activeArticle):
                            ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($article['title']) ?></h5>
                                            <p class="card-text small"><?= htmlspecialchars($article['summary']) ?></p>
                                        </div>
                                        <div class="card-footer bg-white border-0">
                                            <a href="?category=<?= $activeCategory ?>&article=<?= $article['id'] ?>" class="btn btn-sm btn-outline-primary">Leer más</a>
                                        </div>
                                    </div>
                                </div>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Listado de artículos de la categoría -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h2 class="h4 mb-0"><?= htmlspecialchars($categories[$activeCategory]) ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($helpArticles[$activeCategory] as $article): ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <i class="bi bi-<?= $article['icon'] ?> text-primary" style="font-size: 2rem;"></i>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <h3 class="h5"><?= htmlspecialchars($article['title']) ?></h3>
                                                    <p class="text-muted mb-0"><?= htmlspecialchars($article['summary']) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-footer bg-white">
                                            <a href="?category=<?= $activeCategory ?>&article=<?= $article['id'] ?>" class="btn btn-sm btn-outline-primary w-100">Leer más</a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Preguntas frecuentes -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-light">
                        <h3 class="h5 mb-0">Preguntas Frecuentes</h3>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="faqAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqHeading1">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse1" aria-expanded="false" aria-controls="faqCollapse1">
                                        ¿El servicio es gratuito?
                                    </button>
                                </h2>
                                <div id="faqCollapse1" class="accordion-collapse collapse" aria-labelledby="faqHeading1" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        Sí, el servicio básico es completamente gratuito. Puedes crear listas, añadir regalos y compartirlas sin costo alguno.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqHeading2">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                                        ¿Cuántas listas puedo crear?
                                    </button>
                                </h2>
                                <div id="faqCollapse2" class="accordion-collapse collapse" aria-labelledby="faqHeading2" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No hay límite en la cantidad de listas que puedes crear. Puedes tener tantas listas activas como necesites para diferentes ocasiones.
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="faqHeading3">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                                        ¿Necesito una cuenta para reservar un regalo?
                                    </button>
                                </h2>
                                <div id="faqCollapse3" class="accordion-collapse collapse" aria-labelledby="faqHeading3" data-bs-parent="#faqAccordion">
                                    <div class="accordion-body">
                                        No, tus invitados no necesitan crear una cuenta para reservar regalos. Solo deben ingresar su nombre al hacer la reserva, o pueden hacerlo de forma anónima.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Incluir footer
require_once __DIR__ . '/../../includes/footer.php';
?>