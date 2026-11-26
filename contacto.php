<?php
/**
 * Pantalla de Contacto.
 * El formulario guarda el mensaje en la tabla mensajes_contacto.
 * Si quien escribe tiene sesión iniciada, se liga a su usuario (usuario_id).
 */
require __DIR__ . '/app/bootstrap.php';
require_once ROOT_PATH . '/app/componentes.php';

$errores = [];

if (es_post()) {
    csrf_verificar();

    $datos = [
        'nombre'   => entrada('nombre'),
        'apellido' => entrada('apellido'),
        'email'    => entrada('email'),
        'telefono' => entrada('telefono'),
        'asunto'   => entrada('asunto'),
        'mensaje'  => entrada('mensaje'),
    ];

    if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 60) {
        $errores['nombre'] = 'Escribe tu nombre (máximo 60 caracteres).';
    }
    if ($datos['apellido'] === '' || mb_strlen($datos['apellido']) > 60) {
        $errores['apellido'] = 'Escribe tu apellido (máximo 60 caracteres).';
    }
    if (!correo_valido($datos['email'])) {
        $errores['email'] = 'Escribe un correo válido, por ejemplo nombre@correo.com.';
    }
    if ($datos['telefono'] !== '' && !preg_match('/^[0-9 +()\-]{7,20}$/', $datos['telefono'])) {
        $errores['telefono'] = 'El teléfono sólo puede tener números, espacios y guiones.';
    }
    if ($datos['asunto'] === '' || mb_strlen($datos['asunto']) > 120) {
        $errores['asunto'] = 'Escribe el asunto (máximo 120 caracteres).';
    }
    if (mb_strlen($datos['mensaje']) < 10) {
        $errores['mensaje'] = 'El mensaje debe tener al menos 10 caracteres.';
    }

    if (!$errores) {
        consulta(
            'INSERT INTO mensajes_contacto
                (usuario_id, nombre, apellido, email, telefono, asunto, mensaje, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $_SESSION['usuario_id'] ?? null,
                $datos['nombre'], $datos['apellido'], $datos['email'],
                $datos['telefono'] !== '' ? $datos['telefono'] : null,
                $datos['asunto'], $datos['mensaje'],
            ]
        );
        limpiar_viejo();
        flash('success', 'Mensaje enviado. Te responderemos a ' . $datos['email'] . ' en menos de 24 horas.');
        redirigir('contacto.php');
    }

    guardar_viejo($datos);
}

$g = config('gimnasio');
$lat = (float) $g['latitud'];
$lon = (float) $g['longitud'];
$mapa = sprintf(
    'https://www.openstreetmap.org/export/embed.html?bbox=%F,%F,%F,%F&layer=mapnik&marker=%F,%F',
    $lon - 0.008, $lat - 0.005, $lon + 0.008, $lat + 0.005, $lat, $lon
);

/** Clase y mensaje de error de un campo. */
$invalido = fn(string $c) => isset($errores[$c]) ? ' is-invalid' : '';
$error = fn(string $c) => isset($errores[$c]) ? '<div class="invalid-feedback">' . e($errores[$c]) . '</div>' : '';

$titulo = 'Contacto · MaxisGym';
$activo = 'contacto';
$navSobreFoto = true;
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>

<?= encabezado_pagina('Contáctanos',
    '¿Dudas sobre planes, horarios o tu membresía? Escríbenos y te respondemos por correo.',
    'img/instalaciones/cardio.jpg', 'Caminadoras frente a un ventanal al atardecer', '40% 50%') ?>

<section class="seccion seccion-clara">
    <div class="envoltura">
        <div class="contacto-rejilla">
            <div>
                <?= flash_html() ?>
                <div class="panel-blanco">
                    <h2 class="mb-4">Envíanos un mensaje</h2>
                    <form method="post" action="<?= url('contacto.php') ?>" novalidate>
                        <?= csrf_campo() ?>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="nombre">Nombre</label>
                                <input class="form-control<?= $invalido('nombre') ?>" id="nombre" name="nombre"
                                       value="<?= viejo('nombre') ?>" maxlength="60" required autocomplete="given-name">
                                <?= $error('nombre') ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="apellido">Apellido</label>
                                <input class="form-control<?= $invalido('apellido') ?>" id="apellido" name="apellido"
                                       value="<?= viejo('apellido') ?>" maxlength="60" required autocomplete="family-name">
                                <?= $error('apellido') ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="email">Correo</label>
                                <input class="form-control<?= $invalido('email') ?>" id="email" name="email" type="email"
                                       value="<?= viejo('email') ?>" maxlength="120" required autocomplete="email">
                                <?= $error('email') ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="telefono">Teléfono <span class="text-body-secondary fw-normal">(opcional)</span></label>
                                <input class="form-control<?= $invalido('telefono') ?>" id="telefono" name="telefono" type="tel"
                                       value="<?= viejo('telefono') ?>" maxlength="20" autocomplete="tel">
                                <?= $error('telefono') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="asunto">Asunto</label>
                                <input class="form-control<?= $invalido('asunto') ?>" id="asunto" name="asunto"
                                       value="<?= viejo('asunto') ?>" maxlength="120" required>
                                <?= $error('asunto') ?>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="mensaje">Mensaje</label>
                                <textarea class="form-control<?= $invalido('mensaje') ?>" id="mensaje" name="mensaje"
                                          rows="5" required minlength="10"><?= viejo('mensaje') ?></textarea>
                                <?= $error('mensaje') ?>
                            </div>
                            <div class="col-12">
                                <button class="boton" type="submit">Enviar mensaje<span class="flecha-circulo"><?= flecha() ?></span></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div>
                <h2 class="titulo-l">Encuéntranos</h2>
                <ul class="datos-contacto">
                    <li><i class="bi bi-geo-alt" aria-hidden="true"></i><span><?= e($g['direccion']) ?></span></li>
                    <li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:<?= e(preg_replace('/\s+/', '', $g['telefono'])) ?>"><?= e($g['telefono']) ?></a></li>
                    <li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:<?= e($g['email']) ?>"><?= e($g['email']) ?></a></li>
                    <li><i class="bi bi-clock" aria-hidden="true"></i><span><?= implode('<br>', array_map('e', $g['horario'])) ?></span></li>
                </ul>
                <div class="mapa-marco">
                    <iframe class="mapa" src="<?= e($mapa) ?>" title="Mapa de la ubicación de MaxisGym" loading="lazy"></iframe>
                </div>
                <a class="enlace mt-2" target="_blank" rel="noopener" href="<?= e(enlace_mapa()) ?>">Abrir el mapa en otra pestaña <?= flecha() ?></a>
            </div>
        </div>
    </div>
</section>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/publico_fin.php';
?>
