<?php
/**
 * Roles y permisos — permiso empleados.gestionar.
 *
 * Aquí se administra el CATÁLOGO de roles (tabla roles) y qué permisos tiene
 * cada uno (tabla intermedia rol_permiso). Crear un rol nuevo, por ejemplo
 * "entrenador", o cambiar lo que puede hacer un rol, es sólo cambiar registros:
 * no se modifica la estructura de la base de datos ni el código, porque todas
 * las páginas revisan permisos (requiere_permiso), no nombres de rol.
 *
 * Los permisos en sí son fijos: cada uno corresponde a una función programada.
 *
 * Reglas para no romper el sistema:
 *   - Tu propio rol no puede perder "empleados.gestionar" (te quedarías fuera).
 *   - El rol "socio" lo usa el registro público y debe conservar "membresia.ver".
 *   - Un rol con usuarios no se puede borrar (llave foránea RESTRICT).
 */
require __DIR__ . '/../app/bootstrap.php';

$yo = requiere_permiso('empleados.gestionar');
$errores = [];

$permisos = filas('SELECT id, clave, descripcion FROM permisos ORDER BY clave');
$idsPermisos = array_map('intval', array_column($permisos, 'id'));
$idPermiso = array_column($permisos, 'id', 'clave');

if (es_post()) {
    csrf_verificar();
    $accion = entrada('accion');

    // Guardar la matriz completa de permisos
    if ($accion === 'permisos') {
        $roles = filas('SELECT id, nombre FROM roles');
        $marcados = (array) ($_POST['permisos'] ?? []);
        $nuevos = [];
        foreach ($roles as $r) {
            $lista = array_map('intval', (array) ($marcados[$r['id']] ?? []));
            $nuevos[(int) $r['id']] = array_values(array_intersect($lista, $idsPermisos));
        }

        if (!in_array((int) $idPermiso['empleados.gestionar'], $nuevos[(int) $yo['rol_id']] ?? [], true)) {
            $errores[] = 'Tu propio rol debe conservar el permiso "empleados.gestionar"; si no, nadie podría volver a esta pantalla.';
        }
        $rolSocio = rol_id('socio');
        if ($rolSocio && !in_array((int) $idPermiso['membresia.ver'], $nuevos[$rolSocio] ?? [], true)) {
            $errores[] = 'El rol "socio" debe conservar el permiso "membresia.ver": es el que identifica a los socios.';
        }

        if (!$errores) {
            $pdo = db();
            $pdo->beginTransaction();
            $insertar = $pdo->prepare('INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)');
            foreach ($nuevos as $rolId => $lista) {
                consulta('DELETE FROM rol_permiso WHERE rol_id = ?', [$rolId]);
                foreach ($lista as $pid) {
                    $insertar->execute([$rolId, $pid]);
                }
            }
            $pdo->commit();
            flash('success', 'Permisos guardados. Los cambios aplican desde la siguiente página que abra cada usuario.');
            redirigir('admin/roles.php');
        }
    }

    // Crear un rol nuevo
    if ($accion === 'crear') {
        $nombre = mb_strtolower(entrada('nombre'));
        $descripcion = entrada('descripcion');
        $copiarDe = (int) entrada('copiar_de');

        if (!preg_match('/^[a-záéíóúñ ]{3,40}$/u', $nombre)) {
            $errores['nombre'] = 'El nombre debe tener de 3 a 40 letras minúsculas, por ejemplo "entrenador".';
        } elseif (valor('SELECT COUNT(*) FROM roles WHERE nombre = ?', [$nombre]) > 0) {
            $errores['nombre'] = 'Ya existe un rol con ese nombre.';
        }
        if (mb_strlen($descripcion) > 150) {
            $errores['descripcion'] = 'La descripción admite máximo 150 caracteres.';
        }

        if (!$errores) {
            $pdo = db();
            $pdo->beginTransaction();
            consulta('INSERT INTO roles (nombre, descripcion, created_at, updated_at) VALUES (?, ?, NOW(), NOW())',
                [$nombre, $descripcion !== '' ? $descripcion : null]);
            $nuevoId = (int) $pdo->lastInsertId();
            if ($copiarDe > 0) {
                consulta('INSERT INTO rol_permiso (rol_id, permiso_id) SELECT ?, permiso_id FROM rol_permiso WHERE rol_id = ?',
                    [$nuevoId, $copiarDe]);
            }
            $pdo->commit();
            flash('success', 'Rol "' . $nombre . '" creado. Ajusta sus permisos en la tabla y asígnalo en Gestión de empleados.');
            redirigir('admin/roles.php');
        }
        guardar_viejo(['nombre' => $nombre, 'descripcion' => $descripcion]);
    }

    // Eliminar un rol
    if ($accion === 'eliminar') {
        $id = (int) entrada('id');
        $rol = fila('SELECT nombre FROM roles WHERE id = ?', [$id]);
        if ($rol === null) {
            flash('warning', 'Ese rol no existe.');
        } elseif (in_array($rol['nombre'], ['socio'], true)) {
            flash('danger', 'El rol "socio" no se puede eliminar: lo usa el registro público.');
        } elseif ($id === (int) $yo['rol_id']) {
            flash('danger', 'No puedes eliminar tu propio rol.');
        } else {
            try {
                consulta('DELETE FROM roles WHERE id = ?', [$id]); // rol_permiso se borra en cascada
                flash('success', 'Rol "' . $rol['nombre'] . '" eliminado.');
            } catch (PDOException $ex) {
                if (!es_error_llave_foranea($ex)) {
                    throw $ex;
                }
                flash('warning', 'El rol "' . $rol['nombre'] . '" tiene usuarios asignados. Cámbiales el rol en Gestión de empleados y luego elimínalo.');
            }
        }
        redirigir('admin/roles.php');
    }
}

$roles = filas(
    'SELECT r.id, r.nombre, r.descripcion, COUNT(u.id) AS usuarios
       FROM roles r LEFT JOIN usuarios u ON u.rol_id = r.id
      GROUP BY r.id, r.nombre, r.descripcion
      ORDER BY r.id'
);
$asignados = [];
foreach (filas('SELECT rol_id, permiso_id FROM rol_permiso') as $rp) {
    $asignados[$rp['rol_id']][$rp['permiso_id']] = true;
}

$titulo = 'Roles y permisos';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<p class="text-body-secondary" style="max-width: 70ch">
    Cada rol es una combinación de permisos. Marca qué puede hacer cada uno y guarda;
    para crear un rol nuevo, usa el formulario de abajo y después asígnalo en
    <a href="<?= url('admin/empleados.php') ?>">Gestión de empleados</a>.
</p>

<?php foreach ($errores as $clave => $msg): ?>
    <?php if (is_int($clave)): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>
<?php endforeach; ?>

<form method="post" action="<?= url('admin/roles.php') ?>">
    <?= csrf_campo() ?>
    <input type="hidden" name="accion" value="permisos">
    <div class="tarjeta-tabla mb-3">
        <div class="table-responsive">
            <table class="table mb-0 align-middle matriz-permisos">
                <thead>
                    <tr>
                        <th scope="col">Permiso</th>
                        <?php foreach ($roles as $r): ?>
                            <th scope="col" class="text-center">
                                <?= e(ucfirst($r['nombre'])) ?>
                                <span class="d-block small fw-normal text-body-secondary"><?= (int) $r['usuarios'] ?> <?= (int) $r['usuarios'] === 1 ? 'usuario' : 'usuarios' ?></span>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($permisos as $p): ?>
                    <tr>
                        <th scope="row" class="fw-normal">
                            <?= e($p['descripcion']) ?>
                            <code class="d-block small"><?= e($p['clave']) ?></code>
                        </th>
                        <?php foreach ($roles as $r): ?>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox" name="permisos[<?= (int) $r['id'] ?>][]"
                                       value="<?= (int) $p['id'] ?>" <?= !empty($asignados[$r['id']][$p['id']]) ? 'checked' : '' ?>
                                       aria-label="<?= e(ucfirst($r['nombre']) . ': ' . $p['descripcion']) ?>">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <button class="btn btn-seguridad mb-5" type="submit">Guardar permisos</button>
</form>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" action="<?= url('admin/roles.php') ?>" class="tarjeta" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="crear">
            <h2 class="h4 mb-3">Nuevo rol</h2>
            <div class="mb-3">
                <label class="form-label" for="nombre">Nombre</label>
                <input class="form-control<?= isset($errores['nombre']) ? ' is-invalid' : '' ?>" id="nombre" name="nombre"
                       value="<?= viejo('nombre') ?>" maxlength="40" placeholder="entrenador" required>
                <?php if (isset($errores['nombre'])): ?><div class="invalid-feedback"><?= e($errores['nombre']) ?></div><?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="descripcion">Descripción <span class="text-body-secondary fw-normal">(opcional)</span></label>
                <input class="form-control<?= isset($errores['descripcion']) ? ' is-invalid' : '' ?>" id="descripcion" name="descripcion"
                       value="<?= viejo('descripcion') ?>" maxlength="150" placeholder="Instructores de piso y clases">
                <?php if (isset($errores['descripcion'])): ?><div class="invalid-feedback"><?= e($errores['descripcion']) ?></div><?php endif; ?>
            </div>
            <div class="mb-4">
                <label class="form-label" for="copiar_de">Empezar con los permisos de</label>
                <select class="form-select" id="copiar_de" name="copiar_de">
                    <option value="0">Ningún permiso</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>"><?= e(ucfirst($r['nombre'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline-dark" type="submit">Crear rol</button>
        </form>
    </div>
    <div class="col-lg-6">
        <h2 class="h4">Roles existentes</h2>
        <div class="tarjeta-tabla">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Rol</th><th class="text-center">Usuarios</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($roles as $r): ?>
                    <tr>
                        <td><strong><?= e(ucfirst($r['nombre'])) ?></strong>
                            <?php if ($r['descripcion']): ?><span class="d-block small text-body-secondary"><?= e($r['descripcion']) ?></span><?php endif; ?></td>
                        <td class="text-center"><?= (int) $r['usuarios'] ?></td>
                        <td class="text-end">
                            <?php if ($r['nombre'] !== 'socio' && (int) $r['id'] !== (int) $yo['rol_id']): ?>
                                <form method="post" class="d-inline" data-confirmar="¿Eliminar el rol &quot;<?= e($r['nombre']) ?>&quot;?" data-boton="Eliminar rol">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-link text-danger" type="submit">Eliminar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/panel_fin.php';
?>
