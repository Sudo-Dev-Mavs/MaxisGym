<?php
/**
 * Gestión de socios — permiso socios.gestionar.
 *
 *   Listado con búsqueda por nombre, correo o ID y paginación: admin/socios.php
 *   Editar datos de un socio:                                   admin/socios.php?editar=ID
 *   Eliminar / dar de baja / reactivar:                        POST accion=...
 *
 * Los socios nuevos se registran en "Alta de socios" (con su plan y su primer pago).
 * Si un socio tiene pagos, MySQL no permite borrarlo (pagos → membresias es
 * RESTRICT); en ese caso se DA DE BAJA (activo = 0) para conservar el historial.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/validacion.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/socios.php';

$yo = requiere_permiso('socios.gestionar');
$errores = [];
const POR_PAGINA = 10;

// Parámetros de la lista, para regresar al mismo lugar después de una acción
$filtros = array_filter([
    'q'      => parametro('q'),
    'estado' => in_array(parametro('estado'), ['activos', 'baja', 'todos'], true) ? parametro('estado') : 'activos',
    'pagina' => parametro('pagina'),
], fn($v) => $v !== '');
$volver = 'admin/socios.php?' . http_build_query($filtros);

if (es_post()) {
    csrf_verificar();
    $accion = entrada('accion');
    $id = (int) entrada('id');
    $socio = socio_por_id($id);
    $volverPost = entrada('volver', 'admin/socios.php');
    if (!str_starts_with($volverPost, 'admin/socios.php')) {
        $volverPost = 'admin/socios.php';
    }

    if (!$socio) {
        flash('warning', 'Ese socio no existe.');
        redirigir('admin/socios.php');
    }
    $nombre = $socio['nombre'] . ' ' . $socio['apellido'];

    if ($accion === 'guardar') {
        $datos = datos_persona_post();
        $datos['activo'] = isset($_POST['activo']) ? 1 : 0;
        $errores = validar_persona($datos, $id);

        if (!$errores) {
            consulta(
                'UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, telefono = ?, fecha_nacimiento = ?, activo = ?,
                        remember_token = IF(? = 0, NULL, remember_token), updated_at = NOW()
                  WHERE id = ?',
                [
                    $datos['nombre'], $datos['apellido'], $datos['email'],
                    $datos['telefono'] !== '' ? $datos['telefono'] : null,
                    $datos['fecha_nacimiento'] !== '' ? $datos['fecha_nacimiento'] : null,
                    $datos['activo'], $datos['activo'], $id,
                ]
            );
            limpiar_viejo();
            flash('success', 'Datos de ' . $datos['nombre'] . ' ' . $datos['apellido'] . ' guardados.');
            redirigir($volverPost);
        }
        guardar_viejo($datos);
        $_GET['editar'] = (string) $id;
    }

    if ($accion === 'eliminar') {
        try {
            consulta('DELETE FROM usuarios WHERE id = ?', [$id]);
            flash('success', "Socio $nombre eliminado.");
        } catch (PDOException $ex) {
            if (!es_error_llave_foranea($ex)) {
                throw $ex;
            }
            consulta('UPDATE usuarios SET activo = 0, remember_token = NULL, updated_at = NOW() WHERE id = ?', [$id]);
            flash('warning', "$nombre tiene pagos registrados, así que no se puede borrar sin perder ese historial. "
                . 'Se dio de baja: ya no puede iniciar sesión y no aparece en la lista de activos.');
        }
        redirigir($volverPost);
    }

    if ($accion === 'reactivar') {
        consulta('UPDATE usuarios SET activo = 1, updated_at = NOW() WHERE id = ?', [$id]);
        flash('success', "$nombre se reactivó y ya puede iniciar sesión.");
        redirigir($volverPost);
    }
}

// ---------------------------------------------------------------------
// Vista: edición
// ---------------------------------------------------------------------
if (isset($_GET['editar'])) {
    $socio = socio_por_id((int) $_GET['editar']);
    if (!$socio) {
        flash('warning', 'Ese socio no existe.');
        redirigir('admin/socios.php');
    }
    if (isset($_SESSION['viejo'])) {
        $socio = array_merge($socio, $_SESSION['viejo']);
    }
    $membresias = filas(
        'SELECT m.*, p.nombre_plan FROM membresias m JOIN planes p ON p.id = m.plan_id
          WHERE m.usuario_id = ? ORDER BY m.fecha_fin DESC',
        [$socio['id']]
    );
    $pagos = filas(
        'SELECT pg.monto, pg.fecha_pago, mp.nombre AS metodo, p.nombre_plan
           FROM pagos pg JOIN membresias m ON m.id = pg.membresia_id
           JOIN planes p ON p.id = m.plan_id JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
          WHERE m.usuario_id = ? ORDER BY pg.fecha_pago DESC LIMIT 10',
        [$socio['id']]
    );

    $titulo = 'Editar socio';
    require ROOT_PATH . '/app/vistas/panel_inicio.php';
    ?>
    <p><a href="<?= url($volver) ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a socios</a></p>
    <div class="row g-4">
        <div class="col-xl-6">
            <form method="post" action="<?= url($volver) ?>" class="tarjeta" novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id" value="<?= (int) $socio['id'] ?>">
                <input type="hidden" name="volver" value="<?= e($volver) ?>">
                <h2 class="h4 mb-3">Datos del socio <span class="text-body-secondary fw-normal">#<?= (int) $socio['id'] ?></span></h2>
                <div class="row g-3">
                    <?php foreach (['nombre' => 'Nombre', 'apellido' => 'Apellido'] as $campo => $etiqueta): ?>
                        <div class="col-sm-6">
                            <label class="form-label" for="<?= $campo ?>"><?= $etiqueta ?></label>
                            <input class="form-control<?= clase_invalida($errores, $campo) ?>" id="<?= $campo ?>" name="<?= $campo ?>"
                                   value="<?= e($socio[$campo]) ?>" maxlength="60" required>
                            <?= mensaje_error($errores, $campo) ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-12">
                        <label class="form-label" for="email">Correo</label>
                        <input class="form-control<?= clase_invalida($errores, 'email') ?>" id="email" name="email" type="email"
                               value="<?= e($socio['email']) ?>" maxlength="120" required>
                        <?= mensaje_error($errores, 'email') ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="telefono">Teléfono</label>
                        <input class="form-control<?= clase_invalida($errores, 'telefono') ?>" id="telefono" name="telefono" type="tel"
                               value="<?= e($socio['telefono']) ?>" maxlength="20">
                        <?= mensaje_error($errores, 'telefono') ?>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="fecha_nacimiento">Fecha de nacimiento</label>
                        <input class="form-control<?= clase_invalida($errores, 'fecha_nacimiento') ?>" id="fecha_nacimiento"
                               name="fecha_nacimiento" type="date" value="<?= e($socio['fecha_nacimiento']) ?>" max="<?= date('Y-m-d') ?>">
                        <?= mensaje_error($errores, 'fecha_nacimiento') ?>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"
                                <?= $socio['activo'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Cuenta activa (si la desactivas, no podrá iniciar sesión)</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-seguridad" type="submit">Guardar cambios</button>
                        <a class="btn btn-outline-secondary" href="<?= url($volver) ?>">Cancelar</a>
                    </div>
                </div>
            </form>
        </div>
        <div class="col-xl-6">
            <h2 class="h4">Membresías</h2>
            <div class="tarjeta-tabla mb-4">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Plan</th><th>Inicio</th><th>Fin</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($membresias as $m): ?>
                        <tr><td><?= e($m['nombre_plan']) ?></td><td><?= e(fecha($m['fecha_inicio'])) ?></td>
                            <td><?= e(fecha($m['fecha_fin'])) ?></td><td><?= insignia_estado(estado_membresia($m)) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$membresias): ?><tr><td colspan="4" class="text-body-secondary">Sin membresías registradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <h2 class="h4">Últimos pagos</h2>
            <div class="tarjeta-tabla">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Fecha</th><th>Plan</th><th>Método</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                    <?php foreach ($pagos as $p): ?>
                        <tr><td><?= e(fecha($p['fecha_pago'])) ?></td><td><?= e($p['nombre_plan']) ?></td>
                            <td><?= e($p['metodo']) ?></td><td class="text-end"><?= e(dinero($p['monto'])) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$pagos): ?><tr><td colspan="4" class="text-body-secondary">Sin pagos registrados.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    limpiar_viejo();
    require ROOT_PATH . '/app/vistas/panel_fin.php';
    exit;
}

// ---------------------------------------------------------------------
// Vista: listado
// ---------------------------------------------------------------------
$pagina = pagina_actual_num();
$resultado = buscar_socios($filtros['q'] ?? '', $filtros['estado'], POR_PAGINA, ($pagina - 1) * POR_PAGINA);
$socios = $resultado['socios'];
$total = $resultado['total'];

$titulo = 'Gestión de socios';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <form method="get" action="<?= url('admin/socios.php') ?>" class="d-flex flex-wrap gap-2 align-items-end" role="search">
        <div>
            <label class="form-label" for="q">Buscar por nombre, correo o ID</label>
            <input class="form-control" id="q" name="q" value="<?= e($filtros['q'] ?? '') ?>" placeholder="Ej. Juan Pérez o 3" style="min-width: 260px">
        </div>
        <div>
            <label class="form-label" for="estado">Mostrar</label>
            <select class="form-select" id="estado" name="estado">
                <option value="activos" <?= $filtros['estado'] === 'activos' ? 'selected' : '' ?>>Socios activos</option>
                <option value="baja" <?= $filtros['estado'] === 'baja' ? 'selected' : '' ?>>Dados de baja</option>
                <option value="todos" <?= $filtros['estado'] === 'todos' ? 'selected' : '' ?>>Todos</option>
            </select>
        </div>
        <button class="btn btn-outline-dark" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Buscar</button>
        <?php if (!empty($filtros['q'])): ?>
            <a class="btn btn-link" href="<?= url('admin/socios.php?estado=' . $filtros['estado']) ?>">Limpiar</a>
        <?php endif; ?>
    </form>
    <?php if (tiene_permiso('socios.alta')): ?>
        <a class="btn btn-seguridad" href="<?= url('empleado/alta_socio.php') ?>"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Agregar nuevo socio</a>
    <?php endif; ?>
</div>

<div class="tarjeta-tabla mb-3">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Membresía</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (!$socios): ?>
                <tr><td colspan="6" class="text-center text-body-secondary py-4">
                    <?= !empty($filtros['q']) ? 'Ningún socio coincide con "' . e($filtros['q']) . '".' : 'No hay socios en esta lista.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($socios as $s): ?>
                <tr class="<?= $s['activo'] ? '' : 'text-body-secondary' ?>">
                    <td><?= str_pad((string) $s['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td><strong><?= e($s['nombre'] . ' ' . $s['apellido']) ?></strong>
                        <?php if (!$s['activo']): ?><span class="badge text-bg-secondary ms-1">Baja</span><?php endif; ?></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['telefono'] ?? '—') ?></td>
                    <td>
                        <?= insignia_estado($s['estado_membresia']) ?>
                        <?php if ($s['nombre_plan']): ?>
                            <span class="d-block small text-body-secondary"><?= e($s['nombre_plan']) ?>, hasta <?= e(fecha($s['fecha_fin'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/socios.php?' . http_build_query($filtros + ['editar' => $s['id']])) ?>">
                            <i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                        <?php if ($s['activo']): ?>
                            <form method="post" class="d-inline" data-boton="Eliminar socio"
                                  data-confirmar="¿Eliminar a <?= e($s['nombre'] . ' ' . $s['apellido']) ?>? Si tiene pagos registrados sólo se dará de baja.">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="volver" value="<?= e($volver) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                            </form>
                        <?php else: ?>
                            <form method="post" class="d-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="reactivar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <input type="hidden" name="volver" value="<?= e($volver) ?>">
                                <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Reactivar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
    <p class="text-body-secondary small mb-0"><?= $total ?> <?= $total === 1 ? 'socio' : 'socios' ?> en esta lista.</p>
    <?= paginacion($total, POR_PAGINA, $pagina, 'admin/socios.php', array_diff_key($filtros, ['pagina' => 1])) ?>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
