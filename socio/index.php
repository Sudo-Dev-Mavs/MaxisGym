<?php
/**
 * Dashboard del socio.
 * Lo primero que ve es cuántos días le quedan (el disco de su plan como anillo),
 * luego su último pago y los avisos más recientes del gimnasio.
 * Los accesos a cada sección quedan en el menú lateral.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/avisos.php';
require_once ROOT_PATH . '/app/componentes.php';

$yo = requiere_permiso('membresia.ver');

$membresia = membresia_vigente((int) $yo['id']);
$ultima = $membresia ?? ultima_membresia((int) $yo['id']);
$nuevosAvisos = avisos_sin_leer($yo);
$renovacion = renovacion_pendiente((int) $yo['id']);
$avisos = tiene_permiso('anuncios.ver') ? avisos_para($yo, 3) : [];

$ultimoPago = tiene_permiso('pagos.ver_propios') ? fila(
    'SELECT pg.fecha_pago, pg.monto, mp.nombre AS metodo, pl.nombre_plan
       FROM pagos pg
       JOIN membresias m ON m.id = pg.membresia_id
       JOIN planes pl ON pl.id = m.plan_id
       JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
      WHERE m.usuario_id = ?
      ORDER BY pg.fecha_pago DESC, pg.id DESC
      LIMIT 1',
    [$yo['id']]
) : null;

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
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div class="flex-grow-1">Tu membresía <?= e(texto_dias((int) $membresia['dias_restantes'])) ?> (<?= e(fecha($membresia['fecha_fin'])) ?>).
            Renuévala en recepción para no perder tu acceso.</div>
        <a class="btn btn-sm btn-dark" href="<?= url('socio/membresia.php') ?>">Pedir renovación</a>
    </div>
<?php endif; ?>

<section class="estado-membresia mb-4" aria-labelledby="titulo-plan">
    <?php if ($membresia): ?>
        <?php $dias = (int) $membresia['dias_restantes']; ?>
        <div class="socio-hero">
            <?= disco_progreso($dias, (int) $membresia['duracion_dias'], color_de_plan((int) $membresia['plan_id']),
                'Te quedan ' . $dias . ($dias === 1 ? ' día' : ' días') . ' de membresía') ?>
            <div class="socio-hero-texto">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <p class="estado-etiqueta">Tu plan</p>
                        <h2 class="estado-plan" id="titulo-plan"><?= e($membresia['nombre_plan']) ?></h2>
                    </div>
                    <?= insignia_estado('activa') ?>
                </div>
                <dl class="socio-fechas">
                    <div><dt>Inicio</dt><dd><?= e(fecha($membresia['fecha_inicio'])) ?></dd></div>
                    <div><dt>Vence</dt><dd><?= e(fecha($membresia['fecha_fin'])) ?></dd></div>
                    <div><dt>Estado</dt><dd><?= e(ucfirst(texto_dias($dias))) ?></dd></div>
                </dl>
            </div>
        </div>
    <?php elseif ($ultima): ?>
        <div class="socio-hero">
            <?= disco_progreso(0, 1, color_de_plan((int) $ultima['plan_id']), 'Tu membresía está vencida') ?>
            <div class="socio-hero-texto">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <p class="estado-etiqueta">Tu último plan</p>
                        <h2 class="estado-plan" id="titulo-plan"><?= e($ultima['nombre_plan']) ?></h2>
                    </div>
                    <?= insignia_estado(estado_membresia($ultima)) ?>
                </div>
                <p class="mt-3 mb-3">Venció el <?= e(fecha($ultima['fecha_fin'])) ?>. Acude a recepción para renovarla o pídela desde aquí.</p>
                <a class="btn btn-seguridad" href="<?= url('socio/membresia.php') ?>">Pedir renovación</a>
            </div>
        </div>
    <?php else: ?>
        <p class="estado-etiqueta">Tu plan</p>
        <h2 class="estado-plan" id="titulo-plan">Aún no tienes membresía</h2>
        <p class="mt-2 mb-0">Acude a recepción para elegir tu plan. Puedes compararlos en <a href="<?= url('planes.php') ?>">Planes y precios</a>.</p>
    <?php endif; ?>
</section>

<div class="row g-3">
    <?php if (tiene_permiso('pagos.ver_propios')): ?>
    <div class="col-lg-5">
        <section class="tarjeta" aria-labelledby="titulo-pago">
            <div class="tarjeta-cabeza">
                <h2 id="titulo-pago">Último pago</h2>
                <a href="<?= url('socio/pagos.php') ?>">Ver historial</a>
            </div>
            <?php if ($ultimoPago): ?>
                <p class="pago-monto mb-2"><?= e(dinero($ultimoPago['monto'])) ?></p>
                <ul class="lista-simple">
                    <li><span>Fecha</span><strong><?= e(fecha($ultimoPago['fecha_pago'])) ?></strong></li>
                    <li><span>Método</span><strong><?= e($ultimoPago['metodo']) ?></strong></li>
                    <li><span>Plan</span><strong><?= e($ultimoPago['nombre_plan']) ?></strong></li>
                </ul>
            <?php else: ?>
                <p class="text-body-secondary mb-0">Todavía no hay pagos registrados.</p>
            <?php endif; ?>
        </section>
    </div>
    <?php endif; ?>
    <div class="col-lg-7">
        <section class="tarjeta" aria-labelledby="titulo-avisos">
            <div class="tarjeta-cabeza">
                <h2 id="titulo-avisos">Avisos del gimnasio
                    <?php if ($nuevosAvisos): ?><span class="badge rounded-pill text-bg-warning align-middle fs-6"><?= $nuevosAvisos ?><span class="visually-hidden"> sin leer</span></span><?php endif; ?></h2>
                <a href="<?= url('cuenta/avisos.php') ?>">Ver todos</a>
            </div>
            <?php if ($avisos): ?>
                <ul class="lista-simple">
                    <?php foreach ($avisos as $a): ?>
                        <li>
                            <span>
                                <?php if ($a['importante']): ?><i class="bi bi-exclamation-circle-fill text-danger me-1" aria-hidden="true"></i><span class="visually-hidden">Importante: </span><?php endif; ?>
                                <strong><?= e($a['titulo']) ?></strong>
                            </span>
                            <span class="meta"><?= e(fecha($a['fecha_publicacion'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-body-secondary mb-0">No hay avisos por ahora.</p>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
