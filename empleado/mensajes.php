<?php
/**
 * Mensajes de contacto — permiso mensajes.atender (recepción y administración).
 *
 * Bandeja con los mensajes del formulario de Contacto y las solicitudes de
 * renovación de los socios. Se filtran por estado (sin atender, atendidos,
 * todos) y por texto. "Marcar como atendido" guarda quién lo atendió y cuándo
 * (atendido_por, fecha_atencion); la respuesta se da por correo o teléfono.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/mensajes.php';

$yo = requiere_permiso('mensajes.atender');

$filtros = [
    'estado' => array_key_exists(parametro('estado'), FILTROS_MENSAJES) ? parametro('estado') : 'pendientes',
    'q'      => mb_substr(parametro('q'), 0, 80),
];
$volver = 'empleado/mensajes.php?' . http_build_query(array_filter($filtros + ['pagina' => parametro('pagina')]));

if (es_post()) {
    csrf_verificar();
    $id = (int) entrada('id');
    $accion = entrada('accion');

    if ($accion === 'atender') {
        $cambio = atender_mensaje($id, (int) $yo['id']);
        $mensaje = $cambio ? 'Mensaje marcado como atendido.' : 'Ese mensaje ya estaba atendido.';
        if (es_ajax()) {
            $m = fila("SELECT mc.*, CONCAT(a.nombre, ' ', a.apellido) AS atendio
                         FROM mensajes_contacto mc LEFT JOIN usuarios a ON a.id = mc.atendido_por WHERE mc.id = ?", [$id]);
            responder_json([
                'ok'         => (bool) $m,
                'mensaje'    => $m ? $mensaje : 'El mensaje ya no existe.',
                'atencion'   => $m && $m['fecha_atencion'] ? texto_atencion($m) : '',
                'pendientes' => mensajes_pendientes(),
            ], $m ? 200 : 404);
        }
        flash($cambio ? 'success' : 'info', $mensaje);
    } elseif ($accion === 'reabrir') {
        $cambio = reabrir_mensaje($id);
        flash($cambio ? 'success' : 'info', $cambio ? 'El mensaje volvió a "Sin atender".' : 'Ese mensaje ya estaba sin atender.');
    }
    redirigir($volver);
}

$porPagina = 10;
$pagina = pagina_actual_num();
$resultado = buscar_mensajes($filtros['estado'], $filtros['q'], $porPagina, ($pagina - 1) * $porPagina);
$mensajes = $resultado['mensajes'];
$pendientes = mensajes_pendientes();

$titulo = 'Mensajes de contacto';
$scripts = ['mensajes.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <nav aria-label="Filtrar mensajes">
        <ul class="nav nav-pills filtros-mensajes">
            <?php foreach (FILTROS_MENSAJES as $clave => $nombre): ?>
                <?php $activo = $filtros['estado'] === $clave; ?>
                <li class="nav-item">
                    <a class="nav-link<?= $activo ? ' active' : '' ?>" <?= $activo ? 'aria-current="page"' : '' ?>
                       href="<?= url('empleado/mensajes.php?' . http_build_query(array_filter(['estado' => $clave, 'q' => $filtros['q']]))) ?>">
                        <?= e($nombre) ?><?php if ($clave === 'pendientes'): ?>
                            <span class="badge rounded-pill text-bg-warning ms-1" id="contador-mensajes"><?= $pendientes ?></span><?php endif; ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
    <form method="get" action="<?= url('empleado/mensajes.php') ?>" class="d-flex gap-2" role="search">
        <input type="hidden" name="estado" value="<?= e($filtros['estado']) ?>">
        <label class="visually-hidden" for="q">Buscar por nombre, correo o asunto</label>
        <input class="form-control" id="q" name="q" value="<?= e($filtros['q']) ?>" placeholder="Nombre, correo o asunto" style="min-width: 220px">
        <button class="btn btn-outline-dark" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden">Buscar</span></button>
        <?php if ($filtros['q'] !== ''): ?>
            <a class="btn btn-link" href="<?= url('empleado/mensajes.php?estado=' . $filtros['estado']) ?>">Limpiar</a>
        <?php endif; ?>
    </form>
</div>

<div id="alerta-mensajes" aria-live="polite"></div>

<div id="lista-mensajes" class="d-grid gap-3" data-estado="<?= e($filtros['estado']) ?>">
    <?php if (!$mensajes): ?>
        <p class="tarjeta text-body-secondary mb-0 h-auto">
            <?php if ($filtros['q'] !== ''): ?>
                No hay mensajes que coincidan con «<?= e($filtros['q']) ?>».
            <?php elseif ($filtros['estado'] === 'pendientes'): ?>
                <i class="bi bi-check2-circle text-success me-1" aria-hidden="true"></i>No hay mensajes sin atender.
            <?php else: ?>
                Todavía no hay mensajes.
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php foreach ($mensajes as $m): ?>
        <?php
        $atendido = $m['fecha_atencion'] !== null;
        $renovacion = es_solicitud_renovacion($m);
        $esSocio = $m['usuario_id'] !== null;
        $membresia = $esSocio ? ultima_membresia((int) $m['usuario_id']) : null;
        ?>
        <article class="mensaje<?= $atendido ? '' : ' mensaje-pendiente' ?>" id="mensaje-<?= (int) $m['id'] ?>">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <h2 class="mensaje-asunto">
                    <?php if ($renovacion): ?><i class="bi bi-arrow-repeat" aria-hidden="true"></i><?php endif; ?>
                    <?= e($m['asunto']) ?>
                </h2>
                <p class="mensaje-fecha" title="<?= e(fecha(substr($m['created_at'], 0, 10)) . ' ' . substr($m['created_at'], 11, 5)) ?>">
                    <?= e(hace($m['created_at'])) ?></p>
            </div>
            <p class="mensaje-de">
                <strong><?= e($m['nombre'] . ' ' . $m['apellido']) ?></strong>
                <span class="badge <?= $esSocio ? 'text-bg-dark' : 'text-bg-light border' ?>"><?= $esSocio ? 'Socio' : 'Visitante' ?></span>
                <?php if ($renovacion): ?><span class="badge text-bg-warning">Renovación</span><?php endif; ?>
                <span class="d-block d-sm-inline">
                    <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>
                    <?php if ($m['telefono']): ?> · <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $m['telefono'])) ?>"><?= e($m['telefono']) ?></a><?php endif; ?>
                </span>
            </p>
            <p class="mensaje-texto"><?= e($m['mensaje']) ?></p>

            <?php if ($membresia): ?>
                <?php $estado = estado_membresia($membresia); ?>
                <p class="mensaje-membresia">
                    <i class="bi bi-credit-card-2-front" aria-hidden="true"></i>
                    Membresía: <?= e($membresia['nombre_plan']) ?> <?= insignia_estado($estado) ?>
                    <?= $estado === 'vencida' ? 'venció' : 'vence' ?> el <?= e(fecha($membresia['fecha_fin'])) ?>
                </p>
            <?php elseif ($esSocio): ?>
                <p class="mensaje-membresia"><i class="bi bi-credit-card-2-front" aria-hidden="true"></i> Socio sin membresía registrada.</p>
            <?php endif; ?>

            <div class="mensaje-acciones">
                <?php if (!$atendido): ?>
                    <form method="post" class="form-atender-mensaje" action="<?= url($volver) ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="atender">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-seguridad" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Marcar como atendido</button>
                    </form>
                    <a class="btn btn-sm btn-outline-dark" href="<?= e(enlace_responder($m)) ?>"><i class="bi bi-reply me-1" aria-hidden="true"></i>Responder por correo</a>
                    <?php if ($renovacion && $esSocio && tiene_permiso('pagos.registrar') && $m['socio_activo']): ?>
                        <a class="btn btn-sm btn-outline-dark" href="<?= url('empleado/pagos.php?socio=' . (int) $m['usuario_id']) ?>"><i class="bi bi-cash-coin me-1" aria-hidden="true"></i>Registrar pago</a>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="mensaje-atendido"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> <?= e(texto_atencion($m)) ?></span>
                    <form method="post" action="<?= url($volver) ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="reabrir">
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <button class="btn btn-sm btn-link p-0" type="submit">Marcar como sin atender</button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
    <p class="small text-body-secondary mb-0">
        <?= $resultado['total'] ?> <?= $resultado['total'] === 1 ? 'mensaje' : 'mensajes' ?>
    </p>
    <?= paginacion($resultado['total'], $porPagina, $pagina, 'empleado/mensajes.php', array_filter($filtros)) ?>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
