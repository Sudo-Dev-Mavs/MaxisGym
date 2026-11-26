<?php
/**
 * Registrar pagos — permiso pagos.registrar.
 *
 * El empleado busca al socio (búsqueda AJAX en api/buscar_socios.php),
 * elige el plan, el método y la fecha, y registra el pago. Cada pago crea
 * un periodo de membresía (ver app/pagos.php).
 *
 * Al elegir al socio se piden sus opciones (api/opciones_pago.php): según su
 * membresía se le puede inscribir, renovar, sólo mejorar de plan o nada
 * (si ya pagó su siguiente periodo). El servidor revisa lo mismo al registrar.
 *
 * El formulario se envía con AJAX (fetch) y la tabla de pagos recientes se
 * actualiza sin recargar la página. Si JavaScript está desactivado, el
 * formulario funciona igual con un envío normal.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/socios.php';
require_once ROOT_PATH . '/app/pagos.php';
require_once ROOT_PATH . '/app/validacion.php';

$yo = requiere_permiso('pagos.registrar');
$errores = [];

if (es_post()) {
    csrf_verificar();

    $datos = [
        'socio_id'       => (int) entrada('socio_id'),
        'plan_id'        => (int) entrada('plan_id'),
        'metodo_pago_id' => (int) entrada('metodo_pago_id'),
        'fecha_pago'     => entrada('fecha_pago'),
    ];

    $socio = socio_por_id($datos['socio_id']);
    if (!$socio || !$socio['activo']) {
        $errores['socio_id'] = 'Busca y elige un socio activo.';
    }
    [$erroresPago, $plan] = validar_pago($datos);
    $errores += $erroresPago;

    // Qué se le puede cobrar a este socio hoy (evita acumular periodos pagados)
    $cobro = isset($errores['socio_id']) ? null : opciones_de_pago((int) $socio['id']);
    if ($cobro && $cobro['modo'] === 'bloqueado') {
        $errores['socio_id'] = $cobro['aviso'];
    } elseif ($cobro && $plan && !opcion_de_plan($cobro, (int) $plan['id'])) {
        $errores['plan_id'] = 'Por ahora sólo puede mejorar a un plan de mayor precio que su ' . ($cobro['mejora']['plan'] ?? 'plan actual') . '.';
    }

    if (!$errores) {
        $mejora = $cobro['modo'] === 'mejora' ? $cobro['mejora'] : null;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $r = crear_membresia_con_pago($socio['id'], $plan, $datos['metodo_pago_id'], $datos['fecha_pago'], (int) $yo['id'], $mejora);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        $quien = $socio['nombre'] . ' ' . $socio['apellido'];
        $periodo = $plan['nombre_plan'] . ' del ' . fecha($r['fecha_inicio']) . ' al ' . fecha($r['fecha_fin']);
        $mensaje = $r['mejora']
            ? 'Mejora registrada para ' . $quien . ': ' . $periodo . '. Se cobraron ' . dinero($r['monto']) . ' ('
                . dinero($plan['precio']) . ' menos ' . dinero($r['credito']) . ' que no usó de su ' . $mejora['plan'] . ').'
            : 'Pago de ' . dinero($r['monto']) . ' registrado para ' . $quien . '. ' . $periodo
                . ($r['renovacion'] ? ' (inicia al terminar su periodo actual).' : '.');

        if (es_ajax()) {
            $nuevo = array_values(array_filter(pagos_recientes(5), fn($p) => (int) $p['id'] === $r['pago_id']));
            responder_json(['ok' => true, 'mensaje' => $mensaje, 'pago' => $nuevo ? pago_para_tabla($nuevo[0]) : null]);
        }
        flash('success', $mensaje);
        redirigir('empleado/pagos.php');
    }

    if (es_ajax()) {
        responder_json(['ok' => false, 'mensaje' => 'Revisa los datos marcados.', 'errores' => $errores], 422);
    }
    guardar_viejo($datos);
}

// Socio preseleccionado (?socio=ID desde Consultar socios, o tras un error)
$preId = (int) ($_SESSION['viejo']['socio_id'] ?? parametro('socio', '0'));
$pre = $preId ? socio_por_id($preId) : null;
$preMembresia = $pre ? ultima_membresia((int) $pre['id']) : null;

// Planes que se le pueden cobrar; elegido: el del intento anterior, o el sugerido
$cobro = opciones_de_pago($pre ? (int) $pre['id'] : null);
$bloqueado = $cobro['modo'] === 'bloqueado';
$planInicial = opcion_de_plan($cobro, (int) ($_SESSION['viejo']['plan_id'] ?? 0))
    ?? opcion_de_plan($cobro, (int) $cobro['sugerido'])
    ?? ($cobro['planes'][0] ?? null);
$credito = (float) ($planInicial['credito'] ?? 0);
$metodos = metodos_pago_activos();
$recientes = array_map('pago_para_tabla', pagos_recientes(15));
$v = fn(string $c, string $d = '') => e($_SESSION['viejo'][$c] ?? $d);

$titulo = 'Registrar pagos';
$scripts = ['buscador.js', 'pagos.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="row g-4">
    <div class="col-xl-5">
        <form method="post" action="<?= url('empleado/pagos.php') ?>" class="tarjeta tarjeta-fija" id="form-pago" novalidate
              data-api="<?= url('api/buscar_socios.php') ?>" data-opciones="<?= url('api/opciones_pago.php') ?>">
            <?= csrf_campo() ?>
            <h2 class="h4 mb-3">Formulario de pago</h2>
            <div id="alerta-pago" aria-live="polite"></div>

            <label class="form-label" for="buscar-socio">Socio</label>
            <div class="buscador mb-1">
                <input class="form-control<?= clase_invalida($errores, 'socio_id') ?>" id="buscar-socio" type="search"
                       placeholder="Escribe nombre, correo o ID" autocomplete="off"
                       aria-controls="resultados-socio" aria-describedby="ayuda-socio">
                <div class="list-group buscador-resultados" id="resultados-socio" role="listbox" hidden></div>
            </div>
            <div id="ayuda-socio" class="form-text mb-2">Empieza a escribir y elige al socio de la lista.</div>
            <div class="invalid-feedback d-block" data-error="socio_id"><?= e($errores['socio_id'] ?? '') ?></div>
            <input type="hidden" name="socio_id" id="socio_id" value="<?= $pre ? (int) $pre['id'] : '' ?>">

            <div class="socio-elegido mb-3" id="socio-elegido" <?= $pre ? '' : 'hidden' ?>>
                <strong data-campo="nombre"><?= $pre ? e($pre['nombre'] . ' ' . $pre['apellido']) : '' ?></strong>
                <span class="d-block small text-body-secondary" data-campo="detalle"><?php
                    if ($pre) {
                        echo e('#' . $pre['id'] . ', ' . $pre['email']) . '<br>';
                        echo $preMembresia
                            ? e($preMembresia['nombre_plan'] . ': ' . estado_membresia($preMembresia) . ', hasta ' . fecha($preMembresia['fecha_fin']))
                            : 'Sin membresía';
                    }
                ?></span>
                <button type="button" class="btn btn-sm btn-link p-0 mt-1" id="cambiar-socio">Cambiar socio</button>
            </div>

            <div id="aviso-pago" class="alert <?= $bloqueado ? 'alert-warning' : 'alert-info' ?> small py-2 mb-3" role="status"
                <?= $cobro['aviso'] === '' ? 'hidden' : '' ?>><?= e($cobro['aviso']) ?></div>

            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="plan_id">Plan</label>
                    <select class="form-select" id="plan_id" name="plan_id" required <?= $bloqueado ? 'disabled' : '' ?>
                            data-mejora-de="<?= e($cobro['mejora']['plan'] ?? '') ?>">
                        <?php foreach ($cobro['planes'] as $o): ?>
                            <option value="<?= $o['id'] ?>" data-precio="<?= e(number_format($o['precio'], 2, '.', '')) ?>"
                                    data-monto="<?= e(number_format($o['monto'], 2, '.', '')) ?>" data-credito="<?= e(number_format($o['credito'], 2, '.', '')) ?>"
                                    <?= $o['id'] === ($planInicial['id'] ?? 0) ? 'selected' : '' ?>>
                                <?= e($o['nombre']) ?> (<?= e(dinero($o['precio'])) ?>, <?= $o['duracion'] ?> días)
                            </option>
                        <?php endforeach; ?>
                        <?php if (!$cobro['planes']): ?><option value="">Sin planes disponibles</option><?php endif; ?>
                    </select>
                    <div class="invalid-feedback d-block" data-error="plan_id"><?= e($errores['plan_id'] ?? '') ?></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="monto">Monto a cobrar</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input class="form-control campo-fijo" id="monto" type="text" readonly aria-describedby="ayuda-monto"
                               value="<?= $planInicial ? e(number_format($planInicial['monto'], 2)) : '' ?>">
                    </div>
                    <div class="form-text" id="ayuda-monto"><?= !$planInicial ? '' : ($credito > 0
                        ? e('Precio ' . dinero($planInicial['precio']) . ' menos ' . dinero($credito) . ' que no ha usado de su ' . $cobro['mejora']['plan'] . '.')
                        : 'Es el precio del plan elegido.') ?></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="fecha_pago">Fecha de pago</label>
                    <input class="form-control" id="fecha_pago" name="fecha_pago" type="date"
                           value="<?= $v('fecha_pago', date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required>
                    <div class="invalid-feedback d-block" data-error="fecha_pago"><?= e($errores['fecha_pago'] ?? '') ?></div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="metodo_pago_id">Método de pago</label>
                    <select class="form-select" id="metodo_pago_id" name="metodo_pago_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($metodos as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= (int) ($_SESSION['viejo']['metodo_pago_id'] ?? 0) === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback d-block" data-error="metodo_pago_id"><?= e($errores['metodo_pago_id'] ?? '') ?></div>
                </div>
                <div class="col-12">
                    <button class="btn btn-seguridad w-100" type="submit" id="boton-pago" <?= $bloqueado ? 'disabled' : '' ?>>Registrar pago</button>
                </div>
            </div>
        </form>
    </div>

    <div class="col-xl-7">
        <h2 class="h4 mb-3">Historial de pagos recientes</h2>
        <div class="tarjeta-tabla">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Socio</th><th>Plan y periodo</th><th>Método</th><th class="text-end">Monto</th></tr></thead>
                    <tbody id="pagos-recientes">
                    <?php foreach ($recientes as $p): ?>
                        <tr>
                            <td><?= e($p['socio']) ?><span class="d-block small text-body-secondary">#<?= $p['socio_id'] ?>, pagó el <?= e($p['fecha']) ?></span></td>
                            <td><?= e($p['plan']) ?><span class="d-block small text-body-secondary"><?= e($p['periodo']) ?></span></td>
                            <td><?= e($p['metodo']) ?><span class="d-block small text-body-secondary">Registró: <?= e($p['registro']) ?></span></td>
                            <td class="text-end fw-semibold"><?= e($p['monto']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recientes): ?><tr><td colspan="4" class="text-body-secondary">Aún no hay pagos.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/panel_fin.php';
?>
