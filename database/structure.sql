-- Estructura de la base de datos para el sistema de listas de regalos
-- Compatible con MySQL 5.7+

-- Eliminar tablas existentes para reinstalación limpia
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS gift_lists;
DROP TABLE IF EXISTS gifts;
DROP TABLE IF EXISTS gift_reservations;
DROP TABLE IF EXISTS list_access_codes;
DROP TABLE IF EXISTS gift_events;
DROP TABLE IF EXISTS share_events;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS login_logs;
DROP TABLE IF EXISTS remember_tokens;
SET FOREIGN_KEY_CHECKS = 1;

-- Tabla de usuarios
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    active TINYINT(1) DEFAULT 1,
    activation_token VARCHAR(64) DEFAULT NULL,
    reset_token VARCHAR(64) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    avatar_path VARCHAR(255) DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_active (active)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de listas de regalos
CREATE TABLE gift_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE,
    description TEXT,
    occasion VARCHAR(50) DEFAULT NULL,
    event_date DATE DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    privacy ENUM('public', 'private', 'password') DEFAULT 'public',
    password_hash VARCHAR(255) DEFAULT NULL,
    allow_comments TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_slug (slug),
    INDEX idx_privacy (privacy),
    INDEX idx_occasion (occasion),
    INDEX idx_created (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de regalos
CREATE TABLE gifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT NULL,
    url VARCHAR(255) DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    status ENUM('available', 'reserved', 'purchased') DEFAULT 'available',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    FOREIGN KEY (list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
    INDEX idx_list_status (list_id, status),
    INDEX idx_category (category),
    INDEX idx_price (price),
    INDEX idx_priority (priority)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de reservas de regalos
CREATE TABLE gift_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gift_id INT NOT NULL,
    list_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) DEFAULT NULL,
    message TEXT,
    is_anonymous TINYINT(1) DEFAULT 0,
    reservation_date DATETIME NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    status ENUM('active', 'cancelled', 'completed') DEFAULT 'active',
    cancellation_reason TEXT,
    cancelled_at DATETIME DEFAULT NULL,
    cancelled_by_user_id INT DEFAULT NULL,
    FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
    FOREIGN KEY (list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (cancelled_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gift (gift_id),
    INDEX idx_list (list_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de códigos de acceso a listas
CREATE TABLE list_access_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    access_code VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    FOREIGN KEY (list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
    UNIQUE KEY (access_code),
    INDEX idx_code (access_code),
    INDEX idx_list_status (list_id, status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de eventos de regalos (vistas, reservas, etc.)
CREATE TABLE gift_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gift_id INT NOT NULL,
    list_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    event_type ENUM('viewed', 'reserved', 'unreserved', 'purchased') NOT NULL,
    details JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
    FOREIGN KEY (list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_gift (gift_id),
    INDEX idx_list (list_id),
    INDEX idx_type (event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de eventos de compartir
CREATE TABLE share_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    list_id INT NOT NULL,
    user_id INT DEFAULT NULL,
    event_type ENUM('link_generated', 'social_share', 'email_share') NOT NULL,
    platform VARCHAR(50) DEFAULT NULL,
    recipient VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_list (list_id),
    INDEX idx_type (event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de notificaciones
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    data JSON DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL,
    read_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de registros de inicio de sesión
CREATE TABLE login_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    login_time DATETIME DEFAULT NULL,
    logout_time DATETIME DEFAULT NULL,
    status ENUM('success', 'failed') DEFAULT 'success',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_time (login_time),
    INDEX idx_status (status)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Tabla de tokens para "Recordarme"
CREATE TABLE remember_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    is_valid TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_valid (user_id, is_valid),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Trigger para actualizar fechas automáticamente
DELIMITER //

-- Actualizar updated_at en usuarios al modificar
CREATE TRIGGER users_update_timestamp 
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END//

-- Actualizar updated_at en listas de regalos al modificar
CREATE TRIGGER gift_lists_update_timestamp 
BEFORE UPDATE ON gift_lists
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END//

-- Actualizar updated_at en regalos al modificar
CREATE TRIGGER gifts_update_timestamp 
BEFORE UPDATE ON gifts
FOR EACH ROW
BEGIN
    SET NEW.updated_at = NOW();
END//

DELIMITER ;

-- Insertar usuario administrador de ejemplo (Contraseña: Admin123!)
INSERT INTO users (name, email, password, role, active, created_at, updated_at)
VALUES ('Administrador', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1, NOW(), NOW());

-- Insertar usuario regular de ejemplo (Contraseña: User123!)
INSERT INTO users (name, email, password, role, active, created_at, updated_at)
VALUES ('Usuario', 'usuario@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 1, NOW(), NOW());