<?php
/**
 * Pantalla de Inicio.
 * Portada con foto, datos rápidos (horario, precio desde, dirección), instalaciones,
 * planes leídos de la base de datos, una declaración que se enciende al hacer scroll,
 * cómo se ve el panel del socio y la invitación a visitarnos.
 */
require __DIR__ . '/app/bootstrap.php';
require ROOT_PATH . '/app/planes.php';
require_once ROOT_PATH . '/app/componentes.php';

$planes = planes_activos();
$g = config('gimnasio');
$desde = precio_desde($planes);

// Plan de ejemplo para el celular de la sección "Tu membresía en línea"
$planMuestra = $planes[(int) floor((count($planes) - 1) / 2)] ?? null;
$diasMuestra = 18;
$venceMuestra = new DateTime('+' . $diasMuestra . ' days');
$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

$instalaciones = [
    ['img/instalaciones/pesas.jpg',  'Área de pesas',   'Rack de mancuernas hexagonales frente a un muro de ladrillo'],
    ['img/instalaciones/cardio.jpg', 'Zona de cardio',  'Fila de caminadoras frente a un ventanal al atardecer'],
    ['img/instalaciones/clases.jpg', 'Salón de clases', 'Salón con piso de madera, tapetes y pesas rusas de colores'],
];

$titulo = 'MaxisGym · Gimnasio cerca de Ciudad Universitaria';
$activo = 'inicio';
$navSobreFoto = true;
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>

<section class="hero" data-entrada aria-labelledby="hero-titulo">
    <?= foto('img/sitio/hero.jpg', 'Plataforma de levantamiento con una barra cargada de discos de colores y, al fondo, un rack y un muro de ladrillo con el letrero de MaxisGym', ['clase' => 'hero-foto', 'prioritaria' => true, 'sizes' => '(max-aspect-ratio: 16/9) 178vh, 100vw']) ?>
    <div class="envoltura hero-contenido">
        <h1 class="titulo-xxl" id="hero-titulo">
            <span class="linea" style="--i:0"><span>Pesas, cardio</span></span>
            <span class="linea" style="--i:1"><span>y clases</span></span>
            <span class="linea" style="--i:2"><span class="acento">cerca de CU</span></span>
        </h1>
        <p class="hero-texto aparece">Equipo completo, entrenadores en piso y tu membresía siempre a la mano: desde tu panel ves cuándo vence, tus pagos y los avisos del gimnasio.</p>
        <div class="acciones aparece">
            <a class="boton" href="<?= url('planes.php') ?>">Ver planes<span class="flecha-circulo"><?= flecha() ?></span></a>
            <a class="boton boton-contorno" href="<?= url('contacto.php') ?>">Escríbenos</a>
        </div>
    </div>
</section>

<div class="datos-sitio">
    <div class="envoltura">
        <dl>
            <div>
                <dt>Horario</dt>
                <dd><?= e($g['horario'][0] ?? '') ?></dd>
            </div>
            <div>
                <dt>Planes</dt>
                <dd><?= $desde ? 'Desde ' . e($desde) : 'Pregunta en recepción' ?></dd>
            </div>
            <div>
                <dt>Dónde</dt>
                <dd><a href="<?= e(enlace_mapa()) ?>" target="_blank" rel="noopener"><?= e($g['direccion']) ?></a></dd>
            </div>
        </dl>
    </div>
</div>

<section class="seccion seccion-oscura" aria-labelledby="titulo-instalaciones">
    <div class="envoltura">
        <div class="seccion-cabeza">
            <h2 class="titulo-l" id="titulo-instalaciones">Instalaciones</h2>
            <p class="entrada">Área de pesas, zona de cardio y salón de clases grupales, con regaderas y lockers. Siempre hay alguien en piso para corregir tu técnica o armarte una rutina.</p>
        </div>
        <div class="mosaico">
            <?php foreach ($instalaciones as [$archivo, $nombre, $alt]): ?>
                <a class="mosaico-pieza" href="<?= url('acerca.php#instalaciones') ?>">
                    <?= foto($archivo, $alt, ['sizes' => '(min-width: 992px) 58vw, 100vw']) ?>
                    <span class="mosaico-etiqueta"><?= e($nombre) ?><span class="flecha-circulo"><?= flecha() ?></span></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($planes): ?>
<section class="seccion seccion-clara" aria-labelledby="titulo-planes">
    <div class="envoltura">
        <div class="seccion-cabeza">
            <h2 class="titulo-l" id="titulo-planes">Planes</h2>
            <p class="entrada">Entre más pesado el disco, más incluye el plan. Te inscribes en recepción y desde ese día tu panel muestra tu membresía.</p>
        </div>
        <div class="planes-rejilla">
            <?php foreach ($planes as $plan): ?>
                <?= tarjeta_plan($plan) ?>
            <?php endforeach; ?>
        </div>
        <p class="planes-nota">
            <a class="enlace" href="<?= url('planes.php#comparar') ?>">Comparar los planes <?= flecha() ?></a>
        </p>
    </div>
</section>
<?php endif; ?>

<section class="seccion seccion-oscura declaracion" aria-label="Nuestros valores">
    <div class="envoltura">
        <p class="declaracion-texto" data-resaltar>Celebramos <strong>venir seguido</strong> más que levantar mucho. Aquí nadie juzga: todos empezamos en algún punto.</p>
    </div>
</section>

<section class="seccion seccion-clara" aria-labelledby="titulo-app">
    <div class="envoltura app-rejilla">
        <div>
            <h2 class="titulo-l" id="titulo-app">Tu membresía en tu celular</h2>
            <ul class="lista-check">
                <li><i class="bi bi-calendar-check" aria-hidden="true"></i>
                    <div><strong>Cuándo vence tu plan</strong><span>Te avisamos por correo 7, 3 y 1 día antes.</span></div></li>
                <li><i class="bi bi-receipt" aria-hidden="true"></i>
                    <div><strong>Tu historial de pagos</strong><span>Fecha, monto, método y plan de cada pago.</span></div></li>
                <li><i class="bi bi-megaphone" aria-hidden="true"></i>
                    <div><strong>Los avisos del gimnasio</strong><span>Cierres, horarios especiales y novedades.</span></div></li>
            </ul>
            <div class="acciones">
                <a class="boton boton-oscuro" href="<?= url('registro.php') ?>">Crear mi cuenta<span class="flecha-circulo"><?= flecha() ?></span></a>
                <a class="boton boton-contorno-oscuro" href="<?= url('login.php') ?>">Iniciar sesión</a>
            </div>
        </div>
        <?php if ($planMuestra): ?>
        <div class="telefono" aria-hidden="true">
            <div class="telefono-pantalla">
                <div class="telefono-barra"><span>9:41</span><span><i class="bi bi-reception-4"></i> <i class="bi bi-battery-full"></i></span></div>
                <div class="telefono-cabeza">
                    <strong>Mi panel</strong>
                    <span class="avatar" style="width:32px;height:32px;font-size:.9rem">MG</span>
                </div>
                <div class="telefono-tarjeta telefono-tarjeta-oscura">
                    <?= disco_progreso($diasMuestra, (int) $planMuestra['duracion_dias'], $planMuestra['color'], '', 'al-verlo') ?>
                    <div>
                        <p class="telefono-etiqueta">Tu plan</p>
                        <p class="telefono-plan"><?= e($planMuestra['nombre_plan']) ?></p>
                        <span class="small" style="color:var(--acero-claro)">Vence el <?= $venceMuestra->format('j') . ' ' . $mesesCortos[(int) $venceMuestra->format('n')] ?></span>
                    </div>
                </div>
                <div class="telefono-tarjeta">
                    <div class="telefono-fila"><span>Último pago</span><b><?= e(dinero($planMuestra['precio'])) ?></b></div>
                    <div class="telefono-fila"><span>Método</span><b>Tarjeta</b></div>
                </div>
                <div class="telefono-tarjeta">
                    <div class="telefono-fila"><span><i class="bi bi-bell"></i> Avisos del gimnasio</span><b>2 nuevos</b></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<section class="visita" aria-labelledby="titulo-visita">
    <?= foto('img/sitio/discos.jpg', '', ['clase' => 'visita-foto']) ?>
    <div class="envoltura visita-rejilla">
        <div>
            <h2 class="titulo-l" id="titulo-visita">Visítanos</h2>
            <a class="direccion-grande" href="<?= e(enlace_mapa()) ?>" target="_blank" rel="noopener"><?= e($g['direccion']) ?></a>
            <div class="acciones">
                <a class="boton" href="<?= e(enlace_mapa()) ?>" target="_blank" rel="noopener">Cómo llegar<span class="flecha-circulo"><?= flecha() ?></span></a>
                <a class="boton boton-contorno" href="<?= url('contacto.php') ?>">Escríbenos</a>
            </div>
        </div>
        <dl class="horario">
            <?php foreach ($g['horario'] as $linea): ?>
                <?php [$dias, $horas] = array_pad(explode(':', $linea, 2), 2, ''); ?>
                <div><dt><?= e(trim($dias)) ?></dt><dd><?= e(trim($horas)) ?></dd></div>
            <?php endforeach; ?>
            <div><dt>Teléfono</dt><dd><a href="tel:<?= e(preg_replace('/\s+/', '', $g['telefono'])) ?>"><?= e($g['telefono']) ?></a></dd></div>
        </dl>
    </div>
</section>

<?php require ROOT_PATH . '/app/vistas/publico_fin.php'; ?>
