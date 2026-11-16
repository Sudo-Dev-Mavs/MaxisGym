<?php
/**
 * Datos de la pantalla Reportes y de su PDF.
 * Todo se calcula con consultas sobre las tablas existentes
 * (no hay tablas de resumen que se puedan desactualizar).
 */
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/socios.php';

/**
 * Lee y valida el rango de fechas de la URL (?desde=AAAA-MM-DD&hasta=AAAA-MM-DD).
 * Por defecto: del primer día del mes actual a hoy.
 * Devuelve [desde, hasta, error|null].
 */
function rango_reporte(): array
{
    $desde = parametro('desde', date('Y-m-01'));
    $hasta = parametro('hasta', date('Y-m-d'));
    $d = DateTime::createFromFormat('!Y-m-d', $desde);
    $h = DateTime::createFromFormat('!Y-m-d', $hasta);

    if (!$d || $d->format('Y-m-d') !== $desde || !$h || $h->format('Y-m-d') !== $hasta) {
        return [date('Y-m-01'), date('Y-m-d'), 'Las fechas no son válidas; se muestra el mes actual.'];
    }
    if ($d > $h) {
        return [$hasta, $desde, 'La fecha inicial era posterior a la final; se intercambiaron.'];
    }
    return [$desde, $hasta, null];
}

/** Todos los datos del reporte para un rango. */
function datos_reporte(string $desde, string $hasta): array
{
    $rango = [$desde, $hasta];

    $r = [
        'desde' => $desde,
        'hasta' => $hasta,
        // Al día de hoy
        'socios_activos' => (int) valor(
            'SELECT COUNT(DISTINCT m.usuario_id) FROM membresias m JOIN usuarios u ON u.id = m.usuario_id
              WHERE ' . SQL_VIGENTE . ' AND u.activo = 1'
        ),
        'empleados' => (int) valor('SELECT COUNT(*) FROM usuarios u WHERE u.activo = 1 AND NOT (' . SQL_ES_SOCIO . ')'),
        // Dentro del rango
        'ingresos' => (float) valor('SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago BETWEEN ? AND ?', $rango),
        'num_pagos' => (int) valor('SELECT COUNT(*) FROM pagos WHERE fecha_pago BETWEEN ? AND ?', $rango),
        'socios_nuevos' => (int) valor(
            'SELECT COUNT(*) FROM usuarios u WHERE ' . SQL_ES_SOCIO . ' AND DATE(u.created_at) BETWEEN ? AND ?', $rango
        ),
        'por_plan' => filas(
            'SELECT pl.id, pl.nombre_plan, COUNT(*) AS pagos, SUM(pg.monto) AS total
               FROM pagos pg JOIN membresias m ON m.id = pg.membresia_id JOIN planes pl ON pl.id = m.plan_id
              WHERE pg.fecha_pago BETWEEN ? AND ?
              GROUP BY pl.id, pl.nombre_plan ORDER BY total DESC', $rango
        ),
        'por_metodo' => filas(
            'SELECT mp.nombre, COUNT(*) AS pagos, SUM(pg.monto) AS total
               FROM pagos pg JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
              WHERE pg.fecha_pago BETWEEN ? AND ?
              GROUP BY mp.id, mp.nombre ORDER BY total DESC', $rango
        ),
        'pagos' => filas(
            'SELECT pg.fecha_pago, pg.monto, mp.nombre AS metodo, pl.nombre_plan,
                    CONCAT(u.nombre, \' \', u.apellido) AS socio
               FROM pagos pg JOIN membresias m ON m.id = pg.membresia_id
               JOIN usuarios u ON u.id = m.usuario_id JOIN planes pl ON pl.id = m.plan_id
               JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
              WHERE pg.fecha_pago BETWEEN ? AND ?
              ORDER BY pg.fecha_pago DESC, pg.id DESC', $rango
        ),
    ];
    $r['promedio'] = $r['num_pagos'] ? $r['ingresos'] / $r['num_pagos'] : 0;
    return $r;
}

/** Ingresos de los últimos $meses meses (incluido el actual), para la gráfica. */
function ingresos_por_mes(int $meses = 6): array
{
    $inicio = (new DateTime('first day of this month'))->modify('-' . ($meses - 1) . ' months')->format('Y-m-d');
    $totales = [];
    foreach (filas("SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto) AS total
                      FROM pagos WHERE fecha_pago >= ? GROUP BY mes", [$inicio]) as $f) {
        $totales[$f['mes']] = (float) $f['total'];
    }
    $nombres = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $serie = [];
    $mes = new DateTime($inicio);
    for ($i = 0; $i < $meses; $i++) {
        $clave = $mes->format('Y-m');
        $serie[] = [
            'clave'    => $clave,
            'etiqueta' => $nombres[(int) $mes->format('n')] . ' ' . $mes->format('y'),
            'total'    => $totales[$clave] ?? 0.0,
        ];
        $mes->modify('+1 month');
    }
    return $serie;
}

/** Texto de un rango: "1 al 27 de octubre de 2026" o "15/09/2026 al 27/10/2026". */
function texto_rango(string $desde, string $hasta): string
{
    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
              'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $d = new DateTime($desde);
    $h = new DateTime($hasta);
    if ($d->format('Y-m') === $h->format('Y-m')) {
        return $d->format('j') . ' al ' . $h->format('j') . ' de ' . $meses[(int) $h->format('n')] . ' de ' . $h->format('Y');
    }
    return fecha($desde) . ' al ' . fecha($hasta);
}
