<?php
/**
 * Consultas de socios.
 *
 * Un usuario es SOCIO si su rol tiene el permiso "membresia.ver"
 * (se decide por el catálogo de permisos, no por el nombre del rol).
 * El personal (empleados, administradores) son los demás.
 */

const SQL_ES_SOCIO = "u.rol_id IN (SELECT rp.rol_id FROM rol_permiso rp
                         JOIN permisos pe ON pe.id = rp.permiso_id
                        WHERE pe.clave = 'membresia.ver')";

/**
 * Busca socios por ID exacto o por nombre, apellido o correo.
 * $estado: 'activos' | 'baja' | 'todos'.
 * Devuelve ['socios' => [...], 'total' => n] para poder paginar.
 */
function buscar_socios(string $texto, string $estado = 'activos', int $limite = 10, int $desde = 0): array
{
    $condiciones = [SQL_ES_SOCIO];
    $params = [];

    if ($estado === 'activos') {
        $condiciones[] = 'u.activo = 1';
    } elseif ($estado === 'baja') {
        $condiciones[] = 'u.activo = 0';
    }

    $texto = trim($texto);
    if ($texto !== '') {
        if (ctype_digit($texto)) {
            $condiciones[] = '(u.id = ? OR u.telefono LIKE ?)';
            $params[] = (int) $texto;
            $params[] = '%' . $texto . '%';
        } else {
            // Cada palabra debe aparecer en el nombre completo o en el correo
            foreach (preg_split('/\s+/', $texto) as $palabra) {
                $condiciones[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.email LIKE ?)";
                $comodin = '%' . $palabra . '%';
                $params[] = $comodin;
                $params[] = $comodin;
            }
        }
    }

    $donde = implode(' AND ', $condiciones);
    $total = (int) valor("SELECT COUNT(*) FROM usuarios u WHERE $donde", $params);

    $socios = filas(
        "SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.activo, u.created_at,
                um.fecha_inicio, um.fecha_fin, um.estado, pl.nombre_plan
           FROM usuarios u
           LEFT JOIN membresias um ON um.id = (
                SELECT m2.id FROM membresias m2
                 WHERE m2.usuario_id = u.id
                 ORDER BY m2.fecha_fin DESC, m2.id DESC LIMIT 1)
           LEFT JOIN planes pl ON pl.id = um.plan_id
          WHERE $donde
          ORDER BY u.nombre, u.apellido
          LIMIT " . (int) $limite . ' OFFSET ' . (int) $desde,
        $params
    );

    foreach ($socios as &$s) {
        $s['estado_membresia'] = $s['fecha_fin'] ? estado_membresia($s) : 'sin membresía';
    }
    unset($s);

    return ['socios' => $socios, 'total' => $total];
}

/** Datos de un socio por id (o null si no existe o no es socio). */
function socio_por_id(int $id): ?array
{
    return fila('SELECT u.* FROM usuarios u WHERE u.id = ? AND ' . SQL_ES_SOCIO, [$id]);
}
