<?php
/**
 * API (AJAX): búsqueda de socios.
 *   GET api/buscar_socios.php?q=juan            → JSON (hasta 10 socios)
 *   GET api/buscar_socios.php?q=juan&limite=50  → hasta 50 (máximo 50)
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

$limite = max(1, min(50, (int) parametro('limite', '10')));
$resultado = buscar_socios($q, 'activos', $limite);

$socios = array_map(fn($s) => [
    'id'            => (int) $s['id'],
    'nombre'        => $s['nombre'] . ' ' . $s['apellido'],
    'email'         => $s['email'],
    'telefono'      => $s['telefono'] ?? '',
    'plan'          => $s['nombre_plan'],
    'plan_id'       => null,
    'estado'        => $s['estado_membresia'],
    'inicio'        => $s['fecha_inicio'] ? fecha($s['fecha_inicio']) : null,
    'vence'         => $s['fecha_fin'] ? fecha($s['fecha_fin']) : null,
    'alta'          => fecha($s['created_at']),
], $resultado['socios']);

// Plan de la última membresía, para sugerirlo al renovar
foreach ($socios as &$s) {
    $s['plan_id'] = ($v = valor('SELECT plan_id FROM membresias WHERE usuario_id = ? ORDER BY fecha_fin DESC LIMIT 1', [$s['id']]))
        ? (int) $v : null;
}
unset($s);

responder_json(['ok' => true, 'socios' => $socios, 'total' => $resultado['total']]);
