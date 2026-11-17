<?php
/**
 * Dashboard del administrador.
 * Resumen del gimnasio y accesos a Gestión de socios, empleados, planes y reportes.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/menu.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/vencimientos.php';
require_once ROOT_PATH . '/app/mensajes.php';

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
$porVencer = count(membresias_por_vencer(7));
$revision = ultima_revision();
$mensajesPendientes = mensajes_pendientes();

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

<p class="text-body-secondary mb-4">Hola, <?= e($yo['nombre']) ?>. Así va el gimnasio hoy, <?= date('j') ?> de <?= $meses[(int) date('n')] ?>.</p>

<?php if (!$revision || $revision['atrasada']): ?>
    <div class="alert alert-warning d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
        <div>
            <strong>La revisión diaria de vencimientos <?= $revision ? 'no corre desde ' . e(texto_ultima_revision($revision['fecha'])) : 'no se ha ejecutado' ?>.</strong>
            Sin ella no se envían los correos de 7, 3 y 1 día ni se generan los avisos para recepción.
            Prográmala en el Programador de tareas de Windows como indica el README (sección <em>Tarea programada</em>) o ejecútala a mano:
            <code class="d-block mt-1">php tareas/notificar_vencimientos.php</code>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="dato">
            <p class="dato-etiqueta">Socios con membresía activa</p>
            <p class="dato-valor"><?= $sociosVigentes ?></p>
            <p class="dato-nota">de <?= $sociosRegistrados ?> socios registrados</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="dato">
            <p class="dato-etiqueta">Ingresos de <?= $meses[(int) date('n')] ?></p>
            <p class="dato-valor"><?= e(dinero($ingresosMes)) ?></p>
            <p class="dato-nota"><?= $pagosMes ?> <?= $pagosMes === 1 ? 'pago registrado' : 'pagos registrados' ?></p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="dato<?= $porVencer ? ' dato-alerta' : '' ?>">
            <p class="dato-etiqueta">Por vencer en 7 días</p>
            <p class="dato-valor"><?= $porVencer ?></p>
            <p class="dato-nota">membresías que conviene renovar</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <?php $etiquetaDato = tiene_permiso('mensajes.atender') ? 'a' : 'div'; ?>
        <<?= $etiquetaDato ?> class="dato<?= $mensajesPendientes ? ' dato-alerta' : '' ?><?= $etiquetaDato === 'a' ? ' dato-enlace' : '' ?>"<?= $etiquetaDato === 'a' ? ' href="' . url('empleado/mensajes.php') . '"' : '' ?>>
            <p class="dato-etiqueta">Mensajes sin atender</p>
            <p class="dato-valor"><?= $mensajesPendientes ?></p>
            <p class="dato-nota">del formulario de contacto<?= $etiquetaDato === 'a' ? ' · Ver mensajes' : '' ?></p>
        </<?= $etiquetaDato ?>>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <h2 class="h4 mb-3">Secciones</h2>
        <div class="atajos">
            <?php if (tiene_permiso('socios.gestionar')): ?>
                <a class="atajo" href="<?= url('admin/socios.php') ?>">
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <span><strong>Gestión de socios</strong>Busca, edita o da de baja socios registrados.</span>
                </a>
            <?php endif; ?>
            <?php if (tiene_permiso('empleados.gestionar')): ?>
                <a class="atajo" href="<?= url('admin/empleados.php') ?>">
                    <i class="bi bi-person-badge" aria-hidden="true"></i>
                    <span><strong>Gestión de empleados</strong>Controla los datos, puesto y rol del personal.</span>
                </a>
            <?php endif; ?>
            <?php if (tiene_permiso('reportes.ver')): ?>
                <a class="atajo" href="<?= url('admin/reportes.php') ?>">
                    <i class="bi bi-graph-up" aria-hidden="true"></i>
                    <span><strong>Reportes</strong>Socios activos, empleados e ingresos por periodo, en PDF.</span>
                </a>
            <?php endif; ?>
            <?php if (tiene_permiso('planes.gestionar')): ?>
                <a class="atajo" href="<?= url('admin/planes.php') ?>">
                    <i class="bi bi-tags" aria-hidden="true"></i>
                    <span><strong>Gestión de planes</strong>Precios, duración y beneficios de cada plan.</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <h2 class="h4 mb-3">Últimos pagos</h2>
        <div class="tarjeta-tabla">
            <?php if (!$ultimosPagos): ?>
                <p class="p-3 mb-0 text-body-secondary">Aún no hay pagos registrados.</p>
            <?php else: ?>
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Socio</th><th>Plan</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimosPagos as $p): ?>
                        <tr>
                            <td><?= e($p['nombre'] . ' ' . $p['apellido']) ?><span class="d-block small text-body-secondary"><?= e(fecha($p['fecha_pago'])) ?> · <?= e($p['metodo']) ?></span></td>
                            <td><?= e($p['nombre_plan']) ?></td>
                            <td class="text-end fw-semibold"><?= e(dinero($p['monto'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
