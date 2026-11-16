<?php
/**
 * Consultar información de socios — permiso socios.consultar.
 * Búsqueda por nombre, correo o ID. Con JavaScript los resultados se
 * actualizan mientras se escribe (AJAX a api/buscar_socios.php);
 * sin JavaScript, el formulario funciona con un envío normal.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/socios.php';

$yo = requiere_permiso('socios.consultar');

$q = parametro('q');
$resultado = $q !== '' ? buscar_socios($q, 'activos', 25) : ['socios' => [], 'total' => 0];
$puedeCobrar = tiene_permiso('pagos.registrar');

$titulo = 'Consultar socios';
$scripts = ['buscador.js', 'consultar.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<form method="get" action="<?= url('empleado/consultar.php') ?>" class="tarjeta mb-3" role="search" id="form-consulta"
      data-api="<?= url('api/buscar_socios.php') ?>" data-pagos="<?= $puedeCobrar ? url('empleado/pagos.php') : '' ?>">
    <label class="form-label" for="q">Buscar datos de socios por nombre, correo o ID</label>
    <div class="d-flex gap-2">
        <input class="form-control form-control-lg" id="q" name="q" value="<?= e($q) ?>" placeholder="Ej. Juan Pérez o 3" autocomplete="off" autofocus>
        <button class="btn btn-outline-dark px-4" type="submit">Buscar</button>
    </div>
</form>

<p class="text-body-secondary small" id="resumen-consulta" aria-live="polite">
    <?= $q === '' ? 'Escribe para buscar. Los resultados aparecen mientras escribes.'
        : e($resultado['total'] . ($resultado['total'] === 1 ? ' resultado' : ' resultados') . ' para "' . $q . '".') ?>
</p>

<div class="tarjeta-tabla">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Plan</th><th>Estado</th><?= $puedeCobrar ? '<th></th>' : '' ?></tr></thead>
            <tbody id="resultados-consulta">
            <?php foreach ($resultado['socios'] as $s): ?>
                <tr>
                    <td><?= str_pad((string) $s['id'], 3, '0', STR_PAD_LEFT) ?></td>
                    <td><strong><?= e($s['nombre'] . ' ' . $s['apellido']) ?></strong></td>
                    <td><?= e($s['email']) ?></td>
                    <td><?= e($s['telefono'] ?? '—') ?></td>
                    <td><?= e($s['nombre_plan'] ?? '—') ?><?php if ($s['fecha_fin']): ?><span class="d-block small text-body-secondary">hasta <?= e(fecha($s['fecha_fin'])) ?></span><?php endif; ?></td>
                    <td><?= insignia_estado($s['estado_membresia']) ?></td>
                    <?php if ($puedeCobrar): ?>
                        <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="<?= url('empleado/pagos.php?socio=' . $s['id']) ?>">Registrar pago</a></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
