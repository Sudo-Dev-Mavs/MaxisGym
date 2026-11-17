<?php
/**
 * Avisos automáticos de vencimiento (tabla notificaciones).
 *
 * La tarea diaria tareas/notificar_vencimientos.php llama a procesar_vencimientos():
 *   1. Marca como 'vencida' cada membresía activa cuya fecha de fin ya pasó.
 *   2. Registra un aviso por membresía que vence en 7, 3 o 1 día.
 *   3. Envía el correo de cada aviso al socio.
 * Recepción ve los avisos pendientes en su panel y los marca como atendidos.
 *
 * Umbral que le toca a cada membresía según los días que le quedan:
 *   7 a 4 días → aviso de 7   ·   3 a 2 días → aviso de 3   ·   1 a 0 días → aviso de 1
 * Si la tarea no corrió algún día (computadora apagada), el aviso se envía al
 * día siguiente en lugar de perderse. La llave única (membresia_id, dias_restantes)
 * impide que el mismo aviso se registre o se envíe dos veces.
 */
require_once ROOT_PATH . '/app/membresias.php';
require_once ROOT_PATH . '/app/correo.php';

const UMBRALES_AVISO = [7, 3, 1];

/** Expresión SQL con el umbral (7, 3 o 1) que corresponde a la membresía "m" hoy. */
const SQL_UMBRAL = "CASE WHEN DATEDIFF(m.fecha_fin, CURDATE()) <= 1 THEN 1
                         WHEN DATEDIFF(m.fecha_fin, CURDATE()) <= 3 THEN 3 ELSE 7 END";

/** Condición: el socio ya pagó un periodo posterior (no hace falta avisarle). */
const SQL_YA_RENOVO = "EXISTS (SELECT 1 FROM membresias sig
                                WHERE sig.usuario_id = m.usuario_id AND sig.estado <> 'cancelada'
                                  AND sig.fecha_inicio > m.fecha_fin)";

/**
 * Ejecuta la revisión diaria. Devuelve el resumen de lo que hizo.
 * Se puede correr varias veces el mismo día sin duplicar avisos ni correos.
 */
function procesar_vencimientos(): array
{
    $resumen = ['vencidas' => 0, 'avisos_nuevos' => 0, 'correos' => 0, 'fallidos' => 0];

    // 1. Estado de las membresías que ya terminaron
    $resumen['vencidas'] = consulta(
        "UPDATE membresias SET estado = 'vencida', updated_at = NOW()
          WHERE estado = 'activa' AND fecha_fin < CURDATE()"
    )->rowCount();

    // 2. Registrar el aviso que le toca hoy a cada membresía por vencer.
    //    No se avisa a quien ya renovó, ni con un umbral igual o mayor que la
    //    duración del periodo (un plan de 7 días no recibe "vence en 7 días" al pagarlo).
    $resumen['avisos_nuevos'] = consulta(
        'INSERT IGNORE INTO notificaciones (membresia_id, dias_restantes, created_at, updated_at)
         SELECT m.id, ' . SQL_UMBRAL . ', NOW(), NOW()
           FROM membresias m JOIN usuarios u ON u.id = m.usuario_id
          WHERE ' . SQL_VIGENTE . ' AND u.activo = 1
            AND m.fecha_fin <= CURDATE() + INTERVAL 7 DAY
            AND DATEDIFF(m.fecha_fin, m.fecha_inicio) > ' . SQL_UMBRAL . '
            AND NOT ' . SQL_YA_RENOVO
    )->rowCount();

    // 3. Correos pendientes del umbral actual (incluye reintentos de días anteriores
    //    que fallaron, mientras sigan siendo el aviso vigente de esa membresía)
    $pendientes = filas(
        'SELECT n.id, m.fecha_fin, DATEDIFF(m.fecha_fin, CURDATE()) AS dias,
                u.nombre, u.email, p.nombre_plan
           FROM notificaciones n
           JOIN membresias m ON m.id = n.membresia_id
           JOIN usuarios u ON u.id = m.usuario_id
           JOIN planes p ON p.id = m.plan_id
          WHERE n.correo_enviado = 0 AND n.dias_restantes = ' . SQL_UMBRAL . '
            AND ' . SQL_VIGENTE . ' AND u.activo = 1 AND NOT ' . SQL_YA_RENOVO . '
          ORDER BY m.fecha_fin, n.id'
    );
    foreach ($pendientes as $n) {
        if (enviar_correo($n['email'], asunto_vencimiento((int) $n['dias']), correo_vencimiento($n))) {
            consulta('UPDATE notificaciones SET correo_enviado = 1, fecha_envio = NOW(), updated_at = NOW() WHERE id = ?', [$n['id']]);
            $resumen['correos']++;
        } else {
            $resumen['fallidos']++;   // queda en 0 y se reintenta en la siguiente ejecución
        }
    }

    guardar_ultima_revision($resumen);
    return $resumen;
}

function asunto_vencimiento(int $dias): string
{
    return 'Tu membresía MaxisGym ' . texto_dias($dias);
}

/** Cuerpo del correo al socio. */
function correo_vencimiento(array $n): string
{
    $horario = implode('<br>', array_map('e', config('gimnasio.horario', [])));
    return '<p>Hola, ' . e($n['nombre']) . ':</p>'
        . '<p>Tu <strong>' . e($n['nombre_plan']) . '</strong> ' . e(texto_dias((int) $n['dias']))
        . ' (<strong>' . e(fecha($n['fecha_fin'])) . '</strong>).</p>'
        . '<p>Para seguir entrenando sin interrupciones, renueva en recepción. Nuestro horario:</p>'
        . '<p style="margin-left:12px">' . $horario . '</p>'
        . boton_correo('Ver mi membresía', url_absoluta('socio/membresia.php'))
        . '<p style="color:#6b747d;font-size:13px">Si ya renovaste, ignora este mensaje.</p>';
}

/**
 * Avisos que recepción todavía no atiende: uno por membresía (el más reciente),
 * sin contar a quien ya renovó ni a socios dados de baja.
 */
function avisos_vencimiento_pendientes(): array
{
    return filas(
        'SELECT m.id AS membresia_id, m.fecha_fin, DATEDIFF(m.fecha_fin, CURDATE()) AS dias,
                u.id AS usuario_id, u.nombre, u.apellido, u.telefono, u.email, p.nombre_plan,
                n.dias_restantes AS aviso, n.correo_enviado, n.fecha_envio
           FROM notificaciones n
           JOIN membresias m ON m.id = n.membresia_id
           JOIN usuarios u ON u.id = m.usuario_id
           JOIN planes p ON p.id = m.plan_id
          WHERE n.atendida = 0 AND u.activo = 1 AND m.estado <> \'cancelada\' AND NOT ' . SQL_YA_RENOVO . '
            -- sólo el aviso más reciente de cada membresía (7 → 3 → 1)
            AND n.dias_restantes = (SELECT MIN(n2.dias_restantes) FROM notificaciones n2
                                     WHERE n2.membresia_id = n.membresia_id AND n2.atendida = 0)
          ORDER BY m.fecha_fin, u.nombre'
    );
}

/** Marca como atendidos los avisos de una membresía. Devuelve cuántos cambió. */
function atender_avisos_membresia(int $membresiaId): int
{
    return consulta(
        'UPDATE notificaciones SET atendida = 1, updated_at = NOW() WHERE membresia_id = ? AND atendida = 0',
        [$membresiaId]
    )->rowCount();
}

/** Texto para la columna de días en el panel: "vence mañana", "venció hace 2 días"... */
function texto_dias_aviso(int $dias): string
{
    if ($dias >= 0) {
        return texto_dias($dias);
    }
    return $dias === -1 ? 'venció ayer' : 'venció hace ' . abs($dias) . ' días';
}

// ---------- Registro de la última ejecución ----------

function archivo_ultima_revision(): string
{
    return ROOT_PATH . '/storage/logs/ultima_revision.json';
}

function guardar_ultima_revision(array $resumen): void
{
    $carpeta = dirname(archivo_ultima_revision());
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0775, true);
    }
    file_put_contents(archivo_ultima_revision(), json_encode(['fecha' => date('Y-m-d H:i:s')] + $resumen));
    file_put_contents(
        $carpeta . '/tareas.log',
        sprintf("%s notificar_vencimientos: %d vencidas, %d avisos nuevos, %d correos enviados, %d fallidos\n",
            date('Y-m-d H:i:s'), $resumen['vencidas'], $resumen['avisos_nuevos'], $resumen['correos'], $resumen['fallidos']),
        FILE_APPEND
    );
}

/**
 * Última ejecución de la tarea: ['fecha' => 'AAAA-MM-DD HH:MM:SS', 'atrasada' => bool, ...] o null.
 * Se considera atrasada si pasaron más de 26 horas (corre una vez al día).
 */
function ultima_revision(): ?array
{
    $archivo = archivo_ultima_revision();
    if (!is_file($archivo)) {
        return null;
    }
    $datos = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($datos) || empty($datos['fecha']) || ($t = strtotime($datos['fecha'])) === false) {
        return null;
    }
    $datos['atrasada'] = time() - $t > 26 * 3600;
    return $datos;
}

/** "hoy a las 06:00", "ayer a las 06:00" o "el 20/10/2026 a las 06:00". */
function texto_ultima_revision(string $fecha): string
{
    $dia = substr($fecha, 0, 10);
    $hora = substr($fecha, 11, 5);
    if ($dia === date('Y-m-d')) {
        return 'hoy a las ' . $hora;
    }
    if ($dia === date('Y-m-d', strtotime('-1 day'))) {
        return 'ayer a las ' . $hora;
    }
    return 'el ' . fecha($dia) . ' a las ' . $hora;
}
