-- =============================================================
-- MaxisGym · Actualización 001: permiso para atender mensajes de contacto
-- Sólo hace falta si cargaste database/maxisgym.sql ANTES de esta versión
-- (una instalación nueva ya lo incluye). Se puede ejecutar más de una vez.
--   mysql -u root maxisgym < database/actualizaciones/001_permiso_mensajes.sql
-- No cambia la estructura: sólo agrega una fila al catálogo de permisos y la
-- asigna a los roles admin y empleado.
-- =============================================================
SET NAMES utf8mb4;

INSERT IGNORE INTO permisos (clave, descripcion, created_at, updated_at)
VALUES ('mensajes.atender', 'Atender mensajes de contacto', NOW(), NOW());

INSERT IGNORE INTO rol_permiso (rol_id, permiso_id)
SELECT r.id, p.id FROM roles r JOIN permisos p ON p.clave = 'mensajes.atender'
 WHERE r.nombre IN ('admin', 'empleado');
