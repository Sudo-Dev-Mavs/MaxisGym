<?php
/**
 * Avisos del gimnasio — permiso anuncios.ver.
 *
 * Muestra los avisos vigentes dirigidos al rol del usuario o a todos.
 * Los que se publicaron después de su última visita se marcan como "Nuevo"
 * y, al abrir esta pantalla, se actualiza su fecha anuncios_vistos_hasta
 * (una sola columna por usuario, sin importar cuántos avisos existan).
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/avisos.php';

$yo = requiere_permiso('anuncios.ver');

$vistosHasta = $yo['anuncios_vistos_hasta'] ?? '1970-01-01 00:00:00';
$avisos = avisos_para($yo);
marcar_avisos_vistos((int) $yo['id']);
$yo['anuncios_vistos_hasta'] = date('Y-m-d H:i:s'); // para que el menú ya no muestre pendientes

$titulo = 'Avisos del gimnasio';
require ROOT_PATH . '/app/vistas/panel_inicio.php';
?>

<p class="text-body-secondary">Comunicados recientes del gimnasio: cierres, horarios y novedades.</p>

<div class="lista-avisos" style="max-width: 820px">
    <?php if (!$avisos): ?>
        <div class="tarjeta text-center py-5">
            <i class="bi bi-bell-slash fs-1 text-body-tertiary" aria-hidden="true"></i>
            <p class="mt-2 mb-0">No hay avisos por ahora. Aquí aparecerán los comunicados del gimnasio.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($avisos as $a): ?>
        <?php $nuevo = $a['fecha_publicacion'] > $vistosHasta; ?>
        <article class="aviso<?= $a['importante'] ? ' aviso-importante' : '' ?>">
            <div class="d-flex justify-content-between gap-3">
                <h2 class="aviso-titulo">
                    <?php if ($a['importante']): ?><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <span class="visually-hidden">Importante:</span><?php endif; ?>
                    <?= e($a['titulo']) ?>
                </h2>
                <?php if ($nuevo): ?><span class="badge text-bg-warning align-self-start">Nuevo</span><?php endif; ?>
            </div>
            <p class="aviso-texto"><?= nl2br(e($a['contenido'])) ?></p>
            <p class="aviso-meta"><?= 'Publicado el ' . e(fecha($a['fecha_publicacion'], true))
                . ($a['fecha_expiracion'] ? ', vigente hasta el ' . e(fecha($a['fecha_expiracion'])) : '') . '.' ?></p>
        </article>
    <?php endforeach; ?>
</div>

<?php require ROOT_PATH . '/app/vistas/panel_fin.php'; ?>
