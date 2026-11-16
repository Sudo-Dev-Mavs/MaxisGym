<?php
/**
 * Punto de arranque: todas las páginas incluyen este archivo primero.
 *
 *   require __DIR__ . '/app/bootstrap.php';          (páginas de la raíz)
 *   require __DIR__ . '/../app/bootstrap.php';       (páginas en admin/, empleado/, socio/)
 *
 * Carga la configuración, ajusta la zona horaria, inicia la sesión de forma
 * segura y deja disponibles las funciones de ayuda.
 */

define('ROOT_PATH', dirname(__DIR__));

$GLOBALS['config'] = require ROOT_PATH . '/config/config.php';

date_default_timezone_set(config('timezone'));

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Sesión con cookies seguras: no accesibles desde JavaScript y sólo para este sitio.
// Las tareas programadas (terminal) no usan sesión.
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_name('maxisgym_sesion');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require ROOT_PATH . '/app/helpers.php';
require ROOT_PATH . '/app/db.php';
require ROOT_PATH . '/app/csrf.php';
require ROOT_PATH . '/app/auth.php';

/**
 * Lee un valor de la configuración usando "punto" para niveles: config('db.host').
 */
function config(string $clave, $porDefecto = null)
{
    $valor = $GLOBALS['config'];
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $porDefecto;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}
