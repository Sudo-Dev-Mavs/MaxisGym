<?php
/**
 * Reportes — permiso reportes.ver.
 * Socios activos, empleados e ingresos del periodo elegido, desglose por plan
 * y por método de pago, gráfica de ingresos de los últimos 6 meses y detalle
 * de pagos. "Generar reporte PDF" descarga lo mismo en PDF (admin/reporte_pdf.php).
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/reportes.php';
require_once ROOT_PATH . '/app/planes.php';

$yo = requiere_permiso('reportes.ver');

[$desde, $hasta, $avisoRango] = rango_reporte();
$r = datos_reporte($desde, $hasta);
$serie = ingresos_por_mes(6);

// Colores de disco de los planes activos, para identificarlos en la tabla
$colores = [];
foreach (planes_activos() as $pa) {
    $colores[$pa['id']] = $pa['color'];
}

// Escala "bonita" para el eje de la gráfica (0, 2K, 4K...)
$maximo = max(array_column($serie, 'total') ?: [0]);
// 4 divisiones con un paso "redondo" (1, 2, 2.5 o 5 por potencia de 10)
$paso = 250;
if ($maximo > 0) {
    $bruto = $maximo / 4;
    $potencia = 10 ** floor(log10($bruto));
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($m * $potencia >= $bruto) { $paso = $m * $potencia; break; }
    }
}
$tope = $paso * 4;
$compacto = fn(float $n) => $n >= 1000 ? rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . 'K' : (string) (int) $n;
$indiceMax = array_search($maximo, array_column($serie, 'total'), true);

// Accesos rápidos de periodo
$hoy = new DateTime('today');
$presets = [
    'Este mes'        => [date('Y-m-01'), $hoy->format('Y-m-d')],
    'Mes pasado'      => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'Últimos 30 días' => [date('Y-m-d', strtotime('-29 days')), $hoy->format('Y-m-d')],
    'Este año'        => [date('Y-01-01'), $hoy->format('Y-m-d')],
];

$titulo = 'Reportes';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<?php if ($avisoRango): ?><div class="alert alert-warning"><?= e($avisoRango) ?></div><?php endif; ?>

<form method="get" action="<?= url('admin/reportes.php') ?>" class="filtros-reporte mb-4">
    <div>
        <label class="form-label" for="desde">Desde</label>
        <input class="form-control" type="date" id="desde" name="desde" value="<?= e($desde) ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <div>
        <label class="form-label" for="hasta">Hasta</label>
        <input class="form-control" type="date" id="hasta" name="hasta" value="<?= e($hasta) ?>" max="<?= date('Y-m-d') ?>">
    </div>
    <button class="btn btn-outline-dark" type="submit">Ver periodo</button>
    <div class="d-flex flex-wrap gap-1">
        <?php foreach ($presets as $nombre => [$pd, $ph]): ?>
            <?php $activo = $pd === $desde && $ph === $hasta; ?>
            <a class="btn btn-sm <?= $activo ? 'btn-dark' : 'btn-light border' ?>" href="<?= url('admin/reportes.php?' . http_build_query(['desde' => $pd, 'hasta' => $ph])) ?>"
               <?= $activo ? 'aria-current="true"' : '' ?>><?= e($nombre) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-seguridad ms-lg-auto" href="<?= url('admin/reporte_pdf.php?' . http_build_query(['desde' => $desde, 'hasta' => $hasta])) ?>">
        <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>Generar reporte PDF</a>
</form>

<p class="text-body-secondary mb-3">Periodo: <strong class="text-body"><?= e(texto_rango($desde, $hasta)) ?></strong></p>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="dato"><p class="dato-etiqueta">Total de socios activos</p><p class="dato-valor"><?= $r['socios_activos'] ?></p><p class="dato-nota">con membresía vigente hoy</p></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="dato"><p class="dato-etiqueta">Total de empleados</p><p class="dato-valor"><?= $r['empleados'] ?></p><p class="dato-nota">personal activo hoy</p></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="dato"><p class="dato-etiqueta">Ingresos del periodo</p><p class="dato-valor"><?= e(dinero($r['ingresos'])) ?></p>
            <p class="dato-nota"><?= $r['num_pagos'] ?> <?= $r['num_pagos'] === 1 ? 'pago' : 'pagos' ?>, promedio <?= e(dinero($r['promedio'])) ?></p></div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="dato"><p class="dato-etiqueta">Socios nuevos</p><p class="dato-valor"><?= $r['socios_nuevos'] ?></p><p class="dato-nota">cuentas creadas en el periodo</p></div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <figure class="tarjeta h-auto mb-0">
            <figcaption class="h5 titulo mb-3">Ingresos por mes <span class="text-body-secondary fw-normal fs-6">últimos 6 meses</span></figcaption>
            <div class="grafica" style="--tope: <?= (float) $tope ?>">
                <div class="grafica-eje" aria-hidden="true">
                    <?php for ($i = 4; $i >= 0; $i--): ?>
                        <span style="bottom: <?= $i * 25 ?>%"><?= e($compacto($tope * $i / 4)) ?></span>
                    <?php endfor; ?>
                </div>
                <ul class="grafica-columnas" aria-label="Ingresos por mes">
                    <?php foreach ($serie as $i => $m): ?>
                        <?php $alto = $tope ? $m['total'] / $tope * 100 : 0;
                              $etiquetar = $i === count($serie) - 1 || $i === $indiceMax; ?>
                        <li class="columna" tabindex="0" aria-label="<?= e($m['etiqueta'] . ': ' . dinero($m['total'])) ?>">
                            <span class="columna-barra" style="height: <?= round($alto, 2) ?>%">
                                <?php if ($etiquetar && $m['total'] > 0): ?><span class="columna-valor"><?= e($compacto($m['total'])) ?></span><?php endif; ?>
                                <span class="columna-tooltip" aria-hidden="true"><?= e($m['etiqueta']) ?><br><strong><?= e(dinero($m['total'])) ?></strong></span>
                            </span>
                            <span class="columna-mes"><?= e($m['etiqueta']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </figure>
    </div>
    <div class="col-xl-6">
        <h2 class="h5">Ingresos por plan</h2>
        <div class="tarjeta-tabla mb-3">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Plan</th><th class="text-center">Pagos</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($r['por_plan'] as $f): ?>
                    <tr>
                        <td><?php if (isset($colores[$f['id']])): ?><span class="punto disco-<?= e($colores[$f['id']]) ?>" aria-hidden="true"></span><?php endif; ?><?= e($f['nombre_plan']) ?></td>
                        <td class="text-center"><?= (int) $f['pagos'] ?></td><td class="text-end fw-semibold"><?= e(dinero($f['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$r['por_plan']): ?><tr><td colspan="3" class="text-body-secondary">Sin pagos en el periodo.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <h2 class="h5">Ingresos por método de pago</h2>
        <div class="tarjeta-tabla">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Método</th><th class="text-center">Pagos</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                <?php foreach ($r['por_metodo'] as $f): ?>
                    <tr><td><?= e($f['nombre']) ?></td><td class="text-center"><?= (int) $f['pagos'] ?></td><td class="text-end fw-semibold"><?= e(dinero($f['total'])) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$r['por_metodo']): ?><tr><td colspan="3" class="text-body-secondary">Sin pagos en el periodo.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<h2 class="h5">Detalle de pagos del periodo</h2>
<div class="tarjeta-tabla">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Fecha</th><th>Socio</th><th>Plan</th><th>Método</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
            <?php foreach ($r['pagos'] as $p): ?>
                <tr><td><?= e(fecha($p['fecha_pago'])) ?></td><td><?= e($p['socio']) ?></td><td><?= e($p['nombre_plan']) ?></td>
                    <td><?= e($p['metodo']) ?></td><td class="text-end"><?= e(dinero($p['monto'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$r['pagos']): ?><tr><td colspan="5" class="text-center text-body-secondary py-4">No hubo pagos en este periodo.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($r['pagos']): ?>
                <tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-end"><?= e(dinero($r['ingresos'])) ?></th></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
