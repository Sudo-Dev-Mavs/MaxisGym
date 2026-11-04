<?php
/**
 * Pantalla de Registro.
 * Cualquier visitante puede crear una cuenta, pero SIEMPRE con el rol "socio".
 * Los empleados y administradores sólo los crea un administrador
 * (así nadie puede registrarse como administrador).
 */
require __DIR__ . '/app/bootstrap.php';
require ROOT_PATH . '/app/validacion.php';

if ($yo = usuario_actual()) {
    redirigir(panel_de($yo));
}

$errores = [];

if (es_post()) {
    csrf_verificar();

    $datos = datos_persona_post();
    $password = $_POST['password'] ?? '';
    $confirmacion = $_POST['password_confirmacion'] ?? '';

    // Nombre, apellido, correo (no repetido), teléfono y fecha: obligatorios en el registro
    $errores = validar_persona($datos, 0, true, true);

    if (!is_string($password) || !is_string($confirmacion)
        || ($msg = error_password($password, $confirmacion)) !== null) {
        $errores['password'] = $msg ?? 'Escribe una contraseña.';
    }
    if (!isset($_POST['terminos'])) {
        $errores['terminos'] = 'Debes aceptar los términos y condiciones.';
    }

    $rolSocio = rol_id('socio');
    if ($rolSocio === null) {
        $errores['general'] = 'El registro no está disponible: falta el rol "socio" en el catálogo de roles.';
    }

    if (!$errores) {
        try {
            consulta(
                'INSERT INTO usuarios
                    (rol_id, nombre, apellido, email, telefono, fecha_nacimiento, password, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $rolSocio, $datos['nombre'], $datos['apellido'], $datos['email'],
                    $datos['telefono'], $datos['fecha_nacimiento'],
                    password_hash($password, PASSWORD_DEFAULT),
                ]
            );
        } catch (PDOException $ex) {
            if (!es_error_duplicado($ex)) {
                throw $ex;
            }
            $errores['email'] = 'Ya existe una cuenta con ese correo.';
        }

        if (!$errores) {
            limpiar_viejo();
            iniciar_sesion(['id' => db()->lastInsertId()], false);
            flash('success', '¡Bienvenido a MaxisGym! Tu cuenta está lista. Acude a recepción para elegir tu plan y activar tu membresía.');
            redirigir('socio/index.php');
        }
    }

    guardar_viejo($datos);
}

$invalido = fn(string $c) => isset($errores[$c]) ? ' is-invalid' : '';
$error = fn(string $c) => isset($errores[$c]) ? '<div class="invalid-feedback">' . e($errores[$c]) . '</div>' : '';

$titulo = 'Registrarse · MaxisGym';
$activo = '';
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>

<section class="acceso">
    <div class="acceso-panel acceso-panel-ancho">
        <h1>Crear cuenta</h1>
        <p class="acceso-sub">Con tu cuenta ves tu membresía, tus pagos y los avisos del gimnasio.</p>

        <?php if (isset($errores['general'])): ?>
            <div class="alert alert-danger"><?= e($errores['general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('registro.php') ?>" novalidate>
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
                <div class="col-12">
                    <label class="form-label" for="email">Correo</label>
                    <input class="form-control<?= $invalido('email') ?>" id="email" name="email" type="email"
                           value="<?= viejo('email') ?>" maxlength="120" required autocomplete="email">
                    <?= $error('email') ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="telefono">Teléfono</label>
                    <input class="form-control<?= $invalido('telefono') ?>" id="telefono" name="telefono" type="tel"
                           value="<?= viejo('telefono') ?>" maxlength="20" required autocomplete="tel">
                    <?= $error('telefono') ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input class="form-control<?= $invalido('fecha_nacimiento') ?>" id="fecha_nacimiento" name="fecha_nacimiento"
                           type="date" value="<?= viejo('fecha_nacimiento') ?>" max="<?= date('Y-m-d') ?>" required autocomplete="bday">
                    <?= $error('fecha_nacimiento') ?>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="password">Contraseña</label>
                    <input class="form-control<?= $invalido('password') ?>" id="password" name="password" type="password"
                           minlength="8" required autocomplete="new-password" aria-describedby="ayuda-password">
                    <?= $error('password') ?>
                    <div id="ayuda-password" class="form-text">Mínimo 8 caracteres, con letras y números.</div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="password_confirmacion">Confirmar contraseña</label>
                    <input class="form-control<?= $invalido('password') ?>" id="password_confirmacion" name="password_confirmacion"
                           type="password" minlength="8" required autocomplete="new-password">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input<?= $invalido('terminos') ?>" type="checkbox" id="terminos" name="terminos" value="1">
                        <label class="form-check-label" for="terminos">
                            Acepto los <a href="#" data-bs-toggle="modal" data-bs-target="#modal-terminos">términos y condiciones</a>
                        </label>
                        <?= $error('terminos') ?>
                    </div>
                </div>
                <div class="col-12">
                    <button class="btn btn-seguridad btn-lg w-100" type="submit">Crear cuenta</button>
                </div>
            </div>
        </form>

        <p class="acceso-pie">
            ¿Ya tienes cuenta? <a href="<?= url('login.php') ?>">Inicia sesión</a>
            <span class="mx-2 text-body-tertiary">|</span>
            <a href="<?= url('index.php') ?>">Volver a inicio</a>
        </p>
    </div>
</section>

<div class="modal fade" id="modal-terminos" tabindex="-1" aria-labelledby="titulo-terminos" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h4" id="titulo-terminos">Términos y condiciones</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Al crear tu cuenta en MaxisGym aceptas lo siguiente:</p>
                <ul>
                    <li>Tus datos se usan sólo para administrar tu membresía, tus pagos y enviarte avisos del gimnasio.</li>
                    <li>La cuenta es personal; no compartas tu contraseña.</li>
                    <li>Crear la cuenta no activa una membresía: el plan se contrata y paga en recepción.</li>
                    <li>Debes respetar el reglamento interno y las indicaciones del personal.</li>
                    <li>Puedes pedir en recepción la baja de tu cuenta cuando lo desees.</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-seguridad" data-bs-dismiss="modal">Entendido</button>
            </div>
        </div>
    </div>
</div>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/publico_fin.php';
?>
