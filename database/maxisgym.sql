-- =====================================================================
--  MaxisGym — Script de creación de la base de datos (Fase 2, v2)
--  Motor: MySQL 8
--  Ejecutar en Laragon:  mysql -u root < maxisgym.sql
--  Cambios v2 (revisión del docente):
--   * roles, permisos y metodos_pago pasan a tablas catálogo (sin ENUM)
--   * anuncios = avisos del sistema; una sola fila por aviso, dirigida por rol
--   * mensajes_contacto y password_reset_tokens quedan enlazadas con llaves foráneas
-- =====================================================================

-- Asegura que los acentos y la ñ se guarden bien sin importar la terminal
SET NAMES utf8mb4;

DROP DATABASE IF EXISTS maxisgym;
CREATE DATABASE maxisgym CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE maxisgym;

-- ---------------------------------------------------------------------
-- CATÁLOGOS
-- ---------------------------------------------------------------------
-- 1. roles: catálogo de roles del sistema (admin, empleado, socio, ...)
CREATE TABLE roles (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(40)  NOT NULL UNIQUE,
    descripcion VARCHAR(150) NULL,
    activo      BOOLEAN      NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP    NULL,
    updated_at  TIMESTAMP    NULL
) ENGINE=InnoDB;

-- 2. permisos: acciones que se pueden asignar a un rol
CREATE TABLE permisos (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave       VARCHAR(60)  NOT NULL UNIQUE,            -- p. ej. pagos.registrar
    descripcion VARCHAR(150) NULL,
    created_at  TIMESTAMP    NULL,
    updated_at  TIMESTAMP    NULL
) ENGINE=InnoDB;

-- 3. rol_permiso: qué permisos tiene cada rol (muchos a muchos)
CREATE TABLE rol_permiso (
    rol_id     BIGINT UNSIGNED NOT NULL,
    permiso_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rp_rol     FOREIGN KEY (rol_id)     REFERENCES roles(id)    ON DELETE CASCADE,
    CONSTRAINT fk_rp_permiso FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. metodos_pago: catálogo de métodos de pago (efectivo, tarjeta, ...)
CREATE TABLE metodos_pago (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(40) NOT NULL UNIQUE,
    activo     BOOLEAN     NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP   NULL,
    updated_at TIMESTAMP   NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. usuarios: administradores, empleados y socios
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id                BIGINT UNSIGNED NOT NULL,
    nombre                VARCHAR(60)  NOT NULL,
    apellido              VARCHAR(60)  NOT NULL,
    email                 VARCHAR(120) NOT NULL UNIQUE,
    telefono              VARCHAR(20)  NULL,
    fecha_nacimiento      DATE         NULL,
    password              VARCHAR(255) NOT NULL,         -- hash bcrypt
    puesto                VARCHAR(50)  NULL,             -- solo empleados (p. ej. Recepción)
    activo                BOOLEAN      NOT NULL DEFAULT TRUE,
    anuncios_vistos_hasta DATETIME     NULL,             -- último momento en que revisó los anuncios
    remember_token        VARCHAR(100) NULL,             -- opción "Recordarme"
    created_at            TIMESTAMP    NULL,
    updated_at            TIMESTAMP    NULL,
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE RESTRICT,
    INDEX idx_usuarios_nombre (nombre, apellido)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. planes + 7. beneficios + 8. plan_beneficio (muchos a muchos)
-- ---------------------------------------------------------------------
CREATE TABLE planes (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_plan   VARCHAR(60)   NOT NULL UNIQUE,
    precio        DECIMAL(10,2) NOT NULL,
    duracion_dias SMALLINT UNSIGNED NOT NULL,            -- 30 = mensual, 365 = anual
    descripcion   VARCHAR(255)  NULL,
    activo        BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP     NULL,
    updated_at    TIMESTAMP     NULL,
    CONSTRAINT chk_planes_precio   CHECK (precio >= 0),
    CONSTRAINT chk_planes_duracion CHECK (duracion_dias > 0)
) ENGINE=InnoDB;

CREATE TABLE beneficios (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descripcion VARCHAR(120) NOT NULL UNIQUE,
    created_at  TIMESTAMP    NULL,
    updated_at  TIMESTAMP    NULL
) ENGINE=InnoDB;

CREATE TABLE plan_beneficio (
    plan_id      BIGINT UNSIGNED NOT NULL,
    beneficio_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (plan_id, beneficio_id),
    CONSTRAINT fk_pb_plan      FOREIGN KEY (plan_id)      REFERENCES planes(id)     ON DELETE CASCADE,
    CONSTRAINT fk_pb_beneficio FOREIGN KEY (beneficio_id) REFERENCES beneficios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. membresias: qué plan tiene cada socio y su vigencia
-- ---------------------------------------------------------------------
CREATE TABLE membresias (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   BIGINT UNSIGNED NOT NULL,
    plan_id      BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin    DATE NOT NULL,
    estado       ENUM('activa','vencida','cancelada') NOT NULL DEFAULT 'activa',
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,
    CONSTRAINT fk_membresias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_membresias_plan    FOREIGN KEY (plan_id)    REFERENCES planes(id)   ON DELETE RESTRICT,
    CONSTRAINT chk_membresias_fechas CHECK (fecha_fin >= fecha_inicio),
    INDEX idx_membresias_vencimiento (estado, fecha_fin)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. pagos: cada pago corresponde a una membresía (y por ende a un plan)
-- ---------------------------------------------------------------------
CREATE TABLE pagos (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membresia_id   BIGINT UNSIGNED NOT NULL,
    metodo_pago_id BIGINT UNSIGNED NOT NULL,
    registrado_por BIGINT UNSIGNED NULL,                 -- empleado que lo capturó
    monto          DECIMAL(10,2) NOT NULL,
    fecha_pago     DATE NOT NULL,
    created_at     TIMESTAMP NULL,
    updated_at     TIMESTAMP NULL,
    CONSTRAINT fk_pagos_membresia FOREIGN KEY (membresia_id)   REFERENCES membresias(id)   ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_metodo    FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_empleado  FOREIGN KEY (registrado_por) REFERENCES usuarios(id)     ON DELETE SET NULL,
    CONSTRAINT chk_pagos_monto CHECK (monto > 0),
    INDEX idx_pagos_fecha (fecha_pago)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. anuncios: avisos del sistema (no publicidad)
--     Un aviso = una fila. No se copia a cada usuario: cada usuario lo
--     consulta según su rol (rol_destino_id NULL = todos los roles).
-- ---------------------------------------------------------------------
CREATE TABLE anuncios (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id        BIGINT UNSIGNED NOT NULL,          -- autor (empleado o admin)
    rol_destino_id    BIGINT UNSIGNED NULL,              -- NULL = para todos
    titulo            VARCHAR(120) NOT NULL,
    contenido         TEXT         NOT NULL,
    importante        BOOLEAN      NOT NULL DEFAULT FALSE,
    fecha_publicacion DATETIME     NOT NULL,
    fecha_expiracion  DATETIME     NULL,                 -- NULL = no expira
    created_at        TIMESTAMP    NULL,
    updated_at        TIMESTAMP    NULL,
    CONSTRAINT fk_anuncios_autor FOREIGN KEY (usuario_id)     REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_anuncios_rol   FOREIGN KEY (rol_destino_id) REFERENCES roles(id)    ON DELETE SET NULL,
    CONSTRAINT chk_anuncios_fechas CHECK (fecha_expiracion IS NULL OR fecha_expiracion > fecha_publicacion),
    INDEX idx_anuncios_destino (rol_destino_id, fecha_publicacion)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. notificaciones: avisos de vencimiento (7, 3 y 1 día antes)
-- ---------------------------------------------------------------------
CREATE TABLE notificaciones (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    membresia_id    BIGINT UNSIGNED NOT NULL,
    dias_restantes  TINYINT UNSIGNED NOT NULL,           -- 7, 3 o 1
    correo_enviado  BOOLEAN   NOT NULL DEFAULT FALSE,
    fecha_envio     DATETIME  NULL,
    atendida        BOOLEAN   NOT NULL DEFAULT FALSE,    -- el empleado ya la revisó
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    CONSTRAINT fk_notif_membresia FOREIGN KEY (membresia_id) REFERENCES membresias(id) ON DELETE CASCADE,
    CONSTRAINT uq_notif_membresia_dias UNIQUE (membresia_id, dias_restantes),
    CONSTRAINT chk_notif_dias CHECK (dias_restantes IN (1,3,7))
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13. password_reset_tokens: recuperación de contraseña (tabla estándar de Laravel)
--     Expira a los 30 min (config/auth.php) y se borra al usarse (un solo uso)
-- ---------------------------------------------------------------------
CREATE TABLE password_reset_tokens (
    email      VARCHAR(120) NOT NULL PRIMARY KEY,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NULL,
    CONSTRAINT fk_reset_usuario FOREIGN KEY (email) REFERENCES usuarios(email)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 14. mensajes_contacto: formulario de la pantalla "Contacto"
-- ---------------------------------------------------------------------
CREATE TABLE mensajes_contacto (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id     BIGINT UNSIGNED NULL,                 -- si quien escribe tiene cuenta
    atendido_por   BIGINT UNSIGNED NULL,                 -- empleado/admin que lo atendió
    nombre         VARCHAR(60)  NOT NULL,
    apellido       VARCHAR(60)  NOT NULL,
    email          VARCHAR(120) NOT NULL,
    telefono       VARCHAR(20)  NULL,
    asunto         VARCHAR(120) NOT NULL,
    mensaje        TEXT         NOT NULL,
    fecha_atencion DATETIME     NULL,
    created_at     TIMESTAMP    NULL,
    updated_at     TIMESTAMP    NULL,
    CONSTRAINT fk_contacto_usuario  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_contacto_atendido FOREIGN KEY (atendido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
--  Datos iniciales de los catálogos
-- =====================================================================
INSERT INTO roles (nombre, descripcion, created_at, updated_at) VALUES
 ('admin',    'Administrador del gimnasio', NOW(), NOW()),
 ('empleado', 'Personal del gimnasio',      NOW(), NOW()),
 ('socio',    'Cliente con membresía',      NOW(), NOW());

INSERT INTO permisos (clave, descripcion, created_at, updated_at) VALUES
 ('socios.gestionar',    'Editar y dar de baja socios',          NOW(), NOW()),
 ('empleados.gestionar', 'Alta, edición y baja de empleados',    NOW(), NOW()),
 ('planes.gestionar',    'Alta, edición y baja de planes',       NOW(), NOW()),
 ('reportes.ver',        'Consultar y exportar reportes',        NOW(), NOW()),
 ('pagos.registrar',     'Registrar pagos de socios',            NOW(), NOW()),
 ('socios.alta',         'Dar de alta socios',                   NOW(), NOW()),
 ('socios.consultar',    'Buscar información de socios',         NOW(), NOW()),
 ('anuncios.publicar',   'Publicar avisos del sistema',          NOW(), NOW()),
 ('perfil.editar',       'Consultar y editar el perfil propio',  NOW(), NOW()),
 ('membresia.ver',       'Consultar la membresía propia',        NOW(), NOW()),
 ('pagos.ver_propios',   'Consultar el historial de pagos propio', NOW(), NOW()),
 ('anuncios.ver',        'Leer avisos del sistema',              NOW(), NOW()),
 ('mensajes.atender',    'Atender mensajes de contacto',         NOW(), NOW());

INSERT INTO rol_permiso (rol_id, permiso_id) VALUES
 (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,12),(1,13),   -- admin
 (2,5),(2,6),(2,7),(2,8),(2,9),(2,12),(2,13),                           -- empleado
 (3,9),(3,10),(3,11),(3,12);                                     -- socio

INSERT INTO metodos_pago (nombre, created_at, updated_at) VALUES
 ('Efectivo', NOW(), NOW()), ('Tarjeta', NOW(), NOW()), ('Transferencia', NOW(), NOW());

-- =====================================================================
--  Datos de ejemplo (los mismos que aparecen en los bosquejos de la Fase 1)
--  Contraseña de todos los usuarios de prueba: "password"
-- =====================================================================
SET @hash = '$2y$12$h2q0pqGxRRP2rCuVwexyJu5XwUwsh16ueVMQX9QByg12qmfS5C9Cy';

INSERT INTO usuarios (rol_id, nombre, apellido, email, telefono, fecha_nacimiento, password, puesto, created_at, updated_at) VALUES
 (1, 'Admin', 'MaxisGym', 'admin@maxisgym.com', '555-0000', '1990-01-15', @hash, NULL,        NOW(), NOW()),
 (2, 'Laura', 'Gómez',    'laura@example.com',  '555-2222', '1995-06-02', @hash, 'Recepción', NOW(), NOW()),
 (3, 'Juan',  'Pérez',    'juan@example.com',   '555-1234', '2000-03-20', @hash, NULL,        NOW(), NOW());

INSERT INTO planes (nombre_plan, precio, duracion_dias, descripcion, created_at, updated_at) VALUES
 ('Plan Básico',  300.00,  30, 'Acceso al área de pesas',        NOW(), NOW()),
 ('Plan Premium', 500.00,  30, 'Acceso total y clases grupales', NOW(), NOW()),
 ('Plan Anual',  4800.00, 365, 'Plan Premium con pago anual',    NOW(), NOW());

INSERT INTO beneficios (descripcion, created_at, updated_at) VALUES
 ('Acceso al área de pesas', NOW(), NOW()),
 ('Clases grupales',         NOW(), NOW()),
 ('Uso de regaderas y lockers', NOW(), NOW());

INSERT INTO plan_beneficio (plan_id, beneficio_id) VALUES
 (1,1), (2,1), (2,2), (2,3), (3,1), (3,2), (3,3);

INSERT INTO membresias (usuario_id, plan_id, fecha_inicio, fecha_fin, estado, created_at, updated_at) VALUES
 (3, 2, '2026-09-01', '2026-09-30', 'vencida', NOW(), NOW()),
 (3, 2, '2026-10-01', '2026-10-31', 'activa',  NOW(), NOW());

INSERT INTO pagos (membresia_id, metodo_pago_id, registrado_por, monto, fecha_pago, created_at, updated_at) VALUES
 (1, 3, 2, 500.00, '2026-09-01', NOW(), NOW()),
 (2, 2, 2, 500.00, '2026-10-01', NOW(), NOW());

INSERT INTO anuncios (usuario_id, rol_destino_id, titulo, contenido, importante, fecha_publicacion, fecha_expiracion, created_at, updated_at) VALUES
 (2, NULL, 'Cierre por mantenimiento', 'El gimnasio permanecerá cerrado el domingo 18 de octubre por mantenimiento.', TRUE, '2026-10-12 09:00:00', '2026-10-19 00:00:00', NOW(), NOW()),
 (1, 2,    'Cambio de turnos',         'A partir de noviembre el turno matutino inicia a las 6:00.',                   FALSE, '2026-10-13 10:00:00', NULL, NOW(), NOW());

INSERT INTO notificaciones (membresia_id, dias_restantes, correo_enviado, fecha_envio, created_at, updated_at) VALUES
 (2, 7, TRUE, '2026-10-24 08:00:00', NOW(), NOW());

INSERT INTO mensajes_contacto (usuario_id, atendido_por, nombre, apellido, email, telefono, asunto, mensaje, fecha_atencion, created_at, updated_at) VALUES
 (NULL, 2, 'Ana', 'López', 'ana@example.com', '555-7777', 'Horarios', '¿Abren los domingos?', '2026-10-12 12:00:00', NOW(), NOW());
