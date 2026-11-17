<?php
/**
 * Tarea programada diaria: avisos de vencimiento de membresías (7, 3 y 1 día).
 *
 * Se ejecuta desde la terminal, no desde el navegador:
 *   php tareas/notificar_vencimientos.php
 *
 * En Windows (Laragon) se programa con el Programador de tareas; en Linux con cron.
 * Las instrucciones están en el README, sección "Tarea programada".
 *
 * Es seguro ejecutarla más de una vez al día: no repite avisos ni correos.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Esta tarea sólo se ejecuta desde la terminal.');
}

require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/vencimientos.php';

// Evita dos ejecuciones al mismo tiempo (por ejemplo, la programada y una manual)
$carpetaLogs = ROOT_PATH . '/storage/logs';
if (!is_dir($carpetaLogs)) {
    mkdir($carpetaLogs, 0775, true);
}
$candado = fopen($carpetaLogs . '/notificar_vencimientos.lock', 'c');
if (!$candado || !flock($candado, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "La tarea ya se está ejecutando.\n");
    exit(1);
}

try {
    $r = procesar_vencimientos();
} catch (Throwable $e) {
    $mensaje = date('Y-m-d H:i:s') . ' notificar_vencimientos ERROR: ' . $e->getMessage() . "\n";
    file_put_contents($carpetaLogs . '/tareas.log', $mensaje, FILE_APPEND);
    fwrite(STDERR, $mensaje);
    exit(1);
} finally {
    flock($candado, LOCK_UN);
    fclose($candado);
}

echo 'MaxisGym, revisión de vencimientos ' . date('d/m/Y H:i') . PHP_EOL;
echo '  Membresías marcadas como vencidas: ' . $r['vencidas'] . PHP_EOL;
echo '  Avisos nuevos (7, 3 y 1 día):      ' . $r['avisos_nuevos'] . PHP_EOL;
echo '  Correos enviados a socios:          ' . $r['correos'] . PHP_EOL;
if ($r['fallidos']) {
    echo '  Correos que fallaron (se reintentan mañana): ' . $r['fallidos'] . PHP_EOL;
}
exit($r['fallidos'] ? 2 : 0);
