<?php
/**
 * Consultar información de socios — permiso socios.consultar.
 *
 * Buscador con sugerencias: mientras se escribe aparece una lista con los
 * socios que coinciden (AJAX a api/buscar_socios.php). Al elegir uno se
 * muestra su ficha; con Enter (o "Ver todos") se muestran todas las
 * coincidencias en una tabla, p. ej. todos los que se llaman igual.
 * Sin JavaScript, el formulario funciona con un envío normal (tabla).
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/socios.php';

$yo = requiere_permiso('socios.consultar');

$q = parametro('q');
$resultado = $q !== '' ? buscar_socios($q, 'activos', 50) : ['socios' => [], 'total' => 0];
$puedeCobrar = tiene_permiso('pagos.registrar');

$titulo = 'Consultar socios';
$scripts = ['consultar.js'];
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<form method="get" action="<?= url('empleado/consultar.php') ?>" class="buscador buscador-grande" role="search" id="form-consulta"
      data-api="<?= url('api/buscar_socios.php') ?>" data-pagos="<?= $puedeCobrar ? url('empleado/pagos.php') : '' ?>">
    <label class="visually-hidden" for="q">Buscar socios por nombre, correo o ID</label>
    <i class="bi bi-search" aria-hidden="true"></i>
    <input class="form-control" id="q" name="q" type="search" value="<?= e($q) ?>"
           placeholder="Nombre, correo o ID del socio" autocomplete="off" autofocus
           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="sugerencias"
           aria-describedby="resumen-consulta">
    <button class="btn btn-dark" type="submit">Ver todos</button>
    <div class="list-group buscador-resultados" id="sugerencias" role="listbox" aria-label="Socios que coinciden" hidden></div>
</form>

<p class="text-body-secondary small mt-2 mb-4" id="resumen-consulta" aria-live="polite">
    <?php if ($q === ''): ?>
        Escribe y elige un socio de la lista para ver su ficha. Presiona Enter para ver todas las coincidencias.
    <?php else: ?>
        <?= e($resultado['total'] . ($resultado['total'] === 1 ? ' socio coincide' : ' socios coinciden') . ' con "' . $q . '".') ?>
    <?php endif; ?>
</p>

<!-- Ficha del socio elegido en la lista (la llena assets/js/consultar.js) -->
<section id="ficha-socio" class="mb-4" hidden></section>

<div class="tarjeta-tabla" id="tabla-consulta" <?= $q === '' ? 'hidden' : '' ?>>
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Plan</th><th>Estado</th><?= $puedeCobrar ? '<th><span class="visually-hidden">Acciones</span></th>' : '' ?></tr></thead>
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
            <?php if ($q !== '' && !$resultado['socios']): ?>
                <tr><td colspan="<?= $puedeCobrar ? 7 : 6 ?>" class="text-body-secondary">Ningún socio activo coincide.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
