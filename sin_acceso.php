<?php
/**
 * Página para usuarios cuyo rol todavía no tiene permisos de ningún panel
 * (por ejemplo, un rol recién creado que el administrador no ha configurado).
 */
require __DIR__ . '/app/bootstrap.php';

$yo = requiere_sesion();

$titulo = 'Sin secciones asignadas · MaxisGym';
$activo = '';
require ROOT_PATH . '/app/vistas/publico_inicio.php';
?>
<section class="acceso">
    <div class="acceso-panel">
        <h1>Sin secciones asignadas</h1>
        <p class="acceso-sub">
            Hola, <?= e($yo['nombre']) ?>. Tu rol (<?= e($yo['rol']) ?>) todavía no tiene permisos para ninguna sección.
            Pide al administrador que se los asigne en Roles y permisos.
        </p>
        <form method="post" action="<?= url('logout.php') ?>">
            <?= csrf_campo() ?>
            <button class="btn btn-outline-dark w-100" type="submit">Cerrar sesión</button>
        </form>
    </div>
</section>
<?php require ROOT_PATH . '/app/vistas/publico_fin.php'; ?>
