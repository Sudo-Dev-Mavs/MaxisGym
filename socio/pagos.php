<?php
/**
 * Historial de pagos del socio — permiso pagos.ver_propios.
 * Fecha, monto, método y plan de cada pago (el plan se obtiene a través de
 * la membresía que pagó: pagos → membresias → planes).
 */
require __DIR__ . '/../app/bootstrap.php';

$yo = requiere_permiso('pagos.ver_propios');

$pagos = filas(
    'SELECT pg.fecha_pago, pg.monto, mp.nombre AS metodo, pl.nombre_plan, m.fecha_inicio, m.fecha_fin
       FROM pagos pg
       JOIN membresias m ON m.id = pg.membresia_id
       JOIN planes pl ON pl.id = m.plan_id
       JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
      WHERE m.usuario_id = ?
      ORDER BY pg.fecha_pago DESC, pg.id DESC',
    [$yo['id']]
);
$total = array_sum(array_column($pagos, 'monto'));

$titulo = 'Historial de pagos';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <h2 class="h4 mb-0">Pagos realizados</h2>
    <?php if ($pagos): ?>
        <p class="mb-0 text-body-secondary"><?= count($pagos) ?> <?= count($pagos) === 1 ? 'pago' : 'pagos' ?>, total <strong class="text-body"><?= e(dinero($total)) ?></strong></p>
    <?php endif; ?>
</div>

<div class="tarjeta-tabla" style="max-width: 960px">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Fecha</th><th class="text-end">Monto</th><th>Método</th><th>Plan</th><th>Periodo que cubre</th></tr></thead>
            <tbody>
            <?php foreach ($pagos as $p): ?>
                <tr>
                    <td><?= e(fecha($p['fecha_pago'])) ?></td>
                    <td class="text-end fw-semibold"><?= e(dinero($p['monto'])) ?></td>
                    <td><?= e($p['metodo']) ?></td>
                    <td><?= e($p['nombre_plan']) ?></td>
                    <td><?= e(fecha($p['fecha_inicio'])) ?> al <?= e(fecha($p['fecha_fin'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pagos): ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-4">Todavía no tienes pagos registrados. Tus pagos en recepción aparecerán aquí.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
