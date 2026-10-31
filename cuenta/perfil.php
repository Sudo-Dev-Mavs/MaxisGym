<?php
/**
 * Mi perfil (cualquier usuario con el permiso perfil.editar).
 * - Actualizar información: nombre, apellido, correo, teléfono y fecha de nacimiento.
 *   Para cambiar el correo se pide la contraseña actual.
 * - Cambiar contraseña: pide la actual y la nueva dos veces.
 */
require __DIR__ . '/../app/bootstrap.php';

$yo = requiere_permiso('perfil.editar');
$errores = [];
$erroresClave = [];

if (es_post()) {
    csrf_verificar();
    $accion = entrada('accion');
    $hashActual = valor('SELECT password FROM usuarios WHERE id = ?', [$yo['id']]);

    if ($accion === 'datos') {
        $datos = [
            'nombre'           => entrada('nombre'),
            'apellido'         => entrada('apellido'),
            'email'            => mb_strtolower(entrada('email')),
            'telefono'         => entrada('telefono'),
            'fecha_nacimiento' => entrada('fecha_nacimiento'),
        ];

        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 60) {
            $errores['nombre'] = 'Escribe tu nombre (máximo 60 caracteres).';
        }
        if ($datos['apellido'] === '' || mb_strlen($datos['apellido']) > 60) {
            $errores['apellido'] = 'Escribe tu apellido (máximo 60 caracteres).';
        }
        if (!correo_valido($datos['email']) || mb_strlen($datos['email']) > 120) {
            $errores['email'] = 'Escribe un correo válido.';
        } elseif ($datos['email'] !== $yo['email']) {
            if (valor('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?', [$datos['email'], $yo['id']]) > 0) {
                $errores['email'] = 'Ese correo ya lo usa otra cuenta.';
            } elseif (!is_string($_POST['password_actual_correo'] ?? null)
                || !password_verify($_POST['password_actual_correo'], $hashActual)) {
                $errores['password_actual_correo'] = 'Para cambiar tu correo escribe tu contraseña actual.';
            }
        }
        if ($datos['telefono'] !== '' && !preg_match('/^[0-9 +()\-]{7,20}$/', $datos['telefono'])) {
            $errores['telefono'] = 'Escribe un teléfono de 7 a 20 dígitos.';
        }
        if ($datos['fecha_nacimiento'] !== '') {
            $f = DateTime::createFromFormat('!Y-m-d', $datos['fecha_nacimiento']);
            if (!$f || $f->format('Y-m-d') !== $datos['fecha_nacimiento'] || $f > new DateTime('-12 years')) {
                $errores['fecha_nacimiento'] = 'Revisa tu fecha de nacimiento.';
            }
        }

        if (!$errores) {
            consulta(
                'UPDATE usuarios
                    SET nombre = ?, apellido = ?, email = ?, telefono = ?, fecha_nacimiento = ?, updated_at = NOW()
                  WHERE id = ?',
                [
                    $datos['nombre'], $datos['apellido'], $datos['email'],
                    $datos['telefono'] !== '' ? $datos['telefono'] : null,
                    $datos['fecha_nacimiento'] !== '' ? $datos['fecha_nacimiento'] : null,
                    $yo['id'],
                ]
            );
            flash('success', 'Tu información se actualizó.');
            redirigir('cuenta/perfil.php');
        }
        guardar_viejo($datos);
    }

    if ($accion === 'password') {
        $actual = $_POST['password_actual'] ?? '';
        $nueva = $_POST['password'] ?? '';
        $confirmacion = $_POST['password_confirmacion'] ?? '';

        if (!is_string($actual) || !password_verify($actual, $hashActual)) {
            $erroresClave['password_actual'] = 'La contraseña actual no es correcta.';
        } elseif (!is_string($nueva) || !is_string($confirmacion)
            || ($msg = error_password($nueva, $confirmacion)) !== null) {
            $erroresClave['password'] = $msg ?? 'Escribe la nueva contraseña.';
        } elseif (password_verify($nueva, $hashActual)) {
            $erroresClave['password'] = 'La nueva contraseña debe ser distinta de la actual.';
        }

        if (!$erroresClave) {
            // Se cambia la contraseña y se invalidan los "Recordarme" de otros equipos
            consulta('UPDATE usuarios SET password = ?, remember_token = NULL, updated_at = NOW() WHERE id = ?',
                [password_hash($nueva, PASSWORD_DEFAULT), $yo['id']]);
            session_regenerate_id(true);
            if (isset($_COOKIE[COOKIE_RECORDAR])) {
                crear_cookie_recordar((int) $yo['id']); // este equipo sigue recordado
            }
            flash('success', 'Tu contraseña se cambió. En otros equipos tendrás que iniciar sesión de nuevo.');
            redirigir('cuenta/perfil.php');
        }
    }
}

// Datos frescos para el formulario
$perfil = fila('SELECT u.*, r.nombre AS rol FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?', [$yo['id']]);
$v = fn(string $campo) => isset($_SESSION['viejo'][$campo]) ? viejo($campo) : e($perfil[$campo] ?? '');
$invalido = fn(string $c, array $err) => isset($err[$c]) ? ' is-invalid' : '';
$error = fn(string $c, array $err) => isset($err[$c]) ? '<div class="invalid-feedback">' . e($err[$c]) . '</div>' : '';

$titulo = 'Mi perfil';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="row g-4">
    <div class="col-xl-7">
        <section class="tarjeta">
            <h2 class="h4 mb-1">Información personal</h2>
            <p class="text-body-secondary mb-4">
                Rol: <?= e(ucfirst($perfil['rol'])) ?><?= $perfil['puesto'] ? ' (' . e($perfil['puesto']) . ')' : '' ?>.
                Cuenta creada el <?= e(fecha($perfil['created_at'])) ?>.
            </p>
            <form method="post" action="<?= url('cuenta/perfil.php') ?>" novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="datos">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input class="form-control<?= $invalido('nombre', $errores) ?>" id="nombre" name="nombre"
                               value="<?= $v('nombre') ?>" maxlength="60" required autocomplete="given-name">
                        <?= $error('nombre', $errores) ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="apellido">Apellido</label>
                        <input class="form-control<?= $invalido('apellido', $errores) ?>" id="apellido" name="apellido"
                               value="<?= $v('apellido') ?>" maxlength="60" required autocomplete="family-name">
                        <?= $error('apellido', $errores) ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="email">Correo</label>
                        <input class="form-control<?= $invalido('email', $errores) ?>" id="email" name="email" type="email"
                               value="<?= $v('email') ?>" maxlength="120" required autocomplete="email">
                        <?= $error('email', $errores) ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input class="form-control<?= $invalido('telefono', $errores) ?>" id="telefono" name="telefono" type="tel"
                               value="<?= $v('telefono') ?>" maxlength="20" autocomplete="tel">
                        <?= $error('telefono', $errores) ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input class="form-control<?= $invalido('fecha_nacimiento', $errores) ?>" id="fecha_nacimiento"
                               name="fecha_nacimiento" type="date" value="<?= $v('fecha_nacimiento') ?>" max="<?= date('Y-m-d') ?>">
                        <?= $error('fecha_nacimiento', $errores) ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="password_actual_correo">Contraseña actual
                            <span class="text-body-secondary fw-normal">(sólo si cambias tu correo)</span></label>
                        <input class="form-control<?= $invalido('password_actual_correo', $errores) ?>" id="password_actual_correo"
                               name="password_actual_correo" type="password" autocomplete="current-password">
                        <?= $error('password_actual_correo', $errores) ?>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-seguridad" type="submit">Actualizar información</button>
                    </div>
                </div>
            </form>
        </section>
    </div>

    <div class="col-xl-5">
        <section class="tarjeta">
            <h2 class="h4 mb-1">Cambiar contraseña</h2>
            <p class="text-body-secondary mb-4">Mínimo 8 caracteres, con letras y números.</p>
            <form method="post" action="<?= url('cuenta/perfil.php') ?>" novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="password">
                <div class="mb-3">
                    <label class="form-label" for="password_actual">Contraseña actual</label>
                    <input class="form-control<?= $invalido('password_actual', $erroresClave) ?>" id="password_actual"
                           name="password_actual" type="password" required autocomplete="current-password">
                    <?= $error('password_actual', $erroresClave) ?>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Nueva contraseña</label>
                    <input class="form-control<?= $invalido('password', $erroresClave) ?>" id="password" name="password"
                           type="password" minlength="8" required autocomplete="new-password">
                    <?= $error('password', $erroresClave) ?>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmacion">Confirmar nueva contraseña</label>
                    <input class="form-control" id="password_confirmacion" name="password_confirmacion"
                           type="password" minlength="8" required autocomplete="new-password">
                </div>
                <button class="btn btn-outline-dark" type="submit">Cambiar contraseña</button>
            </form>
        </section>
    </div>
</div>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/panel_fin.php';
?>
