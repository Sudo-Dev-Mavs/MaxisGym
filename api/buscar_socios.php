<?php
/**
 * API (AJAX): búsqueda de socios.
 *   GET api/buscar_socios.php?q=juan            → JSON
 * La usan "Registrar pagos" y "Consultar socios" para buscar mientras se escribe.
 * Requiere sesión con permiso para registrar pagos o consultar socios.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/socios.php';

requiere_permiso('socios.consultar', 'pagos.registrar', 'socios.gestionar');

$q = parametro('q');
if (mb_strlen($q) < 1) {
    responder_json(['ok' => true, 'socios' => [], 'total' => 0]);
}

$resultado = buscar_socios($q, 'activos', 10);

$socios = array_map(fn($s) => [
    'id'            => (int) $s['id'],
    'nombre'        => $s['nombre'] . ' ' . $s['apellido'],
    'email'         => $s['email'],
    'telefono'      => $s['telefono'] ?? '',
    'plan'          => $s['nombre_plan'],
    'plan_id'       => null,
    'estado'        => $s['estado_membresia'],
    'vence'         => $s['fecha_fin'] ? fecha($s['fecha_fin']) : null,
], $resultado['socios']);

// Plan de la última membresía, para sugerirlo al renovar
foreach ($socios as &$s) {
    $s['plan_id'] = ($v = valor('SELECT plan_id FROM membresias WHERE usuario_id = ? ORDER BY fecha_fin DESC LIMIT 1', [$s['id']]))
        ? (int) $v : null;
}
unset($s);

responder_json(['ok' => true, 'socios' => $socios, 'total' => $resultado['total']]);
