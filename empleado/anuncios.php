<?php
/**
 * Publicar avisos del sistema — permiso anuncios.publicar.
 *
 * Un aviso es UNA sola fila en la tabla anuncios, dirigida a un rol del
 * catálogo o a todos (rol_destino_id = NULL). No se envía nada a cada
 * usuario: cada quien lo ve al abrir "Avisos del gimnasio" (ver app/avisos.php).
 * Publicar cuesta lo mismo con 10 socios que con un millón.
 *
 * Se publica con AJAX (la lista se actualiza sin recargar). Sin JavaScript,
 * el formulario funciona con un envío normal.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/validacion.php';

$yo = requiere_permiso('anuncios.publicar');
$errores = [];
$roles = filas('SELECT id, nombre FROM roles WHERE activo = 1 ORDER BY id');
$idsRoles = array_map('intval', array_column($roles, 'id'));
$puedeBorrarTodos = tiene_permiso('empleados.gestionar');

/** Estado de un aviso según sus fechas. */
function estado_aviso(array $a): array
{
    $ahora = date('Y-m-d H:i:s');
    if ($a['fecha_expiracion'] && $a['fecha_expiracion'] <= $ahora) {
        return ['Expirado', 'text-bg-secondary'];
    }
    if ($a['fecha_publicacion'] > $ahora) {
        return ['Programado', 'text-bg-info'];
    }
    return ['Visible', 'text-bg-success'];
}

/** Datos de un aviso listos para la tabla o para JSON. */
function aviso_para_lista(array $a): array
{
    [$estado, $clase] = estado_aviso($a);
    return [
        'id'         => (int) $a['id'],
        'titulo'     => $a['titulo'],
        'contenido'  => $a['contenido'],
        'importante' => (bool) $a['importante'],
        'para'       => $a['rol_destino'] ? 'Rol: ' . ucfirst($a['rol_destino']) : 'Todos',
        'publicado'  => fecha($a['fecha_publicacion'], true),
        'expira'     => $a['fecha_expiracion'] ? fecha($a['fecha_expiracion'], true) : 'No expira',
        'autor'      => trim($a['autor_nombre'] . ' ' . $a['autor_apellido']),
        'autor_id'   => (int) $a['usuario_id'],
        'estado'     => $estado,
        'clase'      => $clase,
    ];
}

function avisos_publicados(int $limite = 30, ?int $id = null): array
{
    return filas(
        'SELECT a.*, u.nombre AS autor_nombre, u.apellido AS autor_apellido, r.nombre AS rol_destino
           FROM anuncios a
           JOIN usuarios u ON u.id = a.usuario_id
           LEFT JOIN roles r ON r.id = a.rol_destino_id
          ' . ($id ? 'WHERE a.id = ' . (int) $id : '') . '
          ORDER BY a.fecha_publicacion DESC, a.id DESC
          LIMIT ' . (int) $limite
    );
}

if (es_post()) {
    csrf_verificar();
    $accion = entrada('accion', 'publicar');

    if ($accion === 'publicar') {
        $datos = [
            'titulo'           => entrada('titulo'),
            'contenido'        => entrada('contenido'),
            'rol_destino_id'   => entrada('rol_destino_id'),
            'importante'       => isset($_POST['importante']) ? 1 : 0,
            'fecha_expiracion' => entrada('fecha_expiracion'),
        ];

        if (mb_strlen($datos['titulo']) < 3 || mb_strlen($datos['titulo']) > 120) {
            $errores['titulo'] = 'Escribe un título de 3 a 120 caracteres.';
        }
        if (mb_strlen($datos['contenido']) < 5 || mb_strlen($datos['contenido']) > 2000) {
            $errores['contenido'] = 'Escribe el contenido (de 5 a 2000 caracteres).';
        }
        $rolDestino = $datos['rol_destino_id'] === '' ? null : (int) $datos['rol_destino_id'];
        if ($rolDestino !== null && !in_array($rolDestino, $idsRoles, true)) {
            $errores['rol_destino_id'] = 'Elige a quién va dirigido.';
        }
        $expira = null;
        if ($datos['fecha_expiracion'] !== '') {
            $f = DateTime::createFromFormat('!Y-m-d', $datos['fecha_expiracion']);
            if (!$f || $f->format('Y-m-d') !== $datos['fecha_expiracion'] || $f < new DateTime('today')) {
                $errores['fecha_expiracion'] = 'La fecha hasta la que se muestra no puede ser anterior a hoy.';
            } else {
                $expira = $f->format('Y-m-d') . ' 23:59:59'; // se muestra todo ese día
            }
        }

        if (!$errores) {
            consulta(
                'INSERT INTO anuncios (usuario_id, rol_destino_id, titulo, contenido, importante, fecha_publicacion,
                                       fecha_expiracion, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())',
                [$yo['id'], $rolDestino, $datos['titulo'], $datos['contenido'], $datos['importante'], $expira]
            );
            $nuevo = avisos_publicados(1, (int) db()->lastInsertId());
            $mensaje = 'Aviso "' . $datos['titulo'] . '" publicado.';
            if (es_ajax()) {
                responder_json(['ok' => true, 'mensaje' => $mensaje, 'aviso' => aviso_para_lista($nuevo[0])]);
            }
            flash('success', $mensaje);
            redirigir('empleado/anuncios.php');
        }

        if (es_ajax()) {
            responder_json(['ok' => false, 'mensaje' => 'Revisa los datos marcados.', 'errores' => $errores], 422);
        }
        guardar_viejo($datos);
    }

    if ($accion === 'eliminar') {
        $aviso = fila('SELECT id, titulo, usuario_id FROM anuncios WHERE id = ?', [(int) entrada('id')]);
        if (!$aviso) {
            flash('warning', 'Ese aviso ya no existe.');
        } elseif ((int) $aviso['usuario_id'] !== (int) $yo['id'] && !$puedeBorrarTodos) {
            flash('danger', 'Sólo puedes eliminar los avisos que tú publicaste.');
        } else {
            consulta('DELETE FROM anuncios WHERE id = ?', [$aviso['id']]);
            flash('success', 'Aviso "' . $aviso['titulo'] . '" eliminado.');
        }
        redirigir('empleado/anuncios.php');
    }
}

$avisos = array_map('aviso_para_lista', avisos_publicados());
$v = fn(string $c) => e($_SESSION['viejo'][$c] ?? '');

$titulo = 'Publicar avisos';
$scripts = ['anuncios.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="row g-4">
    <div class="col-xl-5">
        <form method="post" action="<?= url('empleado/anuncios.php') ?>" class="tarjeta tarjeta-fija" id="form-aviso" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="publicar">
            <h2 class="h4 mb-1">Publicar nuevo aviso</h2>
            <p class="text-body-secondary small mb-3">Avisos del sistema: cierres, cambios de horario, mantenimiento. Se muestran en el panel de quien los recibe.</p>
            <div id="alerta-aviso" aria-live="polite"></div>
            <div class="mb-3">
                <label class="form-label" for="titulo">Título del aviso</label>
                <input class="form-control<?= clase_invalida($errores, 'titulo') ?>" id="titulo" name="titulo" value="<?= $v('titulo') ?>" maxlength="120" required>
                <div class="invalid-feedback" data-error="titulo"><?= e($errores['titulo'] ?? '') ?></div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="contenido_aviso">Contenido</label>
                <textarea class="form-control<?= clase_invalida($errores, 'contenido') ?>" id="contenido_aviso" name="contenido" rows="5" maxlength="2000" required><?= $v('contenido') ?></textarea>
                <div class="invalid-feedback" data-error="contenido"><?= e($errores['contenido'] ?? '') ?></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="rol_destino_id">Dirigido a</label>
                    <select class="form-select<?= clase_invalida($errores, 'rol_destino_id') ?>" id="rol_destino_id" name="rol_destino_id">
                        <option value="">Todos</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= ($_SESSION['viejo']['rol_destino_id'] ?? '') === (string) $r['id'] ? 'selected' : '' ?>>
                                Sólo <?= e($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback" data-error="rol_destino_id"><?= e($errores['rol_destino_id'] ?? '') ?></div>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="fecha_expiracion">Mostrar hasta <span class="text-body-secondary fw-normal">(opcional)</span></label>
                    <input class="form-control<?= clase_invalida($errores, 'fecha_expiracion') ?>" id="fecha_expiracion" name="fecha_expiracion" type="date"
                           value="<?= $v('fecha_expiracion') ?>" min="<?= date('Y-m-d') ?>">
                    <div class="invalid-feedback" data-error="fecha_expiracion"><?= e($errores['fecha_expiracion'] ?? '') ?></div>
                </div>
            </div>
            <div class="form-check mb-4">
                <input class="form-check-input" type="checkbox" id="importante" name="importante" value="1" <?= !empty($_SESSION['viejo']['importante']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="importante">Marcar como importante (aparece primero y resaltado)</label>
            </div>
            <button class="btn btn-seguridad w-100" type="submit" id="boton-aviso">Publicar aviso</button>
        </form>
    </div>

    <div class="col-xl-7">
        <h2 class="h4 mb-3">Avisos publicados</h2>
        <div class="lista-avisos" id="lista-avisos" data-yo="<?= (int) $yo['id'] ?>" data-borrar-todos="<?= $puedeBorrarTodos ? '1' : '0' ?>">
            <?php if (!$avisos): ?><p class="text-body-secondary" id="sin-avisos">Aún no hay avisos publicados.</p><?php endif; ?>
            <?php foreach ($avisos as $a): ?>
                <article class="aviso<?= $a['importante'] ? ' aviso-importante' : '' ?>">
                    <div class="d-flex justify-content-between gap-3">
                        <h3 class="aviso-titulo"><?= $a['importante'] ? '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> ' : '' ?><?= e($a['titulo']) ?></h3>
                        <span class="badge <?= e($a['clase']) ?> align-self-start"><?= e($a['estado']) ?></span>
                    </div>
                    <p class="aviso-texto"><?= nl2br(e($a['contenido'])) ?></p>
                    <p class="aviso-meta">
                        <?= e($a['para']) ?>. Publicó <?= e($a['autor']) ?> el <?= e($a['publicado']) ?>. Expira: <?= e($a['expira']) ?>.
                    </p>
                    <?php if ($a['autor_id'] === (int) $yo['id'] || $puedeBorrarTodos): ?>
                        <form method="post" class="text-end" data-confirmar="¿Eliminar el aviso &quot;<?= e($a['titulo']) ?>&quot;? Dejará de verse para todos." data-boton="Eliminar aviso">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $a['id'] ?>">
                            <button class="btn btn-sm btn-link text-danger p-0" type="submit">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php
limpiar_viejo();
require ROOT_PATH . '/app/vistas/panel_fin.php';
?>
