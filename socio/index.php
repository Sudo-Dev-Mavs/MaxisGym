<?php
/**
 * Dashboard del socio.
 * Estado de su membresía (con aviso si está por vencer) y accesos a
 * Mi perfil, Mi membresía, Historial de pagos y Avisos del gimnasio.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/avisos.php';

$yo = requiere_permiso('membresia.ver');

$membresia = membresia_vigente((int) $yo['id']);
$ultima = $membresia ?? ultima_membresia((int) $yo['id']);
$nuevosAvisos = avisos_sin_leer($yo);
$renovacion = renovacion_pendiente((int) $yo['id']);

$progreso = 0;
if ($membresia) {
    $total = max(1, (strtotime($membresia['fecha_fin']) - strtotime($membresia['fecha_inicio'])) / 86400);
    $usados = (time() - strtotime($membresia['fecha_inicio'])) / 86400;
    $progreso = (int) max(0, min(100, round($usados / $total * 100)));
}

$titulo = 'Bienvenido, ' . $yo['nombre'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<?php if ($renovacion): ?>
    <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
        <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
        <div>Ya renovaste: tu <?= e($renovacion['nombre_plan']) ?> continúa del <?= e(fecha($renovacion['fecha_inicio'])) ?>
            al <?= e(fecha($renovacion['fecha_fin'])) ?>.</div>
    </div>
<?php elseif ($membresia && $membresia['dias_restantes'] <= 7): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
        <div>Tu membresía <?= e(texto_dias((int) $membresia['dias_restantes'])) ?> (<?= e(fecha($membresia['fecha_fin'])) ?>).
            Renuévala en recepción para no perder tu acceso.</div>
    </div>
<?php endif; ?>

<section class="estado-membresia mb-4">
    <?php if ($membresia): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="estado-etiqueta">Tu plan</p>
                <h2 class="estado-plan"><?= e($membresia['nombre_plan']) ?></h2>
            </div>
            <?= insignia_estado('activa') ?>
        </div>
        <div class="progress my-3" role="progressbar" aria-label="Tiempo usado de la membresía"
             aria-valuenow="<?= $progreso ?>" aria-valuemin="0" aria-valuemax="100" style="height: .6rem">
            <div class="progress-bar bg-warning" style="width: <?= $progreso ?>%"></div>
        </div>
        <div class="d-flex flex-wrap justify-content-between gap-2 small">
            <span>Inicio: <strong><?= e(fecha($membresia['fecha_inicio'])) ?></strong></span>
            <span>Vence: <strong><?= e(fecha($membresia['fecha_fin'])) ?></strong> (<?= e(texto_dias((int) $membresia['dias_restantes'])) ?>)</span>
        </div>
    <?php elseif ($ultima): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <p class="estado-etiqueta">Tu último plan</p>
                <h2 class="estado-plan"><?= e($ultima['nombre_plan']) ?></h2>
            </div>
            <?= insignia_estado(estado_membresia($ultima)) ?>
        </div>
        <p class="mt-3 mb-0">Venció el <?= e(fecha($ultima['fecha_fin'])) ?>. Acude a recepción para renovarla.</p>
    <?php else: ?>
        <p class="estado-etiqueta">Tu plan</p>
        <h2 class="estado-plan">Aún no tienes membresía</h2>
        <p class="mt-2 mb-0">Acude a recepción para elegir tu plan. Puedes compararlos en <a href="<?= url('planes.php') ?>">Planes y precios</a>.</p>
    <?php endif; ?>
</section>

<div class="atajos atajos-4">
    <a class="atajo" href="<?= url('cuenta/perfil.php') ?>">
        <i class="bi bi-person-circle" aria-hidden="true"></i>
        <span><strong>Mi perfil</strong>Consulta y edita tu información personal.</span>
    </a>
    <a class="atajo" href="<?= url('socio/membresia.php') ?>">
        <i class="bi bi-credit-card-2-front" aria-hidden="true"></i>
        <span><strong>Mi membresía</strong>Tu plan actual, su vigencia y sus beneficios.</span>
    </a>
    <?php if (tiene_permiso('pagos.ver_propios')): ?>
        <a class="atajo" href="<?= url('socio/pagos.php') ?>">
            <i class="bi bi-receipt" aria-hidden="true"></i>
            <span><strong>Historial de pagos</strong>Fecha, monto, método y plan de cada pago.</span>
        </a>
    <?php endif; ?>
    <a class="atajo" href="<?= url('cuenta/avisos.php') ?>">
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span><strong>Avisos del gimnasio</strong>
            <?= $nuevosAvisos ? $nuevosAvisos . ($nuevosAvisos === 1 ? ' aviso nuevo.' : ' avisos nuevos.') : 'Comunicados, cierres y novedades.' ?></span>
    </a>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
