<?php
/**
 * Encabezado de los paneles (administración, recepción y socio).
 * Antes de incluirlo, la página define:
 *   $titulo  — título que se muestra arriba
 *   $yo      — usuario actual (lo devuelve requiere_permiso())
 */
require_once ROOT_PATH . '/app/menu.php';
require_once ROOT_PATH . '/app/avisos.php';
require_once ROOT_PATH . '/app/mensajes.php';
require_once ROOT_PATH . '/app/componentes.php';

$titulo = $titulo ?? 'Panel';
$actual = pagina_actual();
$menu = menu_del_usuario();
// Contadores que se muestran junto a una opción del menú: ruta => [cantidad, texto para lector de pantalla]
$insignias = [
    'cuenta/avisos.php'     => [tiene_permiso('anuncios.ver') ? avisos_sin_leer($yo) : 0, 'sin leer'],
    'empleado/mensajes.php' => [tiene_permiso('mensajes.atender') ? mensajes_pendientes() : 0, 'sin atender'],
];
$iniciales = mb_strtoupper(mb_substr($yo['nombre'], 0, 1) . mb_substr($yo['apellido'], 0, 1));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0E1013">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($titulo) ?> · MaxisGym</title>
    <link rel="icon" href="<?= asset('img/logo.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
</head>
<body class="panel">
<a class="visually-hidden-focusable saltar" href="#contenido">Saltar al contenido</a>

<div class="panel-envoltura">
    <aside class="lateral offcanvas-lg offcanvas-start" tabindex="-1" id="lateral" aria-label="Menú del panel">
        <div class="lateral-cabecera">
            <a class="marca" href="<?= url(panel_de($yo)) ?>">
                <img src="<?= asset('img/logo.svg') ?>" alt="" width="30" height="30">
                <span>MaxisGym</span>
            </a>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                    data-bs-target="#lateral" aria-label="Cerrar menú"></button>
        </div>

        <nav class="lateral-menu">
            <?php foreach ($menu as $grupo => $opciones): ?>
                <p class="lateral-grupo"><?= e($grupo) ?></p>
                <ul class="list-unstyled mb-3">
                    <?php foreach ($opciones as [$ruta, $texto, $icono]): ?>
                        <?php $esActual = $actual === $ruta; ?>
                        <li>
                            <a class="lateral-enlace<?= $esActual ? ' activo' : '' ?>" href="<?= url($ruta) ?>"
                               <?= $esActual ? 'aria-current="page"' : '' ?>>
                                <i class="bi <?= e($icono) ?>" aria-hidden="true"></i>
                                <span><?= e($texto) ?></span>
                                <?php if (($insignias[$ruta][0] ?? 0) > 0): ?>
                                    <span class="badge rounded-pill text-bg-warning ms-auto"><?= (int) $insignias[$ruta][0] ?>
                                        <span class="visually-hidden"><?= e($insignias[$ruta][1]) ?></span></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <div class="lateral-pie">
            <a class="lateral-enlace" href="<?= url('index.php') ?>">
                <i class="bi bi-globe2" aria-hidden="true"></i><span>Ver sitio público</span>
            </a>
        </div>
    </aside>

    <div class="panel-cuerpo">
        <header class="panel-barra">
            <button class="btn btn-outline-secondary d-lg-none flex-shrink-0" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#lateral" aria-controls="lateral" aria-label="Abrir menú">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <h1 class="panel-titulo"><?= e($titulo) ?></h1>

            <div class="dropdown ms-auto">
                <button class="btn usuario-boton dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="avatar" aria-hidden="true"><?= e($iniciales) ?></span>
                    <span class="d-none d-sm-inline text-start lh-sm">
                        <span class="d-block fw-semibold"><?= e($yo['nombre'] . ' ' . $yo['apellido']) ?></span>
                        <span class="d-block small text-body-secondary"><?= e(ucfirst($yo['puesto'] ?: $yo['rol'])) ?></span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (tiene_permiso('perfil.editar')): ?>
                        <li><a class="dropdown-item" href="<?= url('cuenta/perfil.php') ?>"><i class="bi bi-person-circle me-2" aria-hidden="true"></i>Mi perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                        <form method="post" action="<?= url('logout.php') ?>">
                            <?= csrf_campo() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Cerrar sesión</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="panel-contenido" id="contenido">
            <?= flash_html() ?>
