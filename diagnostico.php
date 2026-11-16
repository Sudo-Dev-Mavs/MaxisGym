<?php
/**
 * Diagnóstico de instalación.
 * Abre http://localhost/maxisgym/diagnostico.php para revisar que todo esté listo.
 * Sólo funciona con 'debug' => true en config/config.php.
 */
require __DIR__ . '/app/bootstrap.php';

if (!config('debug')) {
    http_response_code(404);
    exit;
}

$revisiones = [];

$revisiones[] = ['PHP 8.0 o superior', version_compare(PHP_VERSION, '8.0.0', '>='), 'Versión actual: ' . PHP_VERSION];
foreach (['pdo_mysql', 'mbstring'] as $ext) {
    $ok = extension_loaded($ext);
    $revisiones[] = ["Extensión $ext", $ok, $ok ? 'Cargada' : 'Actívala en Laragon: Menú > PHP > Extensions'];
}

$tablas = [];
try {
    $tablas = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $revisiones[] = ['Conexión a MySQL', true, 'Base de datos: ' . config('db.database')];
} catch (Throwable $ex) {
    $revisiones[] = ['Conexión a MySQL', false, $ex->getMessage()];
}

$esperadas = ['roles', 'permisos', 'rol_permiso', 'metodos_pago', 'usuarios', 'planes', 'beneficios',
    'plan_beneficio', 'membresias', 'pagos', 'anuncios', 'notificaciones', 'password_reset_tokens', 'mensajes_contacto'];
$faltan = array_diff($esperadas, $tablas);
$revisiones[] = ['Las 14 tablas existen', !$faltan, $faltan ? 'Faltan: ' . implode(', ', $faltan) . '. Ejecuta database/maxisgym.sql' : count($tablas) . ' tablas'];

// Permisos agregados después de la Fase 2 (para bases cargadas antes)
if (!$faltan) {
    $faltaPermiso = !valor("SELECT COUNT(*) FROM permisos WHERE clave = 'mensajes.atender'");
    $revisiones[] = ['Permiso mensajes.atender', !$faltaPermiso,
        $faltaPermiso ? 'Ejecuta database/actualizaciones/001_permiso_mensajes.sql' : 'Registrado en el catálogo'];
}

$revisiones[] = ['Ruta base detectada', true, url() ];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico · <?= e(config('app_name')) ?></title>
    <link rel="stylesheet" href="<?= asset('vendor/bootstrap/bootstrap.min.css') ?>">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 760px">
    <h1 class="h3 mb-4">Diagnóstico de instalación</h1>
    <table class="table table-bordered bg-white">
        <thead><tr><th>Revisión</th><th>Estado</th><th>Detalle</th></tr></thead>
        <tbody>
        <?php foreach ($revisiones as [$nombre, $ok, $detalle]): ?>
            <tr>
                <td><?= e($nombre) ?></td>
                <td><?= $ok ? '<span class="badge text-bg-success">OK</span>' : '<span class="badge text-bg-danger">Falla</span>' ?></td>
                <td class="small"><?= e($detalle) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
