<?php
/**
 * Gestión de empleados (CRUD) — permiso empleados.gestionar.
 *
 *   Listado:   admin/empleados.php
 *   Nuevo:     admin/empleados.php?nuevo=1
 *   Editar:    admin/empleados.php?editar=ID
 *   Eliminar:  POST accion=eliminar
 *
 * "Personal" son los usuarios cuyo rol NO es de socio (no tiene membresia.ver).
 * El rol sale del catálogo de roles y el puesto es texto libre (Recepción,
 * Entrenador...). Reglas de seguridad:
 *   - Nadie puede eliminarse, darse de baja ni cambiarse el rol a sí mismo.
 *   - Siempre debe quedar al menos un usuario activo que pueda gestionar empleados.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/validacion.php';
require_once ROOT_PATH . '/app/socios.php';

$yo = requiere_permiso('empleados.gestionar');
$errores = [];
const POR_PAGINA_EMP = 10;
const SQL_ES_PERSONAL = 'NOT (' . SQL_ES_SOCIO . ')';

/** Roles del catálogo que son de personal (no de socio). */
function roles_de_personal(): array
{
    return filas(
        "SELECT r.id, r.nombre, r.descripcion FROM roles r
          WHERE r.activo = 1
            AND r.id NOT IN (SELECT rp.rol_id FROM rol_permiso rp JOIN permisos pe ON pe.id = rp.permiso_id
                              WHERE pe.clave = 'membresia.ver')
          ORDER BY r.nombre"
    );
}

/** ¿Quedaría al menos otro usuario activo con permiso para gestionar empleados? */
function quedan_otros_gestores(int $excepto): bool
{
    return valor(
        "SELECT COUNT(*) FROM usuarios u
          WHERE u.activo = 1 AND u.id <> ?
            AND u.rol_id IN (SELECT rp.rol_id FROM rol_permiso rp JOIN permisos pe ON pe.id = rp.permiso_id
                              WHERE pe.clave = 'empleados.gestionar')",
        [$excepto]
    ) > 0;
}

/** ¿El rol tiene el permiso de gestionar empleados? */
function rol_gestiona_empleados(int $rolId): bool
{
    return valor(
        "SELECT COUNT(*) FROM rol_permiso rp JOIN permisos pe ON pe.id = rp.permiso_id
          WHERE rp.rol_id = ? AND pe.clave = 'empleados.gestionar'",
        [$rolId]
    ) > 0;
}

function empleado_por_id(int $id): ?array
{
    return fila('SELECT u.* FROM usuarios u WHERE u.id = ? AND ' . SQL_ES_PERSONAL, [$id]);
}

$roles = roles_de_personal();
$idsRoles = array_map('intval', array_column($roles, 'id'));

// ---------------------------------------------------------------------
// Acciones
// ---------------------------------------------------------------------
if (es_post()) {
    csrf_verificar();
    $accion = entrada('accion');
    $id = (int) entrada('id');

    if ($accion === 'guardar') {
        $actual = $id > 0 ? empleado_por_id($id) : null;
        if ($id > 0 && !$actual) {
            flash('warning', 'Ese empleado no existe.');
            redirigir('admin/empleados.php');
        }

        $datos = datos_persona_post() + [
            'puesto' => entrada('puesto'),
            'rol_id' => (int) entrada('rol_id'),
            'activo' => isset($_POST['activo']) ? 1 : 0,
        ];
        $password = $_POST['password'] ?? '';
        $confirmacion = $_POST['password_confirmacion'] ?? '';

        $errores = validar_persona($datos, $id, true);
        if ($datos['puesto'] === '' || mb_strlen($datos['puesto']) > 50) {
            $errores['puesto'] = 'Escribe el puesto (máximo 50 caracteres), por ejemplo Recepción.';
        }
        if (!in_array($datos['rol_id'], $idsRoles, true)) {
            $errores['rol_id'] = 'Elige un rol de la lista.';
        }
        // La contraseña es obligatoria al crear; al editar sólo si se escribe una nueva
        if ($id === 0 || $password !== '') {
            if (!is_string($password) || !is_string($confirmacion)
                || ($msg = error_password($password, $confirmacion)) !== null) {
                $errores['password'] = $msg ?? 'Escribe una contraseña.';
            }
        }

        // Reglas para no quedarse sin administradores
        if ($actual) {
            $esYo = (int) $actual['id'] === (int) $yo['id'];
            if ($esYo && (int) $actual['rol_id'] !== $datos['rol_id']) {
                $errores['rol_id'] = 'No puedes cambiar tu propio rol.';
            }
            if ($esYo && !$datos['activo']) {
                $errores['activo'] = 'No puedes darte de baja a ti mismo.';
            }
            $pierdeGestion = rol_gestiona_empleados((int) $actual['rol_id'])
                && (!$datos['activo'] || !rol_gestiona_empleados($datos['rol_id']));
            if ($pierdeGestion && !quedan_otros_gestores((int) $actual['id'])) {
                $errores['rol_id'] = 'Es el único usuario que puede gestionar empleados; asigna ese permiso a alguien más antes.';
            }
        }

        if (!$errores) {
            $valores = [
                $datos['rol_id'], $datos['nombre'], $datos['apellido'], $datos['email'], $datos['telefono'],
                $datos['fecha_nacimiento'] !== '' ? $datos['fecha_nacimiento'] : null, $datos['puesto'], $datos['activo'],
            ];
            if ($id > 0) {
                consulta('UPDATE usuarios SET rol_id = ?, nombre = ?, apellido = ?, email = ?, telefono = ?, fecha_nacimiento = ?,
                                 puesto = ?, activo = ?, updated_at = NOW() WHERE id = ?', [...$valores, $id]);
                if ($password !== '') {
                    consulta('UPDATE usuarios SET password = ?, remember_token = NULL WHERE id = ?',
                        [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                if (!$datos['activo']) {
                    consulta('UPDATE usuarios SET remember_token = NULL WHERE id = ?', [$id]);
                }
            } else {
                consulta('INSERT INTO usuarios (rol_id, nombre, apellido, email, telefono, fecha_nacimiento, puesto, activo,
                                                password, created_at, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [...$valores, password_hash($password, PASSWORD_DEFAULT)]);
            }
            limpiar_viejo();
            flash('success', 'Empleado ' . $datos['nombre'] . ' ' . $datos['apellido'] . ' guardado.');
            redirigir('admin/empleados.php');
        }

        guardar_viejo($datos);
        $_GET[$id > 0 ? 'editar' : 'nuevo'] = $id > 0 ? (string) $id : '1';
    }

    if ($accion === 'eliminar') {
        $emp = empleado_por_id($id);
        if (!$emp) {
            flash('warning', 'Ese empleado no existe.');
        } elseif ((int) $emp['id'] === (int) $yo['id']) {
            flash('danger', 'No puedes eliminar tu propia cuenta.');
        } elseif (rol_gestiona_empleados((int) $emp['rol_id']) && !quedan_otros_gestores((int) $emp['id'])) {
            flash('danger', 'No se puede eliminar: es el único usuario que puede gestionar empleados.');
        } else {
            try {
                // pagos.registrado_por y mensajes atendidos quedan en NULL (SET NULL);
                // sus avisos publicados se borran (CASCADE)
                consulta('DELETE FROM usuarios WHERE id = ?', [$id]);
                flash('success', 'Empleado ' . $emp['nombre'] . ' ' . $emp['apellido'] . ' eliminado.');
            } catch (PDOException $ex) {
                if (!es_error_llave_foranea($ex)) {
                    throw $ex;
                }
                consulta('UPDATE usuarios SET activo = 0, remember_token = NULL WHERE id = ?', [$id]);
                flash('warning', 'No se pudo borrar por registros relacionados; se dio de baja.');
            }
        }
        redirigir('admin/empleados.php');
    }
}

// ---------------------------------------------------------------------
// Vista: formulario (nuevo / editar)
// ---------------------------------------------------------------------
if (isset($_GET['nuevo']) || isset($_GET['editar'])) {
    $esNuevo = isset($_GET['nuevo']);
    $emp = ['id' => 0, 'nombre' => '', 'apellido' => '', 'email' => '', 'telefono' => '', 'fecha_nacimiento' => '',
            'puesto' => '', 'rol_id' => rol_id('empleado') ?? ($idsRoles[0] ?? 0), 'activo' => 1];
    if (!$esNuevo) {
        $emp = empleado_por_id((int) $_GET['editar']);
        if (!$emp) {
            flash('warning', 'Ese empleado no existe.');
            redirigir('admin/empleados.php');
        }
    }
    if (isset($_SESSION['viejo'])) {
        $emp = array_merge($emp, $_SESSION['viejo']);
    }
    $esYo = !$esNuevo && (int) $emp['id'] === (int) $yo['id'];

    $titulo = $esNuevo ? 'Nuevo empleado' : 'Editar empleado';
    require ROOT_PATH . '/app/vistas/panel_inicio.php';
    ?>
    <p><a href="<?= url('admin/empleados.php') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a empleados</a></p>
    <form method="post" action="<?= url('admin/empleados.php') ?>" class="tarjeta" style="max-width: 860px" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) $emp['id'] ?>">
        <div class="row g-3">
            <?php foreach (['nombre' => 'Nombre', 'apellido' => 'Apellido'] as $c => $et): ?>
                <div class="col-sm-6">
                    <label class="form-label" for="<?= $c ?>"><?= $et ?></label>
                    <input class="form-control<?= clase_invalida($errores, $c) ?>" id="<?= $c ?>" name="<?= $c ?>" value="<?= e($emp[$c]) ?>" maxlength="60" required>
                    <?= mensaje_error($errores, $c) ?>
                </div>
            <?php endforeach; ?>
            <div class="col-sm-6">
                <label class="form-label" for="email">Correo</label>
                <input class="form-control<?= clase_invalida($errores, 'email') ?>" id="email" name="email" type="email" value="<?= e($emp['email']) ?>" maxlength="120" required>
                <?= mensaje_error($errores, 'email') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="telefono">Teléfono</label>
                <input class="form-control<?= clase_invalida($errores, 'telefono') ?>" id="telefono" name="telefono" type="tel" value="<?= e($emp['telefono']) ?>" maxlength="20" required>
                <?= mensaje_error($errores, 'telefono') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="puesto">Puesto</label>
                <input class="form-control<?= clase_invalida($errores, 'puesto') ?>" id="puesto" name="puesto" value="<?= e($emp['puesto']) ?>"
                       maxlength="50" required list="puestos" placeholder="Recepción, Entrenador...">
                <datalist id="puestos"><option value="Recepción"><option value="Entrenador"><option value="Gerente"><option value="Limpieza"></datalist>
                <?= mensaje_error($errores, 'puesto') ?>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="rol_id">Rol en el sistema</label>
                <select class="form-select<?= clase_invalida($errores, 'rol_id') ?>" id="rol_id" name="rol_id" <?= $esYo ? 'aria-describedby="ayuda-rol"' : '' ?>>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $emp['rol_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                            <?= e(ucfirst($r['nombre'])) ?><?= $r['descripcion'] ? ' (' . e($r['descripcion']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= mensaje_error($errores, 'rol_id') ?>
                <div class="form-text" id="ayuda-rol">El rol define qué secciones puede usar. <?= $esYo ? 'No puedes cambiar tu propio rol.' : '' ?></div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="password"><?= $esNuevo ? 'Contraseña inicial' : 'Nueva contraseña' ?>
                    <?php if (!$esNuevo): ?><span class="text-body-secondary fw-normal">(déjala vacía para no cambiarla)</span><?php endif; ?></label>
                <input class="form-control<?= clase_invalida($errores, 'password') ?>" id="password" name="password" type="password"
                       autocomplete="new-password" <?= $esNuevo ? 'required' : '' ?>>
                <?= mensaje_error($errores, 'password') ?>
                <div class="form-text">Mínimo 8 caracteres, con letras y números. El empleado puede cambiarla en Mi perfil.</div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="password_confirmacion">Confirmar contraseña</label>
                <input class="form-control" id="password_confirmacion" name="password_confirmacion" type="password" autocomplete="new-password">
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input<?= clase_invalida($errores, 'activo') ?>" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= $emp['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Cuenta activa (si la desactivas, no podrá iniciar sesión)</label>
                    <?= mensaje_error($errores, 'activo') ?>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-seguridad" type="submit"><?= $esNuevo ? 'Agregar empleado' : 'Guardar cambios' ?></button>
                <a class="btn btn-outline-secondary" href="<?= url('admin/empleados.php') ?>">Cancelar</a>
            </div>
        </div>
    </form>
    <?php
    limpiar_viejo();
    require ROOT_PATH . '/app/vistas/panel_fin.php';
    exit;
}

// ---------------------------------------------------------------------
// Vista: listado
// ---------------------------------------------------------------------
$q = parametro('q');
$pagina = pagina_actual_num();
$condicion = SQL_ES_PERSONAL;
$params = [];
if ($q !== '') {
    if (ctype_digit($q)) {
        $condicion .= ' AND u.id = ?';
        $params[] = (int) $q;
    } else {
        $condicion .= " AND (CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.email LIKE ? OR u.puesto LIKE ?)";
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
}
$total = (int) valor("SELECT COUNT(*) FROM usuarios u WHERE $condicion", $params);
$empleados = filas(
    "SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.puesto, u.activo, r.nombre AS rol
       FROM usuarios u JOIN roles r ON r.id = u.rol_id
      WHERE $condicion
      ORDER BY u.activo DESC, u.nombre
      LIMIT " . POR_PAGINA_EMP . ' OFFSET ' . (($pagina - 1) * POR_PAGINA_EMP),
    $params
);

$titulo = 'Gestión de empleados';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <form method="get" action="<?= url('admin/empleados.php') ?>" class="d-flex flex-wrap gap-2 align-items-end" role="search">
        <div>
            <label class="form-label" for="q">Buscar por nombre, correo, puesto o ID</label>
            <input class="form-control" id="q" name="q" value="<?= e($q) ?>" style="min-width: 280px">
        </div>
        <button class="btn btn-outline-dark" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Buscar</button>
        <?php if ($q !== ''): ?><a class="btn btn-link" href="<?= url('admin/empleados.php') ?>">Limpiar</a><?php endif; ?>
    </form>
    <a class="btn btn-seguridad" href="<?= url('admin/empleados.php?nuevo=1') ?>"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Agregar nuevo empleado</a>
</div>

<div class="tarjeta-tabla mb-3">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>ID</th><th>Nombre</th><th>Puesto</th><th>Rol</th><th>Correo</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if (!$empleados): ?>
                <tr><td colspan="7" class="text-center text-body-secondary py-4">No hay empleados que coincidan.</td></tr>
            <?php endif; ?>
            <?php foreach ($empleados as $em): ?>
                <?php $esYo = (int) $em['id'] === (int) $yo['id']; ?>
                <tr class="<?= $em['activo'] ? '' : 'text-body-secondary' ?>">
                    <td><?= str_pad((string) $em['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td><strong><?= e($em['nombre'] . ' ' . $em['apellido']) ?></strong><?= $esYo ? ' <span class="badge text-bg-light border">Tú</span>' : '' ?></td>
                    <td><?= e($em['puesto'] ?: '—') ?></td>
                    <td><?= e(ucfirst($em['rol'])) ?></td>
                    <td><?= e($em['email']) ?></td>
                    <td><?= $em['activo'] ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Baja</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-dark" href="<?= url('admin/empleados.php?editar=' . $em['id']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                        <?php if (!$esYo): ?>
                            <form method="post" class="d-inline" data-boton="Eliminar empleado"
                                  data-confirmar="¿Eliminar a <?= e($em['nombre'] . ' ' . $em['apellido']) ?>? También se borrarán los avisos que haya publicado. Si sólo quieres que deje de entrar, edítalo y desactiva su cuenta.">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int) $em['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="d-flex justify-content-between align-items-center">
    <p class="text-body-secondary small mb-0"><?= $total ?> <?= $total === 1 ? 'persona' : 'personas' ?> del personal.</p>
    <?= paginacion($total, POR_PAGINA_EMP, $pagina, 'admin/empleados.php', $q !== '' ? ['q' => $q] : []) ?>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
