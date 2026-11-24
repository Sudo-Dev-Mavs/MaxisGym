<?php
/**
 * Pantalla Acerca de: historia, misión, valores e instalaciones.
 * Las fotos se leen de assets/img/instalaciones/. Si una foto no existe,
 * se muestra un recuadro con ícono para que la página no se vea rota.
 */
require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/componentes.php';

$instalaciones = [
    ['archivo' => 'pesas.jpg',  'nombre' => 'Área de pesas',   'icono' => 'bi-trophy',
     'detalle' => 'Racks, plataformas y mancuernas', 'alt' => 'Rack de mancuernas hexagonales frente a un muro de ladrillo'],
    ['archivo' => 'cardio.jpg', 'nombre' => 'Zona de cardio',  'icono' => 'bi-heart-pulse',
     'detalle' => 'Caminadoras frente al ventanal', 'alt' => 'Fila de caminadoras frente a un ventanal al atardecer'],
    ['archivo' => 'clases.jpg', 'nombre' => 'Salón de clases', 'icono' => 'bi-people',
     'detalle' => 'Tapetes, cajones y pesas rusas', 'alt' => 'Salón con piso de madera, tapetes y pesas rusas de colores'],
];

$titulo = 'Acerca de · MaxisGym';
$activo = 'acerca';
$navSobreFoto = true;
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>

<?= encabezado_pagina('Acerca de MaxisGym',
    'Un gimnasio de barrio con equipo completo y un sistema que te deja ver tu membresía y tus pagos sin hacer fila.',
    'img/instalaciones/clases.jpg', 'Salón de clases con tapetes y pesas rusas de colores', '50% 60%') ?>

<section class="seccion seccion-clara" aria-labelledby="titulo-historia">
    <div class="envoltura historia">
        <div>
            <h2 id="titulo-historia">Nuestra historia</h2>
            <p>
                MaxisGym abrió como un pequeño cuarto de pesas cerca de Ciudad Universitaria.
                Con los años crecimos a un gimnasio completo, pero el control de socios seguía
                en libretas: pagos que se perdían y membresías que vencían sin que nadie se diera cuenta.
            </p>
            <p>
                Por eso creamos esta plataforma. Hoy cada socio tiene su panel, el personal
                registra pagos en segundos y la administración ve los ingresos del mes al momento.
            </p>
            <div class="mision">
                <h3>Misión</h3>
                <p>Que cualquier persona pueda entrenar con constancia, con un espacio seguro,
                    personal que acompaña y procesos claros desde la inscripción.</p>
            </div>
        </div>
        <div>
            <h2>Valores</h2>
            <ul class="valores">
                <li><strong>Constancia</strong>Celebramos venir seguido más que levantar mucho.</li>
                <li><strong>Respeto</strong>Todos empezamos en algún punto; aquí nadie juzga.</li>
                <li><strong>Transparencia</strong>Ves lo que pagas, cuándo vence y qué incluye tu plan.</li>
                <li><strong>Seguridad</strong>Equipo en buen estado y orientación en cada ejercicio.</li>
            </ul>
        </div>
    </div>
</section>

<section class="seccion seccion-oscura" id="instalaciones" aria-labelledby="titulo-instalaciones">
    <div class="envoltura">
        <div class="seccion-cabeza">
            <h2 class="titulo-l" id="titulo-instalaciones">Nuestras instalaciones</h2>
            <p class="entrada">Pesas, cardio y clases grupales en un mismo lugar, con regaderas y lockers.</p>
        </div>
        <div class="galeria">
            <?php foreach ($instalaciones as $i => $lugar): ?>
                <?php $img = foto('img/instalaciones/' . $lugar['archivo'], $lugar['alt'], ['sizes' => '(min-width: 768px) 33vw, 100vw']); ?>
                <figure>
                    <div class="galeria-foto"<?= $img ? ' data-revelar data-retraso="' . ($i * 140) . '"' : '' ?>>
                        <?php if ($img): ?>
                            <?= $img ?>
                        <?php else: ?>
                            <div class="galeria-vacia"><i class="bi <?= e($lugar['icono']) ?>" aria-hidden="true"></i></div>
                        <?php endif; ?>
                    </div>
                    <figcaption><strong><?= e($lugar['nombre']) ?></strong><span><?= e($lugar['detalle']) ?></span></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="seccion seccion-clara">
    <div class="envoltura franja-cta">
        <h2 class="titulo-l">Ven a conocernos</h2>
        <div class="acciones">
            <a class="boton boton-oscuro" href="<?= url('planes.php') ?>">Ver planes<span class="flecha-circulo"><?= flecha() ?></span></a>
            <a class="boton boton-contorno-oscuro" href="<?= url('contacto.php') ?>">Escríbenos</a>
        </div>
    </div>
</section>

<?php require ROOT_PATH . '/app/vistas/publico_fin.php'; ?>
