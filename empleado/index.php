<?php
/**
 * Dashboard del empleado (recepción).
 * Accesos a Registrar pagos, Alta de socios, Consultar información y Avisos,
 * y los avisos de vencimiento que genera la tarea diaria
 * (tareas/notificar_vencimientos.php): recepción contacta al socio y lo marca
 * como atendido.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/menu.php';
require_once ROOT_PATH . '/app/vencimientos.php';
require_once ROOT_PATH . '/app/mensajes.php';

$yo = requiere_permiso(...PERMISOS_RECEPCION);
$veAvisos = tiene_permiso('socios.consultar');

// Marcar como atendido el aviso de una membresía (AJAX o formulario normal)
if (es_post() && entrada('accion') === 'atender_aviso') {
    csrf_verificar();
    requiere_permiso('socios.consultar');
    $cambiados = atender_avisos_membresia((int) entrada('membresia_id'));
    $mensaje = $cambiados ? 'Aviso marcado como atendido.' : 'Ese aviso ya estaba atendido.';
    if (es_ajax()) {
        responder_json(['ok' => true, 'mensaje' => $mensaje, 'pendientes' => count(avisos_vencimiento_pendientes())]);
    }
    flash($cambiados ? 'success' : 'info', $mensaje);
    redirigir('empleado/index.php');
}

$avisos = $veAvisos ? avisos_vencimiento_pendientes() : [];
$revision = ultima_revision();
$mensajesPendientes = tiene_permiso('mensajes.atender') ? mensajes_pendientes() : 0;
$renovaciones = $mensajesPendientes ? renovaciones_pendientes() : 0;

$titulo = 'Panel de recepción';
$scripts = ['vencimientos.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<p class="text-body-secondary mb-4">Hola, <?= e($yo['nombre']) ?>. Esto es lo que tienes a la mano en recepción.</p>

<?php if ($mensajesPendientes): ?>
    <a class="aviso-mensajes mb-4" href="<?= url('empleado/mensajes.php') ?>">
        <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
        <span><strong><?= $mensajesPendientes ?> <?= $mensajesPendientes === 1 ? 'mensaje sin atender' : 'mensajes sin atender' ?></strong><?php if ($renovaciones): ?>,
            <?= $renovaciones === 1 ? 'incluye 1 solicitud de renovación' : "incluye $renovaciones solicitudes de renovación" ?><?php endif; ?>.</span>
        <span class="ms-auto text-nowrap">Ver mensajes <i class="bi bi-arrow-right" aria-hidden="true"></i></span>
    </a>
<?php endif; ?>

<div class="atajos atajos-4 mb-4">
    <?php if (tiene_permiso('pagos.registrar')): ?>
        <a class="atajo" href="<?= url('empleado/pagos.php') ?>">
            <i class="bi bi-cash-coin" aria-hidden="true"></i>
            <span><strong>Registrar pagos</strong>Cobra una renovación y ve los pagos recientes.</span>
        </a>
    <?php endif; ?>
    <?php if (tiene_permiso('socios.alta')): ?>
        <a class="atajo" href="<?= url('empleado/alta_socio.php') ?>">
            <i class="bi bi-person-plus" aria-hidden="true"></i>
            <span><strong>Alta de socios</strong>Inscribe a un socio nuevo con su plan y su primer pago.</span>
        </a>
    <?php endif; ?>
    <?php if (tiene_permiso('socios.consultar')): ?>
        <a class="atajo" href="<?= url('empleado/consultar.php') ?>">
            <i class="bi bi-search" aria-hidden="true"></i>
            <span><strong>Consultar información</strong>Busca socios por nombre o ID y ve su plan activo.</span>
        </a>
    <?php endif; ?>
    <?php if (tiene_permiso('anuncios.publicar')): ?>
        <a class="atajo" href="<?= url('empleado/anuncios.php') ?>">
            <i class="bi bi-megaphone" aria-hidden="true"></i>
            <span><strong>Avisos</strong>Publica avisos del sistema para socios o personal.</span>
        </a>
    <?php endif; ?>
</div>

<?php if ($veAvisos): ?>
    <section aria-labelledby="titulo-vencimientos">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-2">
            <h2 class="h4 mb-0" id="titulo-vencimientos">Avisos de vencimiento
                <span class="badge rounded-pill text-bg-warning align-middle fs-6" id="contador-vencimientos"><?= count($avisos) ?></span></h2>
            <?php if ($revision && !$revision['atrasada']): ?>
                <p class="small text-body-secondary mb-0"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Revisión automática: <?= e(texto_ultima_revision($revision['fecha'])) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!$revision || $revision['atrasada']): ?>
            <div class="alert alert-warning small d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
                <div>
                    <?= $revision
                        ? 'La revisión automática no corre desde ' . e(texto_ultima_revision($revision['fecha'])) . '.'
                        : 'La revisión automática de vencimientos todavía no se ha ejecutado.' ?>
                    Sin ella no se generan avisos ni se envían correos a los socios.
                    <?= tiene_permiso('empleados.gestionar')
                        ? 'Prográmala como indica el README (sección <strong>Tarea programada</strong>).'
                        : 'Avísale al administrador.' ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="small text-body-secondary">El sistema avisa por correo al socio 7, 3 y 1 día antes de que venza su membresía.
            Contáctalo y marca el aviso como atendido.</p>

        <div id="alerta-vencimientos" aria-live="polite"></div>
        <div class="tarjeta-tabla" id="tabla-vencimientos">
            <?php if (!$avisos): ?>
                <p class="p-3 mb-0 text-body-secondary">No hay avisos pendientes.</p>
            <?php else: ?>
                <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Socio</th><th>Plan</th><th>Teléfono</th><th>Vence</th><th>Aviso</th><th><span class="visually-hidden">Acción</span></th></tr></thead>
                    <tbody>
                    <?php foreach ($avisos as $a): ?>
                        <?php $nombre = $a['nombre'] . ' ' . $a['apellido']; $dias = (int) $a['dias']; ?>
                        <tr>
                            <td class="fw-semibold"><?= e($nombre) ?></td>
                            <td><?= e($a['nombre_plan']) ?></td>
                            <td class="text-nowrap"><?php if ($a['telefono']): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $a['telefono'])) ?>"><?= e($a['telefono']) ?></a><?php else: ?><span class="text-body-secondary">—</span><?php endif; ?></td>
                            <td class="text-nowrap"><?= e(fecha($a['fecha_fin'])) ?>
                                <span class="d-block small <?= $dias <= 1 ? 'text-danger fw-semibold' : 'text-body-secondary' ?>"><?= e(texto_dias_aviso($dias)) ?></span></td>
                            <td class="text-nowrap">
                                <span class="badge <?= (int) $a['aviso'] === 1 ? 'text-bg-danger' : ((int) $a['aviso'] === 3 ? 'text-bg-warning' : 'text-bg-light border') ?>"><?= (int) $a['aviso'] ?> <?= (int) $a['aviso'] === 1 ? 'día' : 'días' ?></span>
                                <span class="d-block small text-body-secondary">
                                    <?php if ($a['correo_enviado']): ?>
                                        <i class="bi bi-envelope-check" aria-hidden="true"></i> Correo enviado <?= e(fecha(substr($a['fecha_envio'], 0, 10))) ?>
                                    <?php else: ?>
                                        <i class="bi bi-envelope-exclamation" aria-hidden="true"></i> Correo pendiente
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <form method="post" class="form-atender">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="atender_aviso">
                                    <input type="hidden" name="membresia_id" value="<?= (int) $a['membresia_id'] ?>">
                                    <button class="btn btn-sm btn-outline-dark text-nowrap" type="submit" aria-label="Marcar como atendido el aviso de <?= e($nombre) ?>">
                                        <i class="bi bi-check2" aria-hidden="true"></i> Atendido</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
