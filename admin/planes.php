<?php
/**
 * Gestión de planes (CRUD) — permiso planes.gestionar.
 *
 *   Listado:   admin/planes.php
 *   Nuevo:     admin/planes.php?nuevo=1
 *   Editar:    admin/planes.php?editar=ID
 *   Eliminar:  POST accion=eliminar
 *
 * Cada plan tiene nombre, precio, duración en días, descripción, si se
 * muestra en el sitio, y sus beneficios (tabla intermedia plan_beneficio).
 * Un plan con membresías registradas no se puede borrar (llave foránea
 * RESTRICT): en ese caso se oculta para conservar el historial.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/planes.php';
require_once ROOT_PATH . '/app/membresias.php';

$yo = requiere_permiso('planes.gestionar');
$errores = [];

// ---------------------------------------------------------------------
// Acciones (POST)
// ---------------------------------------------------------------------
if (es_post()) {
    csrf_verificar();
    $accion = entrada('accion');

    if ($accion === 'guardar') {
        $id = (int) entrada('id');
        $datos = [
            'nombre_plan'   => entrada('nombre_plan'),
            'precio'        => str_replace(',', '', entrada('precio')),
            'duracion_dias' => entrada('duracion_dias'),
            'descripcion'   => entrada('descripcion'),
            'activo'        => isset($_POST['activo']) ? 1 : 0,
        ];
        $beneficiosElegidos = array_map('intval', (array) ($_POST['beneficios'] ?? []));
        $beneficioNuevo = entrada('beneficio_nuevo');

        if ($datos['nombre_plan'] === '' || mb_strlen($datos['nombre_plan']) > 60) {
            $errores['nombre_plan'] = 'Escribe el nombre del plan (máximo 60 caracteres).';
        } elseif (valor('SELECT COUNT(*) FROM planes WHERE nombre_plan = ? AND id <> ?', [$datos['nombre_plan'], $id]) > 0) {
            $errores['nombre_plan'] = 'Ya existe un plan con ese nombre.';
        }
        if (!is_numeric($datos['precio']) || $datos['precio'] < 0 || $datos['precio'] > 999999.99) {
            $errores['precio'] = 'Escribe un precio válido, por ejemplo 450 o 450.50.';
        }
        if (!ctype_digit($datos['duracion_dias']) || $datos['duracion_dias'] < 1 || $datos['duracion_dias'] > 3650) {
            $errores['duracion_dias'] = 'La duración va de 1 a 3650 días.';
        }
        if (mb_strlen($datos['descripcion']) > 255) {
            $errores['descripcion'] = 'La descripción admite máximo 255 caracteres.';
        }
        if (mb_strlen($beneficioNuevo) > 120) {
            $errores['beneficio_nuevo'] = 'El beneficio admite máximo 120 caracteres.';
        }

        if (!$errores) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $valores = [
                    $datos['nombre_plan'], round((float) $datos['precio'], 2), (int) $datos['duracion_dias'],
                    $datos['descripcion'] !== '' ? $datos['descripcion'] : null, $datos['activo'],
                ];
                if ($id > 0) {
                    consulta('UPDATE planes SET nombre_plan = ?, precio = ?, duracion_dias = ?, descripcion = ?, activo = ?,
                                     updated_at = NOW() WHERE id = ?', [...$valores, $id]);
                } else {
                    consulta('INSERT INTO planes (nombre_plan, precio, duracion_dias, descripcion, activo, created_at, updated_at)
                              VALUES (?, ?, ?, ?, ?, NOW(), NOW())', $valores);
                    $id = (int) $pdo->lastInsertId();
                }

                // Beneficio nuevo escrito a mano: se crea (o se reutiliza si ya existe) y se marca
                if ($beneficioNuevo !== '') {
                    $idBeneficio = valor('SELECT id FROM beneficios WHERE descripcion = ?', [$beneficioNuevo]);
                    if (!$idBeneficio) {
                        consulta('INSERT INTO beneficios (descripcion, created_at, updated_at) VALUES (?, NOW(), NOW())', [$beneficioNuevo]);
                        $idBeneficio = $pdo->lastInsertId();
                    }
                    $beneficiosElegidos[] = (int) $idBeneficio;
                }

                // Sincroniza la tabla intermedia: se borran y se vuelven a insertar los marcados
                consulta('DELETE FROM plan_beneficio WHERE plan_id = ?', [$id]);
                $insertar = $pdo->prepare('INSERT IGNORE INTO plan_beneficio (plan_id, beneficio_id)
                                           SELECT ?, id FROM beneficios WHERE id = ?');
                foreach (array_unique($beneficiosElegidos) as $idB) {
                    $insertar->execute([$id, $idB]);
                }

                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }

            limpiar_viejo();
            flash('success', 'Plan "' . $datos['nombre_plan'] . '" guardado.');
            redirigir('admin/planes.php');
        }

        guardar_viejo($datos + ['beneficios' => $beneficiosElegidos, 'beneficio_nuevo' => $beneficioNuevo]);
        $_GET[$id > 0 ? 'editar' : 'nuevo'] = $id > 0 ? (string) $id : '1';
    }

    if ($accion === 'eliminar') {
        $id = (int) entrada('id');
        $plan = fila('SELECT nombre_plan FROM planes WHERE id = ?', [$id]);
        if ($plan) {
            try {
                consulta('DELETE FROM planes WHERE id = ?', [$id]);
                flash('success', 'Plan "' . $plan['nombre_plan'] . '" eliminado.');
            } catch (PDOException $ex) {
                if (!es_error_llave_foranea($ex)) {
                    throw $ex;
                }
                consulta('UPDATE planes SET activo = 0, updated_at = NOW() WHERE id = ?', [$id]);
                flash('warning', 'El plan "' . $plan['nombre_plan'] . '" tiene membresías registradas, así que no se puede '
                    . 'borrar sin perder ese historial. Se ocultó del sitio y ya no se podrá contratar.');
            }
        }
        redirigir('admin/planes.php');
    }

    if ($accion === 'eliminar_beneficio') {
        $id = (int) entrada('id');
        $b = fila('SELECT descripcion FROM beneficios WHERE id = ?', [$id]);
        if ($b) {
            consulta('DELETE FROM beneficios WHERE id = ?', [$id]); // plan_beneficio se borra en cascada
            flash('success', 'Beneficio "' . $b['descripcion'] . '" eliminado de todos los planes.');
        }
        redirigir('admin/planes.php');
    }
}

// ---------------------------------------------------------------------
// Datos para la vista
// ---------------------------------------------------------------------
$modo = isset($_GET['nuevo']) ? 'nuevo' : (isset($_GET['editar']) ? 'editar' : 'lista');
$beneficios = filas('SELECT b.id, b.descripcion, COUNT(pb.plan_id) AS planes
                       FROM beneficios b LEFT JOIN plan_beneficio pb ON pb.beneficio_id = b.id
                      GROUP BY b.id, b.descripcion ORDER BY b.descripcion');

if ($modo === 'lista') {
    $planes = filas(
        'SELECT p.*,
                (SELECT COUNT(*) FROM plan_beneficio pb WHERE pb.plan_id = p.id) AS num_beneficios,
                (SELECT COUNT(*) FROM membresias m WHERE m.plan_id = p.id AND ' . SQL_VIGENTE . ') AS vigentes
           FROM planes p
          ORDER BY p.activo DESC, p.precio ASC'
    );
    $colores = [];
    foreach (planes_activos() as $pa) {
        $colores[$pa['id']] = $pa['color'];
    }
} else {
    $plan = ['id' => 0, 'nombre_plan' => '', 'precio' => '', 'duracion_dias' => '30', 'descripcion' => '', 'activo' => 1];
    $marcados = [];
    if ($modo === 'editar') {
        $plan = fila('SELECT * FROM planes WHERE id = ?', [(int) $_GET['editar']]);
        if (!$plan) {
            flash('warning', 'Ese plan no existe.');
            redirigir('admin/planes.php');
        }
        $marcados = consulta('SELECT beneficio_id FROM plan_beneficio WHERE plan_id = ?', [$plan['id']])->fetchAll(PDO::FETCH_COLUMN);
    }
    // Si hubo errores, se muestran los valores que escribió el usuario
    if (isset($_SESSION['viejo'])) {
        $plan = array_merge($plan, array_intersect_key($_SESSION['viejo'], $plan));
        $marcados = $_SESSION['viejo']['beneficios'] ?? $marcados;
    }
    $marcados = array_map('intval', $marcados);
}

$invalido = fn(string $c) => isset($errores[$c]) ? ' is-invalid' : '';
$error = fn(string $c) => isset($errores[$c]) ? '<div class="invalid-feedback">' . e($errores[$c]) . '</div>' : '';

$titulo = $modo === 'lista' ? 'Gestión de planes' : ($modo === 'nuevo' ? 'Nuevo plan' : 'Editar plan');
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<?php if ($modo === 'lista'): ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-body-secondary mb-0">Los planes activos aparecen en <a href="<?= url('planes.php') ?>" target="_blank">Planes y precios</a> y se pueden contratar en recepción.</p>
        <a class="btn btn-seguridad" href="<?= url('admin/planes.php?nuevo=1') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Agregar nuevo plan</a>
    </div>

    <div class="tarjeta-tabla mb-4">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th><th>Plan</th><th class="text-end">Precio</th><th>Duración</th>
                        <th class="text-center">Beneficios</th><th class="text-center">Socios activos</th><th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$planes): ?>
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">No hay planes. Agrega el primero.</td></tr>
                <?php endif; ?>
                <?php foreach ($planes as $p): ?>
                    <tr class="<?= $p['activo'] ? '' : 'text-body-secondary' ?>">
                        <td><?= str_pad((string) $p['id'], 2, '0', STR_PAD_LEFT) ?></td>
                        <td>
                            <?php if (isset($colores[$p['id']])): ?>
                                <span class="punto disco-<?= e($colores[$p['id']]) ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                            <strong><?= e($p['nombre_plan']) ?></strong>
                            <?php if ($p['descripcion']): ?><span class="d-block small text-body-secondary"><?= e($p['descripcion']) ?></span><?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold"><?= e(dinero($p['precio'])) ?></td>
                        <td><?= e(duracion_texto((int) $p['duracion_dias'])) ?><span class="d-block small text-body-secondary"><?= (int) $p['duracion_dias'] ?> días</span></td>
                        <td class="text-center"><?= (int) $p['num_beneficios'] ?></td>
                        <td class="text-center"><?= (int) $p['vigentes'] ?></td>
                        <td><?= $p['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Oculto</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/planes.php?editar=' . $p['id']) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                            <form method="post" class="d-inline"
                                  data-confirmar="¿Eliminar el plan &quot;<?= e($p['nombre_plan']) ?>&quot;? Si tiene membresías registradas sólo se ocultará."
                                  data-boton="Eliminar plan">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <h2 class="h4">Catálogo de beneficios</h2>
    <p class="text-body-secondary">Los beneficios se asignan a cada plan al editarlo. Si eliminas uno, se quita de todos los planes.</p>
    <div class="tarjeta-tabla" style="max-width: 640px">
        <table class="table mb-0 align-middle">
            <thead><tr><th>Beneficio</th><th class="text-center">Planes que lo incluyen</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($beneficios as $b): ?>
                <tr>
                    <td><?= e($b['descripcion']) ?></td>
                    <td class="text-center"><?= (int) $b['planes'] ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline"
                              data-confirmar="¿Eliminar el beneficio &quot;<?= e($b['descripcion']) ?>&quot; de todos los planes?" data-boton="Eliminar beneficio">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar_beneficio">
                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                            <button class="btn btn-sm btn-link text-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$beneficios): ?>
                <tr><td colspan="3" class="text-body-secondary">Aún no hay beneficios. Agrégalos desde el formulario de un plan.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>

    <p><a href="<?= url('admin/planes.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a planes</a></p>

    <form method="post" action="<?= url('admin/planes.php') ?>" class="tarjeta" style="max-width: 820px" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="nombre_plan">Nombre del plan</label>
                <input class="form-control<?= $invalido('nombre_plan') ?>" id="nombre_plan" name="nombre_plan"
                       value="<?= e($plan['nombre_plan']) ?>" maxlength="60" required>
                <?= $error('nombre_plan') ?>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="precio">Precio (MXN)</label>
                <div class="input-group has-validation">
                    <span class="input-group-text">$</span>
                    <input class="form-control<?= $invalido('precio') ?>" id="precio" name="precio" type="number"
                           min="0" max="999999.99" step="0.01" value="<?= e($plan['precio']) ?>" required>
                    <?= $error('precio') ?>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="duracion_dias">Duración (días)</label>
                <input class="form-control<?= $invalido('duracion_dias') ?>" id="duracion_dias" name="duracion_dias" type="number"
                       min="1" max="3650" step="1" value="<?= e($plan['duracion_dias']) ?>" required list="duraciones" aria-describedby="ayuda-duracion">
                <datalist id="duraciones">
                    <option value="30">1 mes</option><option value="90">3 meses</option>
                    <option value="180">6 meses</option><option value="365">1 año</option>
                </datalist>
                <?= $error('duracion_dias') ?>
                <div id="ayuda-duracion" class="form-text">30 = mensual, 365 = anual.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="descripcion">Descripción corta <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <input class="form-control<?= $invalido('descripcion') ?>" id="descripcion" name="descripcion"
                       value="<?= e($plan['descripcion']) ?>" maxlength="255">
                <?= $error('descripcion') ?>
            </div>

            <div class="col-12">
                <fieldset>
                    <legend class="form-label fs-6 fw-medium">Beneficios incluidos</legend>
                    <div class="row row-cols-1 row-cols-sm-2 g-2">
                        <?php foreach ($beneficios as $b): ?>
                            <div class="col">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="beneficios[]" value="<?= (int) $b['id'] ?>"
                                           id="b<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $marcados, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="b<?= (int) $b['id'] ?>"><?= e($b['descripcion']) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <label class="form-label mt-3" for="beneficio_nuevo">Agregar un beneficio nuevo <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <input class="form-control<?= $invalido('beneficio_nuevo') ?>" id="beneficio_nuevo" name="beneficio_nuevo"
                       value="<?= viejo('beneficio_nuevo') ?>" maxlength="120" placeholder="Por ejemplo: Asesoría nutricional">
                <?= $error('beneficio_nuevo') ?>
            </div>

            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= $plan['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Plan activo: se muestra en el sitio y se puede contratar</label>
                </div>
            </div>

            <?php if ($modo === 'editar'): ?>
                <div class="col-12">
                    <p class="small text-body-secondary mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i>
                        Cambiar el precio o la duración sólo afecta a inscripciones y renovaciones nuevas; los pagos ya registrados no cambian.</p>
                </div>
            <?php endif; ?>

            <div class="col-12 d-flex gap-2">
                <button class="btn btn-seguridad" type="submit"><?= $modo === 'nuevo' ? 'Agregar plan' : 'Guardar cambios' ?></button>
                <a class="btn btn-outline-secondary" href="<?= url('admin/planes.php') ?>">Cancelar</a>
            </div>
        </div>
    </form>

<?php endif; ?>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/panel_fin.php';
?>
