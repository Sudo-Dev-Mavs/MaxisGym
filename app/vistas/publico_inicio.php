<?php
/**
 * Encabezado y menú del sitio público.
 * Antes de incluirlo, la página define:
 *   $titulo       — título de la pestaña
 *   $activo       — 'inicio' | 'acerca' | 'planes' | 'contacto' (marca el menú)
 *   $navSobreFoto — true si la página abre con una foto a pantalla completa:
 *                   la barra queda transparente encima y se vuelve sólida al bajar
 *   $descripcion  — (opcional) texto para buscadores
 */
require_once ROOT_PATH . '/app/componentes.php';

$titulo = $titulo ?? config('app_name');
$activo = $activo ?? '';
$navSobreFoto = $navSobreFoto ?? false;
$descripcion = $descripcion ?? 'MaxisGym: área de pesas, zona de cardio y clases grupales cerca de Ciudad Universitaria. Consulta planes, horarios y tu membresía en línea.';
$menu = [
    'inicio'   => ['Inicio', 'index.php'],
    'acerca'   => ['Acerca de', 'acerca.php'],
    'planes'   => ['Planes', 'planes.php'],
    'contacto' => ['Contacto', 'contacto.php'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($titulo) ?></title>
    <meta name="description" content="<?= e($descripcion) ?>">
    <meta name="theme-color" content="#0E1013">
    <meta property="og:title" content="<?= e($titulo) ?>">
    <meta property="og:description" content="<?= e($descripcion) ?>">
    <meta property="og:type" content="website">
    <link rel="icon" href="<?= asset('img/logo.svg') ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="sitio<?= $navSobreFoto ? ' con-foto' : '' ?>">
<a class="visually-hidden-focusable saltar" href="#contenido">Saltar al contenido</a>

<header class="barra" id="barra">
    <nav class="navbar navbar-expand-lg navbar-dark" aria-label="Menú principal">
        <div class="envoltura barra-fila">
            <a class="marca" href="<?= url('index.php') ?>">
                <img src="<?= asset('img/logo.svg') ?>" alt="" width="34" height="34">
                <span>MaxisGym</span>
            </a>
            <button class="navbar-toggler barra-menu" type="button" data-bs-toggle="collapse" data-bs-target="#menu"
                    aria-controls="menu" aria-expanded="false" aria-label="Abrir menú">
                <span class="barra-menu-icono"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="menu">
                <ul class="navbar-nav barra-enlaces">
                    <?php foreach ($menu as $clave => [$texto, $ruta]): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= $activo === $clave ? ' active' : '' ?>"
                               <?= $activo === $clave ? 'aria-current="page"' : '' ?>
                               href="<?= url($ruta) ?>"><?= e($texto) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="barra-accion">
                    <?php if ($yo = usuario_actual()): ?>
                        <a class="boton boton-sm" href="<?= url(panel_de($yo)) ?>">Mi panel</a>
                    <?php elseif (basename($_SERVER['SCRIPT_NAME']) !== 'login.php'): ?>
                        <a class="boton boton-sm" href="<?= url('login.php') ?>">Iniciar sesión</a>
                    <?php else: ?>
                        <a class="boton boton-sm boton-contorno" href="<?= url('registro.php') ?>">Crear cuenta</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<main id="contenido">
