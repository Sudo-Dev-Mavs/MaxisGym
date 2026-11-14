<?php
/**
 * Alta de socios — permiso socios.alta.
 *
 * Recepción inscribe a un socio nuevo con sus datos, el plan y su primer pago.
 * En una sola transacción se crean: el usuario (rol socio), la membresía y el pago.
 * El socio recibe un correo de bienvenida con un enlace para crear su contraseña
 * (el personal nunca conoce ni escribe la contraseña del socio).
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/validacion.php';
require_once ROOT_PATH . '/app/pagos.php';
require_once ROOT_PATH . '/app/correo.php';

$yo = requiere_permiso('socios.alta');
$errores = [];

if (es_post()) {
    csrf_verificar();

    $persona = datos_persona_post();
    $pago = [
        'plan_id'        => (int) entrada('plan_id'),
        'metodo_pago_id' => (int) entrada('metodo_pago_id'),
        'monto'          => entrada('monto'),
        'fecha_pago'     => entrada('fecha_pago'),
    ];

    $errores = validar_persona($persona, 0, true, true);
    if (isset($errores['email']) && str_contains($errores['email'], 'ya lo usa')) {
        $errores['email'] = 'Ese correo ya tiene cuenta. Si el socio se registró en línea, cóbrale desde Registrar pagos.';
    }
    [$erroresPago, $plan] = validar_pago($pago);
    $errores += $erroresPago;
    $rolSocio = rol_id('socio');
    if (!$rolSocio) {
        $errores['general'] = 'Falta el rol "socio" en el catálogo de roles.';
    }

    if (!$errores) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            consulta(
                'INSERT INTO usuarios (rol_id, nombre, apellido, email, telefono, fecha_nacimiento, password, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $rolSocio, $persona['nombre'], $persona['apellido'], $persona['email'], $persona['telefono'],
                    $persona['fecha_nacimiento'],
                    // Contraseña aleatoria que nadie conoce; el socio crea la suya con el enlace del correo
                    password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                ]
            );
            $socioId = (int) $pdo->lastInsertId();
            $r = crear_membresia_con_pago($socioId, $plan, $pago['metodo_pago_id'],
                (float) str_replace(',', '', $pago['monto']), $pago['fecha_pago'], (int) $yo['id']);
            $enlace = generar_enlace_contrasena($persona['email']);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        enviar_correo(
            $persona['email'],
            'Bienvenido a MaxisGym',
            '<p style="margin-top:0">¡Hola, ' . e($persona['nombre']) . '!</p>'
            . '<p>Ya eres socio de MaxisGym. Tu <strong>' . e($plan['nombre_plan']) . '</strong> está activo del '
            . e(fecha($r['fecha_inicio'])) . ' al ' . e(fecha($r['fecha_fin'])) . '.</p>'
            . '<p>Crea tu contraseña para entrar a tu panel, donde verás tu membresía, tus pagos y los avisos del gimnasio:</p>'
            . boton_correo('Crear mi contraseña', $enlace)
            . '<p style="color:#6b747d;font-size:13px">El enlace vence en ' . (int) config('recuperacion_minutos', 30)
            . ' minutos. Si vence, entra a la página de inicio de sesión y usa "¿Olvidaste tu contraseña?" con este mismo correo.</p>'
        );

        limpiar_viejo();
        flash('success', 'Socio ' . $persona['nombre'] . ' ' . $persona['apellido'] . ' registrado con el ' . $plan['nombre_plan']
            . ' del ' . fecha($r['fecha_inicio']) . ' al ' . fecha($r['fecha_fin']) . '. Se le envió un correo para crear su contraseña.');
        redirigir('empleado/alta_socio.php');
    }

    guardar_viejo($persona + $pago);
}

$planes = planes_para_venta();
$metodos = metodos_pago_activos();
$v = fn(string $c, string $d = '') => e($_SESSION['viejo'][$c] ?? $d);
$planElegido = (int) ($_SESSION['viejo']['plan_id'] ?? 0);

$titulo = 'Alta de socios';
$scripts = ['alta_socio.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<?php if (isset($errores['general'])): ?><div class="alert alert-danger"><?= e($errores['general']) ?></div><?php endif; ?>

<form method="post" action="<?= url('empleado/alta_socio.php') ?>" class="tarjeta" style="max-width: 920px" novalidate>
    <?= csrf_campo() ?>
    <h2 class="h4 mb-1">Registrar nuevo socio</h2>
    <p class="text-body-secondary mb-4">El socio recibirá un correo para crear su contraseña. Si ya se registró en línea, usa
        <a href="<?= url('empleado/pagos.php') ?>">Registrar pagos</a>.</p>

    <fieldset class="mb-4">
        <legend class="h6 text-body-secondary">Datos personales</legend>
        <div class="row g-3">
            <?php foreach (['nombre' => 'Nombre', 'apellido' => 'Apellido'] as $c => $et): ?>
                <div class="col-sm-6">
                    <label class="form-label" for="<?= $c ?>"><?= $et ?></label>
                    <input class="form-control<?= clase_invalida($errores, $c) ?>" id="<?= $c ?>" name="<?= $c ?>" value="<?= $v($c) ?>" maxlength="60" required>
                    <?= mensaje_error($errores, $c) ?>
                </div>
            <?php endforeach; ?>
            <div class="col-sm-6">
                <label class="form-label" for="email">Correo</label>
                <input class="form-control<?= clase_invalida($errores, 'email') ?>" id="email" name="email" type="email" value="<?= $v('email') ?>" maxlength="120" required>
                <?= mensaje_error($errores, 'email') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="telefono">Teléfono</label>
                <input class="form-control<?= clase_invalida($errores, 'telefono') ?>" id="telefono" name="telefono" type="tel" value="<?= $v('telefono') ?>" maxlength="20" required>
                <?= mensaje_error($errores, 'telefono') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                <input class="form-control<?= clase_invalida($errores, 'fecha_nacimiento') ?>" id="fecha_nacimiento" name="fecha_nacimiento"
                       type="date" value="<?= $v('fecha_nacimiento') ?>" max="<?= date('Y-m-d') ?>" required>
                <?= mensaje_error($errores, 'fecha_nacimiento') ?>
            </div>
        </div>
    </fieldset>

    <fieldset class="mb-4">
        <legend class="h6 text-body-secondary">Plan y primer pago</legend>
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label" for="plan_id">Plan seleccionado</label>
                <select class="form-select<?= clase_invalida($errores, 'plan_id') ?>" id="plan_id" name="plan_id" required>
                    <?php foreach ($planes as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" data-precio="<?= e($p['precio']) ?>" <?= $planElegido === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['nombre_plan']) ?> (<?= e(dinero($p['precio'])) ?>, <?= (int) $p['duracion_dias'] ?> días)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= mensaje_error($errores, 'plan_id') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="metodo_pago_id">Método de pago</label>
                <select class="form-select<?= clase_invalida($errores, 'metodo_pago_id') ?>" id="metodo_pago_id" name="metodo_pago_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($metodos as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= (int) ($_SESSION['viejo']['metodo_pago_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= mensaje_error($errores, 'metodo_pago_id') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="monto">Monto</label>
                <div class="input-group has-validation">
                    <span class="input-group-text">$</span>
                    <input class="form-control<?= clase_invalida($errores, 'monto') ?>" id="monto" name="monto" type="number" min="0.01" step="0.01"
                           value="<?= $v('monto', (string) ($planes[0]['precio'] ?? '')) ?>" required>
                    <?= mensaje_error($errores, 'monto') ?>
                </div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="fecha_pago">Fecha de pago</label>
                <input class="form-control<?= clase_invalida($errores, 'fecha_pago') ?>" id="fecha_pago" name="fecha_pago" type="date"
                       value="<?= $v('fecha_pago', date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                <?= mensaje_error($errores, 'fecha_pago') ?>
            </div>
        </div>
    </fieldset>

    <button class="btn btn-seguridad px-4" type="submit">Registrar socio y pago</button>
</form>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/panel_fin.php';
?>
