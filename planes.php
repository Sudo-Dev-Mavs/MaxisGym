<?php
/**
 * Pantalla de Planes y Precios (información dinámica desde la base de datos).
 * Cada plan se muestra como tarjeta con su disco olímpico y sus beneficios, y abajo una
 * tabla comparativa armada con las tablas planes, beneficios y plan_beneficio.
 */
require __DIR__ . '/app/bootstrap.php';
require ROOT_PATH . '/app/planes.php';
require_once ROOT_PATH . '/app/componentes.php';

$planes = planes_activos();
$beneficios = beneficios_de_planes_activos();
$g = config('gimnasio');

// Para la tabla: ¿el plan X incluye el beneficio Y?
$incluye = [];
foreach ($planes as $plan) {
    foreach ($plan['beneficios'] as $b) {
        $incluye[$plan['id']][$b['id']] = true;
    }
}

$titulo = 'Planes y precios · MaxisGym';
$activo = 'planes';
$navSobreFoto = true;
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>

<?= encabezado_pagina('Planes y precios',
    'Elige el que va con tu ritmo. Te inscribes en recepción y desde ese día tu panel muestra tu membresía.',
    'img/sitio/discos.jpg', 'Pilas de discos olímpicos de colores sobre piso de hule', '50% 62%') ?>

<section class="seccion seccion-clara" aria-labelledby="titulo-planes">
    <div class="envoltura">
        <?php if (!$planes): ?>
            <p class="entrada">Por el momento no hay planes publicados. Pregunta en recepción.</p>
        <?php else: ?>
            <div class="seccion-cabeza">
                <h2 class="titulo-l" id="titulo-planes">Elige tu disco</h2>
                <p class="entrada">Entre más pesado el disco, más incluye el plan.</p>
            </div>
            <div class="planes-rejilla">
                <?php foreach ($planes as $plan): ?>
                    <?= tarjeta_plan($plan) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($planes && $beneficios): ?>
<section class="seccion seccion-clara pt-0" id="comparar" aria-labelledby="titulo-comparar">
    <div class="envoltura">
        <div class="seccion-cabeza">
            <h2 class="titulo-l" id="titulo-comparar">Comparar planes</h2>
            <p class="entrada">Precio, duración y lo que incluye cada plan.</p>
        </div>
        <div class="tabla-envoltura">
            <div class="table-responsive">
                <table class="table tabla-comparativa">
                    <thead>
                        <tr>
                            <th scope="col">Incluye</th>
                            <?php foreach ($planes as $plan): ?>
                                <th scope="col" class="text-center"><?= e($plan['nombre_plan']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">Precio</th>
                            <?php foreach ($planes as $plan): ?>
                                <td class="text-center fw-semibold text-nowrap">
                                    <span class="marca-plan" style="--color: var(--disco-<?= e($plan['color']) ?>)" aria-hidden="true"></span><?= e(dinero($plan['precio'])) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <th scope="row">Duración</th>
                            <?php foreach ($planes as $plan): ?>
                                <td class="text-center"><?= e(duracion_texto((int) $plan['duracion_dias'])) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php foreach ($beneficios as $b): ?>
                            <tr>
                                <th scope="row"><?= e($b['descripcion']) ?></th>
                                <?php foreach ($planes as $plan): ?>
                                    <?php if (!empty($incluye[$plan['id']][$b['id']])): ?>
                                        <td class="text-center si"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span class="visually-hidden">Sí</span></td>
                                    <?php else: ?>
                                        <td class="text-center no"><i class="bi bi-dash-lg" aria-hidden="true"></i><span class="visually-hidden">No</span></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="seccion seccion-oscura" aria-labelledby="titulo-inscripcion">
    <div class="envoltura franja-cta">
        <div>
            <h2 class="titulo-l" id="titulo-inscripcion">Inscríbete en recepción</h2>
            <p class="entrada mt-3"><?= e($g['direccion']) ?> · <?= e(implode(' · ', $g['horario'])) ?></p>
        </div>
        <div class="acciones">
            <a class="boton" href="<?= e(enlace_mapa()) ?>" target="_blank" rel="noopener">Cómo llegar<span class="flecha-circulo"><?= flecha() ?></span></a>
            <a class="boton boton-contorno" href="<?= url('contacto.php') ?>">Tengo una duda</a>
        </div>
    </div>
</section>

<?php require ROOT_PATH . '/app/vistas/publico_fin.php'; ?>
