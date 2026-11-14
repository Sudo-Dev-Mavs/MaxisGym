<?php
/**
 * Consultas y reglas de membresías.
 *
 * Una membresía está VIGENTE si no está cancelada y hoy está entre su fecha
 * de inicio y su fecha de fin. El campo "estado" de la tabla se actualiza
 * cada día con la tarea programada, pero las pantallas usan las fechas para
 * no depender de que la tarea ya haya corrido.
 */

const SQL_VIGENTE = "m.estado <> 'cancelada' AND m.fecha_inicio <= CURDATE() AND m.fecha_fin >= CURDATE()";

/** Membresía vigente de un socio (la que vence más tarde), con su plan. */
function membresia_vigente(int $usuarioId): ?array
{
    return fila(
        'SELECT m.*, p.nombre_plan, p.precio, p.duracion_dias,
                DATEDIFF(m.fecha_fin, CURDATE()) AS dias_restantes
           FROM membresias m
           JOIN planes p ON p.id = m.plan_id
          WHERE m.usuario_id = ? AND ' . SQL_VIGENTE . '
          ORDER BY m.fecha_fin DESC
          LIMIT 1',
        [$usuarioId]
    );
}

/** Última membresía del socio aunque ya haya vencido. */
function ultima_membresia(int $usuarioId): ?array
{
    return fila(
        'SELECT m.*, p.nombre_plan, p.precio, p.duracion_dias,
                DATEDIFF(m.fecha_fin, CURDATE()) AS dias_restantes
           FROM membresias m
           JOIN planes p ON p.id = m.plan_id
          WHERE m.usuario_id = ?
          ORDER BY m.fecha_fin DESC
          LIMIT 1',
        [$usuarioId]
    );
}

/** Estado real según las fechas: 'activa', 'vencida', 'cancelada' o 'pendiente' (aún no inicia). */
function estado_membresia(array $m): string
{
    if ($m['estado'] === 'cancelada') {
        return 'cancelada';
    }
    $hoy = date('Y-m-d');
    if ($m['fecha_inicio'] > $hoy) {
        return 'pendiente';
    }
    return $m['fecha_fin'] >= $hoy ? 'activa' : 'vencida';
}

/** Insignia de Bootstrap para un estado. */
function insignia_estado(string $estado): string
{
    $clases = [
        'activa'    => 'text-bg-success',
        'vencida'   => 'text-bg-danger',
        'cancelada' => 'text-bg-secondary',
        'pendiente' => 'text-bg-info',
        'sin membresía' => 'text-bg-light border',
    ];
    return '<span class="badge ' . ($clases[$estado] ?? 'text-bg-light') . '">' . e(ucfirst($estado)) . '</span>';
}

/** Membresías vigentes que vencen dentro de los próximos $dias días. */
function membresias_por_vencer(int $dias = 7): array
{
    return filas(
        'SELECT m.id, m.fecha_fin, DATEDIFF(m.fecha_fin, CURDATE()) AS dias_restantes,
                u.id AS usuario_id, u.nombre, u.apellido, u.telefono, u.email, p.nombre_plan
           FROM membresias m
           JOIN usuarios u ON u.id = m.usuario_id
           JOIN planes p ON p.id = m.plan_id
          WHERE ' . SQL_VIGENTE . ' AND u.activo = 1
            AND m.fecha_fin <= CURDATE() + INTERVAL ? DAY
            -- si ya pagó el siguiente periodo, no está "por vencer"
            AND NOT EXISTS (SELECT 1 FROM membresias sig
                             WHERE sig.usuario_id = m.usuario_id AND sig.estado <> \'cancelada\'
                               AND sig.fecha_inicio > m.fecha_fin)
          ORDER BY m.fecha_fin ASC, u.nombre',
        [$dias]
    );
}

/** Texto amable para los días restantes. */
function texto_dias(int $dias): string
{
    if ($dias === 0) {
        return 'vence hoy';
    }
    if ($dias === 1) {
        return 'vence mañana';
    }
    return "vence en $dias días";
}

/** Siguiente periodo ya pagado (renovación por adelantado) que todavía no inicia, o null. */
function renovacion_pendiente(int $usuarioId): ?array
{
    return fila(
        "SELECT m.*, p.nombre_plan FROM membresias m JOIN planes p ON p.id = m.plan_id
          WHERE m.usuario_id = ? AND m.estado <> 'cancelada' AND m.fecha_inicio > CURDATE()
          ORDER BY m.fecha_inicio ASC LIMIT 1",
        [$usuarioId]
    );
}
