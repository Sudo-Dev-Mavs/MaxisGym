<?php
/**
 * Pagos, renovaciones y mejoras de plan.
 *
 * En MaxisGym cada pago compra un periodo de membresía: registrar un pago
 * crea una fila en membresias (socio + plan + fechas) y otra en pagos
 * ligada a esa membresía. Así el historial muestra qué plan se pagó.
 *
 * Qué se le puede cobrar a un socio lo decide opciones_de_pago():
 *   - Sin membresía vigente: cualquier plan; empieza en la fecha de pago.
 *   - Vence en DIAS_POR_VENCER días o menos: renovación con cualquier plan;
 *     empieza al día siguiente de que termine la actual (no pierde días).
 *   - Le quedan más días: sólo puede MEJORAR a un plan de mayor precio. El nuevo
 *     empieza hoy, el actual se cancela y se descuenta lo que no ha usado.
 *   - Ya pagó su siguiente periodo, o ya tiene el plan más caro: no se le cobra
 *     nada. Así no se acumulan periodos pagados por error.
 */
require_once ROOT_PATH . '/app/membresias.php';

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
 * Qué se le puede cobrar hoy a un socio ($usuarioId null = todavía no se elige socio).
 * Devuelve:
 *   modo      'sin_socio' | 'inscripcion' | 'renovacion' | 'mejora' | 'bloqueado'
 *   aviso     explicación para recepción ('' si no hace falta)
 *   planes    planes que se le pueden cobrar: id, nombre, precio, duracion, credito, monto
 *   sugerido  id del plan a preseleccionar, o null
 *   mejora    en modo 'mejora': membresia_id y plan de la membresía que se reemplaza, y el crédito
 */
function opciones_de_pago(?int $usuarioId): array
{
    $planes = planes_para_venta();
    $opcion = fn(array $p, float $credito = 0.0) => [
        'id'       => (int) $p['id'],
        'nombre'   => $p['nombre_plan'],
        'precio'   => (float) $p['precio'],
        'duracion' => (int) $p['duracion_dias'],
        'credito'  => $credito,
        'monto'    => round((float) $p['precio'] - $credito, 2),
    ];
    $todos = array_map($opcion, $planes);
    $resultado = fn(string $modo, string $aviso, array $lista, ?int $sugerido = null, ?array $mejora = null) =>
        ['modo' => $modo, 'aviso' => $aviso, 'planes' => $lista, 'sugerido' => $sugerido, 'mejora' => $mejora];

    if (!$usuarioId) {
        return $resultado('sin_socio', '', $todos);
    }

    $siguiente = renovacion_pendiente($usuarioId);
    if ($siguiente) {
        return $resultado('bloqueado', 'Ya tiene pagado su siguiente periodo: ' . $siguiente['nombre_plan'] . ' del '
            . fecha($siguiente['fecha_inicio']) . ' al ' . fecha($siguiente['fecha_fin']) . '. No hace falta cobrarle otro.', []);
    }

    $vigente = membresia_vigente($usuarioId);
    if (!$vigente) {
        $ultima = ultima_membresia($usuarioId);
        $aviso = $ultima
            ? 'Su ' . $ultima['nombre_plan'] . ' venció el ' . fecha($ultima['fecha_fin']) . '. El nuevo periodo empieza en la fecha de pago.'
            : 'Todavía no tiene membresía. El plan empieza en la fecha de pago.';
        return $resultado('inscripcion', $aviso, $todos, $ultima ? (int) $ultima['plan_id'] : null);
    }

    $dias = (int) $vigente['dias_restantes'];
    $vence = fecha($vigente['fecha_fin']);
    if ($dias <= DIAS_POR_VENCER) {
        $inicio = (new DateTime($vigente['fecha_fin']))->modify('+1 day')->format('Y-m-d');
        return $resultado('renovacion', 'Su ' . $vigente['nombre_plan'] . ' ' . texto_dias($dias) . ' (' . $vence . '). '
            . 'La renovación empieza el ' . fecha($inicio) . ', así no pierde días.', $todos, (int) $vigente['plan_id']);
    }

    // Le quedan más días: no se renueva todavía, sólo se puede mejorar a un plan de mayor precio
    $abreRenovacion = fecha((new DateTime($vigente['fecha_fin']))->modify('-' . DIAS_POR_VENCER . ' days')->format('Y-m-d'));
    $mejores = array_values(array_filter($planes, fn($p) => (float) $p['precio'] > (float) $vigente['precio']));
    if (!$mejores) {
        return $resultado('bloqueado', 'Tiene el ' . $vigente['nombre_plan'] . ' vigente hasta el ' . $vence
            . ' y no hay un plan mejor. Su renovación se podrá registrar a partir del ' . $abreRenovacion . '.', []);
    }
    $credito = credito_no_usado($vigente);
    return $resultado('mejora', 'Tiene el ' . $vigente['nombre_plan'] . ' vigente hasta el ' . $vence . ' (quedan ' . $dias . ' días). '
        . 'Su renovación se habilita el ' . $abreRenovacion . '; por ahora sólo puede mejorar su plan. '
        . 'El nuevo empieza hoy y se descuenta lo que no ha usado del actual.',
        array_map(fn($p) => $opcion($p, $credito), $mejores), (int) $mejores[0]['id'],
        ['membresia_id' => (int) $vigente['id'], 'plan' => $vigente['nombre_plan'], 'credito' => $credito]);
}

/**
 * Valor de los días que le quedan a una membresía vigente, con el precio actual de su plan.
 * Ej.: Plan Básico de $300 por 30 días al que le quedan 10 → $100. Si lo pagó hoy, se descuenta completo.
 */
function credito_no_usado(array $m): float
{
    $total = max(1, (new DateTime($m['fecha_inicio']))->diff(new DateTime($m['fecha_fin']))->days);
    $quedan = min(max(0, (int) $m['dias_restantes']), $total);
    return round((float) $m['precio'] * $quedan / $total, 2);
}

/** La opción de $planId dentro de opciones_de_pago(), o null si ese plan no se le puede cobrar. */
function opcion_de_plan(array $opciones, int $planId): ?array
{
    foreach ($opciones['planes'] as $o) {
        if ($o['id'] === $planId) {
            return $o;
        }
    }
    return null;
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
 *
 * $mejora (de opciones_de_pago, modo 'mejora'): la membresía vigente se cancela, el nuevo plan
 * empieza hoy y al precio se le resta el crédito de lo que no usó.
 * Devuelve los datos del periodo creado.
 */
function crear_membresia_con_pago(int $usuarioId, array $plan, int $metodoId, string $fechaPago, int $empleadoId, ?array $mejora = null): array
{
    $credito = $mejora ? (float) $mejora['credito'] : 0.0;
    $ultimoFin = null;
    if ($mejora) {
        // El plan actual termina ayer (o hoy mismo, si empezó hoy) y queda cancelado
        consulta(
            "UPDATE membresias SET estado = 'cancelada', fecha_fin = GREATEST(fecha_inicio, CURDATE() - INTERVAL 1 DAY), updated_at = NOW()
              WHERE id = ? AND usuario_id = ?",
            [$mejora['membresia_id'], $usuarioId]
        );
        $inicio = date('Y-m-d');
    } else {
        // Si tiene un periodo vigente, el nuevo empieza al terminar el último
        $ultimoFin = valor(
            "SELECT MAX(fecha_fin) FROM membresias
              WHERE usuario_id = ? AND estado <> 'cancelada' AND fecha_fin >= ?",
            [$usuarioId, $fechaPago]
        );
        $inicio = $ultimoFin
            ? (new DateTime($ultimoFin))->modify('+1 day')->format('Y-m-d')
            : $fechaPago;
    }
    $fin = (new DateTime($inicio))->modify('+' . (int) $plan['duracion_dias'] . ' days')->format('Y-m-d');
    $monto = round((float) $plan['precio'] - $credito, 2);

    consulta(
        "INSERT INTO membresias (usuario_id, plan_id, fecha_inicio, fecha_fin, estado, created_at, updated_at)
         VALUES (?, ?, ?, ?, 'activa', NOW(), NOW())",
        [$usuarioId, $plan['id'], $inicio, $fin]
    );
    $membresiaId = (int) db()->lastInsertId();

    consulta(
        'INSERT INTO pagos (membresia_id, metodo_pago_id, registrado_por, monto, fecha_pago, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
        [$membresiaId, $metodoId, $empleadoId, $monto, $fechaPago]
    );

    return [
        'membresia_id' => $membresiaId,
        'pago_id'      => (int) db()->lastInsertId(),
        'fecha_inicio' => $inicio,
        'fecha_fin'    => $fin,
        'renovacion'   => (bool) $ultimoFin,
        'mejora'       => (bool) $mejora,
        'monto'        => $monto,
        'credito'      => $credito,
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
