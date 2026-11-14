<?php
/**
 * Menú lateral de los paneles.
 * Cada opción indica qué permisos la muestran; el usuario sólo ve las
 * opciones de los permisos que tiene su rol en la base de datos.
 * (Ocultar la opción es sólo comodidad: cada página vuelve a revisar el
 * permiso con requiere_permiso().)
 */

const PERMISOS_ADMIN = ['empleados.gestionar', 'reportes.ver', 'planes.gestionar'];
const PERMISOS_RECEPCION = ['pagos.registrar', 'socios.alta', 'socios.consultar', 'anuncios.publicar'];

function menu_panel(): array
{
    return [
        'Administración' => [
            ['admin/index.php',     'Panel',            'bi-speedometer2',      PERMISOS_ADMIN],
            ['admin/socios.php',    'Socios',           'bi-people',            ['socios.gestionar']],
            ['admin/empleados.php', 'Empleados',        'bi-person-badge',      ['empleados.gestionar']],
            ['admin/planes.php',    'Planes',           'bi-tags',              ['planes.gestionar']],
            ['admin/reportes.php',  'Reportes',         'bi-graph-up',          ['reportes.ver']],
            ['admin/roles.php',     'Roles y permisos', 'bi-shield-lock',       ['empleados.gestionar']],
        ],
        'Recepción' => [
            ['empleado/index.php',      'Panel de recepción', 'bi-grid',         PERMISOS_RECEPCION],
            ['empleado/pagos.php',      'Registrar pagos',    'bi-cash-coin',    ['pagos.registrar']],
            ['empleado/alta_socio.php', 'Alta de socios',     'bi-person-plus',  ['socios.alta']],
            ['empleado/consultar.php',  'Consultar socios',   'bi-search',       ['socios.consultar']],
            ['empleado/anuncios.php',   'Publicar avisos',    'bi-megaphone',    ['anuncios.publicar']],
        ],
        'Mi cuenta' => [
            ['socio/index.php',     'Inicio',              'bi-house',              ['membresia.ver']],
            ['socio/membresia.php', 'Mi membresía',        'bi-credit-card-2-front', ['membresia.ver']],
            ['socio/pagos.php',     'Historial de pagos',  'bi-receipt',            ['pagos.ver_propios']],
            ['cuenta/avisos.php',   'Avisos del gimnasio', 'bi-bell',               ['anuncios.ver']],
            ['cuenta/perfil.php',   'Mi perfil',           'bi-person-circle',      ['perfil.editar']],
        ],
    ];
}

/** Menú filtrado para el usuario actual (sin grupos vacíos). */
function menu_del_usuario(): array
{
    $resultado = [];
    foreach (menu_panel() as $grupo => $opciones) {
        $visibles = array_values(array_filter($opciones, fn($o) => tiene_alguno($o[3])));
        if ($visibles) {
            $resultado[$grupo] = $visibles;
        }
    }
    return $resultado;
}

/** Ruta de la página actual relativa al proyecto, p. ej. "admin/planes.php". */
function pagina_actual(): string
{
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
    $raiz = str_replace('\\', '/', realpath(ROOT_PATH) ?: ROOT_PATH);
    return ltrim(substr($script, strlen($raiz)), '/');
}
