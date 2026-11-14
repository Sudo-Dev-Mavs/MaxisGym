<?php
/**
 * ¿Olvidaste tu contraseña? — Paso 1: pedir el enlace.
 *
 * Se genera un token aleatorio, se guarda SU HASH en password_reset_tokens
 * (una fila por correo) y se envía por correo un enlace con el token.
 * El enlace dura 30 minutos (config 'recuperacion_minutos') y sólo sirve una vez.
 * El mensaje de respuesta es el mismo exista o no el correo, para no revelar
 * qué correos están registrados.
 */
require __DIR__ . '/app/bootstrap.php';
require ROOT_PATH . '/app/correo.php';

if ($yo = usuario_actual()) {
    redirigir(panel_de($yo));
}

const MENSAJE_ENVIADO = 'Si el correo está registrado, te enviamos un enlace para crear una nueva contraseña. Revisa tu bandeja de entrada.';

if (es_post()) {
    csrf_verificar();
    $email = mb_strtolower(entrada('email'));

    if (!correo_valido($email)) {
        flash('danger', 'Escribe un correo válido, por ejemplo nombre@correo.com.');
        guardar_viejo(['email' => $email]);
        redirigir('recuperar.php');
    }

    $usuario = fila('SELECT id, nombre, email FROM usuarios WHERE email = ? AND activo = 1', [$email]);

    if ($usuario !== null) {
        // Evita mandar varios correos seguidos: un enlace por minuto como máximo
        $reciente = valor(
            'SELECT COUNT(*) FROM password_reset_tokens
              WHERE email = ? AND created_at > NOW() - INTERVAL 1 MINUTE',
            [$usuario['email']]
        );

        if (!$reciente) {
            $enlace = generar_enlace_contrasena($usuario['email']);
            $minutos = (int) config('recuperacion_minutos', 30);

            enviar_correo(
                $usuario['email'],
                'Restablece tu contraseña de MaxisGym',
                '<p style="margin-top:0">Hola, ' . e($usuario['nombre']) . ':</p>'
                . '<p>Recibimos una solicitud para cambiar la contraseña de tu cuenta. '
                . 'Da clic en el botón para crear una nueva:</p>'
                . boton_correo('Crear nueva contraseña', $enlace)
                . '<p>El enlace vence en ' . $minutos . ' minutos y sólo se puede usar una vez.</p>'
                . '<p style="color:#6b747d;font-size:13px">Si tú no lo pediste, ignora este correo: tu contraseña no cambiará.</p>'
            );
        }
    }

    limpiar_viejo();
    flash('success', MENSAJE_ENVIADO);
    redirigir('recuperar.php');
}

$titulo = 'Recuperar contraseña · MaxisGym';
$activo = '';
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>

<section class="acceso">
    <div class="acceso-panel">
        <h1>Recuperar contraseña</h1>
        <p class="acceso-sub">Escribe el correo de tu cuenta y te enviaremos un enlace para crear una contraseña nueva.</p>

        <?= flash_html() ?>

        <form method="post" action="<?= url('recuperar.php') ?>">
            <?= csrf_campo() ?>
            <div class="mb-4">
                <label class="form-label" for="email">Correo</label>
                <input class="form-control form-control-lg" id="email" name="email" type="email"
                       value="<?= viejo('email') ?>" required autocomplete="email" autofocus>
            </div>
            <button class="btn btn-seguridad btn-lg w-100" type="submit">Enviar enlace</button>
        </form>

        <p class="acceso-pie"><a href="<?= url('login.php') ?>">Volver a iniciar sesión</a></p>
    </div>
</section>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/publico_fin.php';
?>
