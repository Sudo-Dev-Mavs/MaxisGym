-- =====================================================================
--  MaxisGym — Datos de demostración (opcional)
--  Ejecutar DESPUÉS de maxisgym.sql:
--      mysql -u root maxisgym < datos_demo.sql
--  Las fechas son relativas al día en que se ejecuta, así siempre hay
--  membresías activas, por vencer (7, 3 y 1 día) y vencidas.
--  Contraseña de todos los usuarios de prueba: "password"
-- =====================================================================
-- Asegura que los acentos y la ñ se guarden bien sin importar la terminal
SET NAMES utf8mb4;

USE maxisgym;

SET @hash = '$2y$12$h2q0pqGxRRP2rCuVwexyJu5XwUwsh16ueVMQX9QByg12qmfS5C9Cy';
SET @hoy  = CURDATE();

-- Usuarios (ids 1-3 ya existen: admin, Laura, Juan)
INSERT INTO usuarios (id, rol_id, nombre, apellido, email, telefono, fecha_nacimiento, password, puesto, created_at, updated_at) VALUES
 (4,  2, 'Carlos',   'Ruiz',      'carlos@example.com',   '555-3333', '1992-11-08', @hash, 'Entrenador', NOW(), NOW()),
 (5,  3, 'María',    'López',     'maria@example.com',    '555-4101', '1998-02-14', @hash, NULL, NOW(), NOW()),
 (6,  3, 'Pedro',    'Sánchez',   'pedro@example.com',    '555-4102', '1985-07-30', @hash, NULL, NOW(), NOW()),
 (7,  3, 'Sofía',    'Torres',    'sofia@example.com',    '555-4103', '2001-05-21', @hash, NULL, NOW(), NOW()),
 (8,  3, 'Diego',    'Ramírez',   'diego@example.com',    '555-4104', '1996-09-03', @hash, NULL, NOW(), NOW()),
 (9,  3, 'Valeria',  'Cruz',      'valeria@example.com',  '555-4105', '1999-12-11', @hash, NULL, NOW(), NOW()),
 (10, 3, 'Andrés',   'Morales',   'andres@example.com',   '555-4106', '1990-04-17', @hash, NULL, NOW(), NOW()),
 (11, 3, 'Fernanda', 'Díaz',      'fernanda@example.com', '555-4107', '1994-08-25', @hash, NULL, NOW(), NOW()),
 (12, 3, 'Luis',     'Hernández', 'luis@example.com',     '555-4108', '2003-01-09', @hash, NULL, NOW(), NOW());

-- Membresías (plan 1 = Básico 30 días, 2 = Premium 30 días, 3 = Anual 365 días)
INSERT INTO membresias (id, usuario_id, plan_id, fecha_inicio, fecha_fin, estado, created_at, updated_at) VALUES
 (3,  5,  2, @hoy - INTERVAL 54 DAY,  @hoy - INTERVAL 24 DAY, 'vencida', NOW(), NOW()),
 (4,  5,  2, @hoy - INTERVAL 23 DAY,  @hoy + INTERVAL 7 DAY,  'activa',  NOW(), NOW()),
 (5,  6,  1, @hoy - INTERVAL 27 DAY,  @hoy + INTERVAL 3 DAY,  'activa',  NOW(), NOW()),
 (6,  7,  3, @hoy - INTERVAL 200 DAY, @hoy + INTERVAL 165 DAY,'activa',  NOW(), NOW()),
 (7,  8,  1, @hoy - INTERVAL 29 DAY,  @hoy + INTERVAL 1 DAY,  'activa',  NOW(), NOW()),
 (8,  9,  2, @hoy - INTERVAL 71 DAY,  @hoy - INTERVAL 41 DAY, 'vencida', NOW(), NOW()),
 (9,  9,  2, @hoy - INTERVAL 40 DAY,  @hoy - INTERVAL 10 DAY, 'vencida', NOW(), NOW()),
 (10, 10, 2, @hoy - INTERVAL 41 DAY,  @hoy - INTERVAL 11 DAY, 'vencida', NOW(), NOW()),
 (11, 10, 2, @hoy - INTERVAL 10 DAY,  @hoy + INTERVAL 20 DAY, 'activa',  NOW(), NOW()),
 (12, 11, 3, @hoy - INTERVAL 300 DAY, @hoy + INTERVAL 65 DAY, 'activa',  NOW(), NOW());

-- Pagos (método: 1 Efectivo, 2 Tarjeta, 3 Transferencia; registrado por Laura o Carlos)
INSERT INTO pagos (membresia_id, metodo_pago_id, registrado_por, monto, fecha_pago, created_at, updated_at) VALUES
 (3,  2, 2,  500.00, @hoy - INTERVAL 54 DAY,  NOW(), NOW()),
 (4,  2, 2,  500.00, @hoy - INTERVAL 23 DAY,  NOW(), NOW()),
 (5,  1, 4,  300.00, @hoy - INTERVAL 27 DAY,  NOW(), NOW()),
 (6,  3, 2, 4800.00, @hoy - INTERVAL 200 DAY, NOW(), NOW()),
 (7,  1, 4,  300.00, @hoy - INTERVAL 29 DAY,  NOW(), NOW()),
 (8,  2, 2,  500.00, @hoy - INTERVAL 71 DAY,  NOW(), NOW()),
 (9,  2, 4,  500.00, @hoy - INTERVAL 40 DAY,  NOW(), NOW()),
 (10, 1, 2,  500.00, @hoy - INTERVAL 41 DAY,  NOW(), NOW()),
 (11, 3, 4,  500.00, @hoy - INTERVAL 10 DAY,  NOW(), NOW()),
 (12, 3, 2, 4800.00, @hoy - INTERVAL 300 DAY, NOW(), NOW());

-- Avisos del sistema recientes
INSERT INTO anuncios (usuario_id, rol_destino_id, titulo, contenido, importante, fecha_publicacion, fecha_expiracion, created_at, updated_at) VALUES
 (2, NULL, 'Mantenimiento de regaderas',
  'Las regaderas del área de hombres estarán en mantenimiento el próximo sábado de 8:00 a 12:00.',
  TRUE,  NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 6 DAY, NOW(), NOW()),
 (1, 3, 'Nuevo horario de fin de semana',
  'A partir de este mes abrimos sábados y domingos de 7:00 a 15:00.',
  FALSE, NOW() - INTERVAL 3 DAY, NULL, NOW(), NOW()),
 (1, 2, 'Junta de personal',
  'Junta general del personal el viernes a las 14:00 en la recepción.',
  FALSE, NOW() - INTERVAL 1 DAY, NOW() + INTERVAL 4 DAY, NOW(), NOW());

-- Mensajes de contacto pendientes
INSERT INTO mensajes_contacto (usuario_id, atendido_por, nombre, apellido, email, telefono, asunto, mensaje, fecha_atencion, created_at, updated_at) VALUES
 (NULL, NULL, 'Ricardo', 'Garza', 'ricardo@example.com', '555-9001', 'Clases para principiantes',
  '¿Tienen clases guiadas para personas que nunca han entrenado?', NULL, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY),
 (5, NULL, 'María', 'López', 'maria@example.com', '555-4101', 'Congelar membresía',
  'Voy a salir de viaje dos semanas, ¿puedo pausar mi membresía?', NULL, NOW() - INTERVAL 1 DAY, NOW() - INTERVAL 1 DAY);
