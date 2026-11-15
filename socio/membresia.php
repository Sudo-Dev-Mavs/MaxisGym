<?php
/**
 * Mi membresía — permiso membresia.ver.
 *
 * Muestra el plan vigente (inicio, vencimiento, estado y días restantes),
 * sus beneficios, la renovación ya pagada si existe y el historial de periodos.
 *
 * "Renovar membresía": el pago se hace en recepción, así que el botón envía
 * una solicitud que llega como mensaje al gimnasio (tabla mensajes_contacto,
 * ligada al socio con usuario_id) para que lo contacten.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/planes.php';

$yo = requiere_permiso('membresia.ver');
$id = (int) $yo['id'];

if (es_post() && entrada('accion') === 'solicitar_renovacion') {
    csrf_verificar();
    $ultima = ultima_membresia($id);
    $yaPidio = valor(
        "SELECT COUNT(*) FROM mensajes_contacto
          WHERE usuario_id = ? AND asunto LIKE 'Solicitud de renovación%' AND atendido_por IS NULL
            AND created_at > NOW() - INTERVAL 1 DAY",
        [$id]
    );
    if ($yaPidio) {
        flash('info', 'Ya enviaste una solicitud hoy. Recepción te contactará pronto.');
    } else {
        $plan = $ultima['nombre_plan'] ?? 'sin plan';
        consulta(
            'INSERT INTO mensajes_contacto (usuario_id, nombre, apellido, email, telefono, asunto, mensaje, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $id, $yo['nombre'], $yo['apellido'], $yo['email'], $yo['telefono'],
                'Solicitud de renovación: ' . $plan,
                'El socio pidió renovar su membresía desde su panel.'
                . ($ultima ? ' Su periodo actual termina el ' . fecha($ultima['fecha_fin']) . '.' : ''),
            ]
        );
        flash('success', 'Solicitud enviada. Puedes pagar tu renovación en recepción; te contactaremos a ' . $yo['email'] . '.');
    }
    redirigir('socio/membresia.php');
}

$vigente = membresia_vigente($id);
$ultima = $vigente ?? ultima_membresia($id);
$renovacion = renovacion_pendiente($id);
$beneficios = $ultima ? filas(
    'SELECT b.descripcion FROM plan_beneficio pb JOIN beneficios b ON b.id = pb.beneficio_id
      WHERE pb.plan_id = ? ORDER BY b.descripcion',
    [$ultima['plan_id']]
) : [];
$historial = filas(
    'SELECT m.*, p.nombre_plan FROM membresias m JOIN planes p ON p.id = m.plan_id
      WHERE m.usuario_id = ? ORDER BY m.fecha_inicio DESC',
    [$id]
);

$titulo = 'Mi membresía';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<?php if ($vigente && !$renovacion && $vigente['dias_restantes'] <= 7): ?>
    <div class="alert alert-warning d-flex gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1" aria-hidden="true"></i>
        <div>Tu membresía <?= e(texto_dias((int) $vigente['dias_restantes'])) ?>.</div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-7">
        <section class="estado-membresia mb-4">
            <?php if ($ultima): ?>
                <?php $estado = estado_membresia($ultima); ?>
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <p class="estado-etiqueta">Detalles de tu membresía</p>
                        <h2 class="estado-plan"><?= e($ultima['nombre_plan']) ?></h2>
                    </div>
                    <?= insignia_estado($estado) ?>
                </div>
                <dl class="datos-membresia mt-3 mb-0">
                    <div><dt>Inicio</dt><dd><?= e(fecha($ultima['fecha_inicio'])) ?></dd></div>
                    <div><dt>Vencimiento</dt><dd><?= e(fecha($ultima['fecha_fin'])) ?></dd></div>
                    <div><dt>Estado</dt><dd><?= e(ucfirst($estado)) ?><?= $vigente ? ', ' . e(texto_dias((int) $vigente['dias_restantes'])) : '' ?></dd></div>
                </dl>
            <?php else: ?>
                <p class="estado-etiqueta">Detalles de tu membresía</p>
                <h2 class="estado-plan">Aún no tienes membresía</h2>
                <p class="mt-2 mb-0">Elige un plan en <a href="<?= url('planes.php') ?>">Planes y precios</a> y págalo en recepción.</p>
            <?php endif; ?>
        </section>

        <?php if ($renovacion): ?>
            <div class="alert alert-success" role="status">
                <i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i>
                Ya pagaste tu siguiente periodo: <?= e($renovacion['nombre_plan']) ?> del <?= e(fecha($renovacion['fecha_inicio'])) ?>
                al <?= e(fecha($renovacion['fecha_fin'])) ?>.
            </div>
        <?php else: ?>
            <form method="post" class="tarjeta h-auto d-flex flex-wrap justify-content-between align-items-center gap-3">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="solicitar_renovacion">
                <div>
                    <h2 class="h5 mb-1">Renovar membresía</h2>
                    <p class="mb-0 text-body-secondary small">La renovación se paga en recepción. Envía la solicitud y te contactamos para dejarla lista.</p>
                </div>
                <button class="btn btn-seguridad" type="submit">Solicitar renovación</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="col-xl-5">
        <h2 class="h4">Beneficios del plan</h2>
        <?php if ($beneficios): ?>
            <ul class="list-group mb-4">
                <?php foreach ($beneficios as $b): ?>
                    <li class="list-group-item"><i class="bi bi-check-lg text-success me-2" aria-hidden="true"></i><?= e($b['descripcion']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-body-secondary">Cuando tengas un plan, aquí verás lo que incluye.</p>
        <?php endif; ?>

        <?php if ($historial): ?>
            <h2 class="h4">Tus periodos</h2>
            <div class="tarjeta-tabla">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Plan y periodo</th><th class="text-end">Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($historial as $m): ?>
                        <tr>
                            <td><?= e($m['nombre_plan']) ?><span class="d-block small text-body-secondary"><?= e(fecha($m['fecha_inicio'])) ?> al <?= e(fecha($m['fecha_fin'])) ?></span></td>
                            <td class="text-end"><?= insignia_estado(estado_membresia($m)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
