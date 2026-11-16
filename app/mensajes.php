<?php
/**
 * Mensajes de la pantalla Contacto (tabla mensajes_contacto).
 *
 * Un mensaje está ATENDIDO cuando tiene fecha_atencion. Se usa la fecha y no
 * atendido_por porque, si se elimina al empleado que lo atendió, la llave
 * foránea pone atendido_por en NULL (ON DELETE SET NULL) y el mensaje no debe
 * volver a aparecer como pendiente.
 *
 * Las solicitudes de renovación que envían los socios desde "Mi membresía"
 * llegan aquí también (asunto "Solicitud de renovación: ...").
 */
require_once ROOT_PATH . '/app/membresias.php';

const FILTROS_MENSAJES = ['pendientes' => 'Sin atender', 'atendidos' => 'Atendidos', 'todos' => 'Todos'];
const PREFIJO_RENOVACION = 'Solicitud de renovación';

/** Mensajes sin atender (para insignias y contadores). */
function mensajes_pendientes(): int
{
    return (int) valor('SELECT COUNT(*) FROM mensajes_contacto WHERE fecha_atencion IS NULL');
}

/** Solicitudes de renovación sin atender. */
function renovaciones_pendientes(): int
{
    return (int) valor(
        'SELECT COUNT(*) FROM mensajes_contacto WHERE fecha_atencion IS NULL AND asunto LIKE ?',
        [PREFIJO_RENOVACION . '%']
    );
}

/**
 * Busca mensajes por estado ('pendientes', 'atendidos', 'todos') y texto
 * (nombre, correo o asunto). Devuelve ['mensajes' => [...], 'total' => n].
 */
function buscar_mensajes(string $estado, string $texto, int $limite, int $desde): array
{
    $where = [];
    $params = [];
    if ($estado === 'pendientes') {
        $where[] = 'mc.fecha_atencion IS NULL';
    } elseif ($estado === 'atendidos') {
        $where[] = 'mc.fecha_atencion IS NOT NULL';
    }
    if ($texto !== '') {
        $where[] = "(CONCAT(mc.nombre, ' ', mc.apellido) LIKE ? OR mc.email LIKE ? OR mc.asunto LIKE ?)";
        $like = '%' . $texto . '%';
        array_push($params, $like, $like, $like);
    }
    $condicion = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $total = (int) valor("SELECT COUNT(*) FROM mensajes_contacto mc $condicion", $params);
    $mensajes = filas(
        "SELECT mc.*, CONCAT(a.nombre, ' ', a.apellido) AS atendio,
                s.activo AS socio_activo
           FROM mensajes_contacto mc
           LEFT JOIN usuarios a ON a.id = mc.atendido_por
           LEFT JOIN usuarios s ON s.id = mc.usuario_id
           $condicion
          ORDER BY mc.created_at DESC, mc.id DESC
          LIMIT " . (int) $limite . ' OFFSET ' . (int) $desde,
        $params
    );
    return ['mensajes' => $mensajes, 'total' => $total];
}

function es_solicitud_renovacion(array $m): bool
{
    return str_starts_with($m['asunto'], PREFIJO_RENOVACION);
}

/** Marca un mensaje como atendido por $empleadoId. Devuelve false si ya estaba atendido. */
function atender_mensaje(int $id, int $empleadoId): bool
{
    return consulta(
        'UPDATE mensajes_contacto SET atendido_por = ?, fecha_atencion = NOW(), updated_at = NOW()
          WHERE id = ? AND fecha_atencion IS NULL',
        [$empleadoId, $id]
    )->rowCount() > 0;
}

/** Regresa un mensaje a "sin atender" (por si se marcó por error). */
function reabrir_mensaje(int $id): bool
{
    return consulta(
        'UPDATE mensajes_contacto SET atendido_por = NULL, fecha_atencion = NULL, updated_at = NOW()
          WHERE id = ? AND fecha_atencion IS NOT NULL',
        [$id]
    )->rowCount() > 0;
}

/** Texto de quién y cuándo atendió: "Atendió Laura Gómez el 27/10/2026 a las 17:30". */
function texto_atencion(array $m): string
{
    $quien = $m['atendio'] ? 'Atendió ' . $m['atendio'] : 'Atendido (el usuario ya no existe)';
    return $quien . ' el ' . fecha(substr($m['fecha_atencion'], 0, 10)) . ' a las ' . substr($m['fecha_atencion'], 11, 5);
}

/** Tiempo transcurrido: "hace 5 minutos", "hace 3 horas", "ayer", "hace 4 días" o la fecha. */
function hace(string $fechaHora): string
{
    $segundos = time() - strtotime($fechaHora);
    if ($segundos < 60) {
        return 'hace un momento';
    }
    if ($segundos < 3600) {
        $n = intdiv($segundos, 60);
        return 'hace ' . $n . ($n === 1 ? ' minuto' : ' minutos');
    }
    $dias = (int) (new DateTime(substr($fechaHora, 0, 10)))->diff(new DateTime('today'))->days;
    if ($dias === 0) {
        $n = intdiv($segundos, 3600);
        return 'hace ' . $n . ($n === 1 ? ' hora' : ' horas');
    }
    if ($dias === 1) {
        return 'ayer';
    }
    return $dias <= 30 ? "hace $dias días" : fecha(substr($fechaHora, 0, 10));
}

/** Enlace mailto para responder con el asunto "Re: ...". */
function enlace_responder(array $m): string
{
    return 'mailto:' . rawurlencode($m['email']) . '?subject=' . rawurlencode('Re: ' . $m['asunto'] . ' · MaxisGym');
}
