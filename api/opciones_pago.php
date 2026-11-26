<?php
/**
 * API (AJAX): qué se le puede cobrar a un socio en "Registrar pagos".
 *   GET api/opciones_pago.php?socio=13 → JSON con el modo (inscripción, renovación, mejora o bloqueado),
 *                                        el aviso para recepción y los planes con su monto.
 *   GET api/opciones_pago.php          → todos los planes a su precio (aún no se elige socio).
 * Las reglas están en opciones_de_pago() (app/pagos.php); el servidor las vuelve a revisar al registrar.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/pagos.php';
require_once ROOT_PATH . '/app/socios.php';

requiere_permiso('pagos.registrar');

$id = (int) parametro('socio', '0');
if ($id) {
    $socio = socio_por_id($id);
    if (!$socio || !$socio['activo']) {
        responder_json(['ok' => false, 'mensaje' => 'Ese socio no existe o está dado de baja.'], 404);
    }
}

responder_json(['ok' => true, 'opciones' => opciones_de_pago($id ?: null)]);
