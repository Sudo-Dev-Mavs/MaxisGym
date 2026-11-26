<?php
/**
 * Configuración local (NO se sube a GitHub).
 * Para correr con Docker: el host "mysql" es el contenedor de MySQL
 * (ambos conectados a la red docker "maxisgym-net").
 */
return [
    'db' => [
        // Dentro de Docker se usa el contenedor "mysql"; con PHP local, 127.0.0.1
        'host'     => is_file('/.dockerenv') ? 'mysql' : '127.0.0.1',
        'port'     => 3306,
        'username' => 'maxisgym',
        'password' => 'c1bdb0bccf12f3e7ab973258bb2b7cc4',
    ],
];
