<?php
/**
 * Pagos y renovaciones.
 *
 * En MaxisGym cada pago compra un periodo de membresía: registrar un pago
 * crea una fila en membresias (socio + plan + fechas) y otra en pagos
 * ligada a esa membresía. Así el historial muestra qué plan se pagó.
 *
 * Si el socio todavía tiene una membresía vigente, la nueva empieza el día
 * siguiente a que termine la actual (renovar antes no hace perder días).
 */

/** Métodos de pago activos del catálogo. */
function metodos_pago_activos(): array
{
    return filas('SELECT id, nombre FROM metodos_pago WHERE activo = 1 ORDER BY id');
}

/** Planes activos para elegir en recepción. */
function planes_para_venta(): array
{
    return filas('SELECT id, nombre_plan, precio, duracion_dias FROM planes WHERE activo = 1 ORDER BY precio');
}

/** Plan que aparece elegido en el formulario: el de $planId si está a la venta, si no el primero. */
function plan_elegido(array $planes, int $planId): ?array
{
    foreach ($planes as $p) {
        if ((int) $p['id'] === $planId) {
            return $p;
        }
    }
    return $planes[0] ?? null;
}

/**
 * Valida los datos de un pago. $d: plan_id, metodo_pago_id, fecha_pago.
 * El monto no se captura: siempre es el precio del plan elegido
 * (lo pone crear_membresia_con_pago), así no puede quedar mal escrito.
 * Devuelve [errores, plan] (plan = fila del plan elegido o null).
 */
function validar_pago(array $d): array
{
    $errores = [];
    $plan = fila('SELECT * FROM planes WHERE id = ? AND activo = 1', [(int) ($d['plan_id'] ?? 0)]);
    if (!$plan) {
        $errores['plan_id'] = 'Elige un plan activo.';
    }
    if (!valor('SELECT COUNT(*) FROM metodos_pago WHERE id = ? AND activo = 1', [(int) ($d['metodo_pago_id'] ?? 0)])) {
        $errores['metodo_pago_id'] = 'Elige el método de pago.';
    }
    $f = DateTime::createFromFormat('!Y-m-d', (string) ($d['fecha_pago'] ?? ''));
    if (!$f || $f->format('Y-m-d') !== $d['fecha_pago']) {
        $errores['fecha_pago'] = 'Escribe la fecha del pago.';
    } elseif ($f > new DateTime('today')) {
        $errores['fecha_pago'] = 'La fecha del pago no puede ser futura.';
    } elseif ($f < new DateTime('-60 days')) {
        $errores['fecha_pago'] = 'Sólo se pueden capturar pagos de los últimos 60 días.';
    }
    return [$errores, $plan];
}

/**
 * Crea la membresía y su pago por el precio del plan. Debe llamarse dentro de una transacción.
 * El pago guarda el precio de ese momento: si después cambia el precio del plan, el historial no cambia.
 * Devuelve los datos del periodo creado.
 */
function crear_membresia_con_pago(int $usuarioId, array $plan, int $metodoId, string $fechaPago, int $empleadoId): array
{
    // Si tiene un periodo vigente o ya pagado por adelantado, el nuevo empieza al terminar el último
    $ultimoFin = valor(
        "SELECT MAX(fecha_fin) FROM membresias
          WHERE usuario_id = ? AND estado <> 'cancelada' AND fecha_fin >= ?",
        [$usuarioId, $fechaPago]
    );
    $inicio = $ultimoFin
        ? (new DateTime($ultimoFin))->modify('+1 day')->format('Y-m-d')
        : $fechaPago;
    $fin = (new DateTime($inicio))->modify('+' . (int) $plan['duracion_dias'] . ' days')->format('Y-m-d');

    consulta(
        "INSERT INTO membresias (usuario_id, plan_id, fecha_inicio, fecha_fin, estado, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'activa', NOW(), NOW())",
        [$usuarioId, $plan['id'], $inicio, $fin]
    );
    $membresiaId = (int) db()->lastInsertId();

    consulta(
        'INSERT INTO pagos (membresia_id, metodo_pago_id, registrado_por, monto, fecha_pago, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
        [$membresiaId, $metodoId, $empleadoId, $plan['precio'], $fechaPago]
    );

    return [
        'membresia_id' => $membresiaId,
        'pago_id'      => (int) db()->lastInsertId(),
        'fecha_inicio' => $inicio,
        'fecha_fin'    => $fin,
        'renovacion'   => (bool) $ultimoFin,
    ];
}

/** Últimos pagos registrados (para la tabla de Registrar pagos). */
function pagos_recientes(int $limite = 15): array
{
    return filas(
        'SELECT pg.id, pg.monto, pg.fecha_pago, pg.created_at, mp.nombre AS metodo,
                u.id AS socio_id, u.nombre, u.apellido, pl.nombre_plan, m.fecha_inicio, m.fecha_fin,
                CONCAT_WS(\' \', e.nombre, e.apellido) AS registrado_por
           FROM pagos pg
           JOIN membresias m ON m.id = pg.membresia_id
           JOIN usuarios u ON u.id = m.usuario_id
           JOIN planes pl ON pl.id = m.plan_id
           JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
           LEFT JOIN usuarios e ON e.id = pg.registrado_por
          ORDER BY pg.fecha_pago DESC, pg.id DESC
          LIMIT ' . (int) $limite
    );
}

/** Convierte una fila de pagos_recientes() en datos listos para JSON / tabla. */
function pago_para_tabla(array $p): array
{
    return [
        'id'        => (int) $p['id'],
        'socio'     => $p['nombre'] . ' ' . $p['apellido'],
        'socio_id'  => (int) $p['socio_id'],
        'plan'      => $p['nombre_plan'],
        'periodo'   => fecha($p['fecha_inicio']) . ' al ' . fecha($p['fecha_fin']),
        'monto'     => dinero($p['monto']),
        'fecha'     => fecha($p['fecha_pago']),
        'metodo'    => $p['metodo'],
        'registro'  => $p['registrado_por'] ?? '—',
    ];
}
