<?php
/**
 * Dashboard del administrador.
 * Ingresos del mes contra el mismo periodo del mes pasado (con los últimos 6 meses),
 * socios activos, membresías por vencer con acceso directo a cobrar, mensajes
 * y los últimos pagos. Las secciones quedan en el menú lateral.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/menu.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/vencimientos.php';
require_once ROOT_PATH . '/app/mensajes.php';
require_once ROOT_PATH . '/app/reportes.php';

$yo = requiere_permiso(...PERMISOS_ADMIN);

$sociosVigentes = (int) valor(
    'SELECT COUNT(DISTINCT m.usuario_id)
       FROM membresias m JOIN usuarios u ON u.id = m.usuario_id
      WHERE ' . SQL_VIGENTE . ' AND u.activo = 1'
);
$sociosRegistrados = (int) valor(
    "SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE r.nombre = 'socio' AND u.activo = 1"
);
$ingresosMes = (float) valor(
    "SELECT COALESCE(SUM(monto), 0) FROM pagos
      WHERE fecha_pago BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()"
);
$pagosMes = (int) valor(
    "SELECT COUNT(*) FROM pagos WHERE fecha_pago BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND CURDATE()"
);

// Mismo periodo del mes pasado: del día 1 al mismo día (o al último día si el mes pasado era más corto)
$inicioPasado = new DateTime('first day of last month');
$finPasado = (clone $inicioPasado)->setDate(
    (int) $inicioPasado->format('Y'),
    (int) $inicioPasado->format('n'),
    min((int) date('j'), (int) $inicioPasado->format('t'))
);
$ingresosPasado = (float) valor(
    'SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE fecha_pago BETWEEN ? AND ?',
    [$inicioPasado->format('Y-m-d'), $finPasado->format('Y-m-d')]
);
if ($ingresosPasado > 0) {
    $cambio = (int) round(($ingresosMes - $ingresosPasado) / $ingresosPasado * 100);
    $tendencia = $cambio > 0 ? 'sube' : ($cambio < 0 ? 'baja' : 'igual');
} else {
    $cambio = null;
    $tendencia = $ingresosMes > 0 ? 'sube' : 'igual';
}
$serie = ingresos_por_mes(6);
$maximo = max(1, max(array_column($serie, 'total')));

$porVencer = membresias_por_vencer(7);
$revision = ultima_revision();
$mensajesPendientes = mensajes_pendientes();
$puedeCobrar = tiene_permiso('pagos.registrar');

$ultimosPagos = filas(
    'SELECT pg.monto, pg.fecha_pago, u.nombre, u.apellido, pl.nombre_plan, mp.nombre AS metodo
       FROM pagos pg
       JOIN membresias m ON m.id = pg.membresia_id
       JOIN usuarios u ON u.id = m.usuario_id
       JOIN planes pl ON pl.id = m.plan_id
       JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
      ORDER BY pg.fecha_pago DESC, pg.id DESC
      LIMIT 5'
);

$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];

$titulo = 'Panel de administración';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<p class="panel-saludo">Hola, <?= e($yo['nombre']) ?>. Así va el gimnasio hoy, <?= date('j') ?> de <?= $meses[(int) date('n')] ?>.</p>

<?php if (!$revision || $revision['atrasada']): ?>
    <details class="aviso-sistema mb-4">
        <summary><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <?= $revision ? 'Avisos de vencimiento detenidos desde ' . e(texto_ultima_revision($revision['fecha'])) : 'La revisión diaria de vencimientos no se ha ejecutado' ?></summary>
        <div class="aviso-sistema-detalle">
            Sin la revisión diaria no se envían los correos de 7, 3 y 1 día ni se generan los avisos para recepción.
            Prográmala en el Programador de tareas de Windows como indica el README (sección <em>Tarea programada</em>) o ejecútala a mano:
            <code class="d-block mt-1">php tareas/notificar_vencimientos.php</code>
        </div>
    </details>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-12 col-xl-5">
        <?php $etiquetaIngresos = tiene_permiso('reportes.ver') ? 'a' : 'div'; ?>
        <<?= $etiquetaIngresos ?> class="dato dato-principal<?= $etiquetaIngresos === 'a' ? ' dato-enlace' : '' ?>"<?= $etiquetaIngresos === 'a' ? ' href="' . url('admin/reportes.php') . '"' : '' ?>>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <p class="dato-etiqueta">Ingresos de <?= $meses[(int) date('n')] ?></p>
                <span class="tendencia tendencia-<?= $tendencia ?>">
                    <i class="bi bi-arrow-<?= $tendencia === 'baja' ? 'down' : ($tendencia === 'sube' ? 'up' : 'right') ?>-short" aria-hidden="true"></i>
                    <?= $cambio === null ? ($ingresosMes > 0 ? 'Nuevo' : 'Sin cambio') : ($cambio > 0 ? '+' : '') . $cambio . ' %' ?>
                </span>
            </div>
            <p class="dato-valor"><?= e(dinero($ingresosMes)) ?></p>
            <p class="dato-nota"><?= $pagosMes ?> <?= $pagosMes === 1 ? 'pago' : 'pagos' ?> · <?= e(dinero($ingresosPasado)) ?> al <?= (int) $finPasado->format('j') ?> de <?= $meses[(int) $finPasado->format('n')] ?></p>
            <ul class="barras" aria-label="Ingresos de los últimos 6 meses">
                <?php foreach ($serie as $mes): ?>
                    <li>
                        <span class="barra-valor" style="height: <?= max(3, round($mes['total'] / $maximo * 100)) ?>%"></span>
                        <small><?= e(explode(' ', $mes['etiqueta'])[0]) ?></small>
                        <span class="visually-hidden"><?= e($mes['etiqueta'] . ': ' . dinero($mes['total'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </<?= $etiquetaIngresos ?>>
    </div>
    <div class="col-sm-4 col-xl-2">
        <div class="dato">
            <p class="dato-etiqueta">Socios activos</p>
            <p class="dato-valor"><?= $sociosVigentes ?></p>
            <p class="dato-nota">de <?= $sociosRegistrados ?> registrados</p>
        </div>
    </div>
    <div class="col-sm-4 col-xl-2">
        <div class="dato<?= $porVencer ? ' dato-alerta' : '' ?>">
            <p class="dato-etiqueta">Por vencer</p>
            <p class="dato-valor"><?= count($porVencer) ?></p>
            <p class="dato-nota">en los próximos 7 días</p>
        </div>
    </div>
    <div class="col-sm-4 col-xl-3">
        <?php $etiquetaDato = tiene_permiso('mensajes.atender') ? 'a' : 'div'; ?>
        <<?= $etiquetaDato ?> class="dato<?= $mensajesPendientes ? ' dato-alerta' : '' ?><?= $etiquetaDato === 'a' ? ' dato-enlace' : '' ?>"<?= $etiquetaDato === 'a' ? ' href="' . url('empleado/mensajes.php') . '"' : '' ?>>
            <p class="dato-etiqueta">Mensajes sin atender</p>
            <p class="dato-valor"><?= $mensajesPendientes ?></p>
            <p class="dato-nota">del formulario de contacto<?= $etiquetaDato === 'a' ? ' · Ver' : '' ?></p>
        </<?= $etiquetaDato ?>>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <section class="tarjeta tarjeta-auto" aria-labelledby="titulo-vencer">
            <div class="tarjeta-cabeza">
                <h2 id="titulo-vencer">Por vencer esta semana</h2>
                <?php if (tiene_permiso('socios.gestionar')): ?><a href="<?= url('admin/socios.php') ?>">Ver socios</a><?php endif; ?>
            </div>
            <?php if (!$porVencer): ?>
                <p class="text-body-secondary mb-0">Ninguna membresía vence en los próximos 7 días.</p>
            <?php else: ?>
                <ul class="lista-simple">
                    <?php foreach (array_slice($porVencer, 0, 6) as $m): ?>
                        <?php $d = (int) $m['dias_restantes']; ?>
                        <li class="align-items-center">
                            <span class="min-w-0">
                                <strong class="d-block text-truncate"><?= e($m['nombre'] . ' ' . $m['apellido']) ?></strong>
                                <span class="meta"><?= e($m['nombre_plan']) ?> · <span class="<?= $d <= 1 ? 'text-danger fw-semibold' : '' ?>"><?= e(texto_dias($d)) ?></span></span>
                            </span>
                            <?php if ($puedeCobrar): ?>
                                <a class="btn btn-sm btn-outline-dark text-nowrap" href="<?= url('empleado/pagos.php?socio=' . (int) $m['usuario_id']) ?>"
                                   aria-label="Registrar pago de <?= e($m['nombre'] . ' ' . $m['apellido']) ?>">Cobrar</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($porVencer) > 6): ?>
                    <p class="small text-body-secondary mt-3 mb-0">Y <?= count($porVencer) - 6 ?> más.</p>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-xl-7">
        <div class="tarjeta-cabeza">
            <h2 class="h4 mb-0" id="titulo-pagos">Últimos pagos</h2>
            <?php if ($puedeCobrar): ?><a href="<?= url('empleado/pagos.php') ?>">Registrar pago</a><?php endif; ?>
        </div>
        <div class="tarjeta-tabla">
            <?php if (!$ultimosPagos): ?>
                <p class="p-3 mb-0 text-body-secondary">Aún no hay pagos registrados.</p>
            <?php else: ?>
                <table class="table mb-0 align-middle" aria-labelledby="titulo-pagos">
                    <thead><tr><th>Socio</th><th>Plan</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimosPagos as $p): ?>
                        <tr>
                            <td><?= e($p['nombre'] . ' ' . $p['apellido']) ?><span class="d-block small text-body-secondary"><?= e(fecha($p['fecha_pago'])) ?> · <?= e($p['metodo']) ?></span></td>
                            <td><?= e($p['nombre_plan']) ?></td>
                            <td class="text-end fw-semibold text-nowrap"><?= e(dinero($p['monto'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
