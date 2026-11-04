<?php
/**
 * Funciones de ayuda usadas en todo el sistema.
 */

/**
 * Escapa texto para mostrarlo en HTML (evita XSS).
 * Todo dato que venga de la base de datos o del usuario se imprime con e().
 */
function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/**
 * Ruta base del sitio, p. ej. "/maxisgym" en http://localhost/maxisgym.
 * Se detecta comparando la carpeta del proyecto con la raíz del servidor.
 */
function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $configurada = config('base_url');
    if ($configurada !== null) {
        return $base = rtrim($configurada, '/');
    }

    $raizServidor = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $raizProyecto = realpath(ROOT_PATH) ?: ROOT_PATH;
    $relativa = '';
    if ($raizServidor !== '' && str_starts_with($raizProyecto, $raizServidor)) {
        $relativa = substr($raizProyecto, strlen($raizServidor));
    }
    return $base = rtrim(str_replace('\\', '/', $relativa), '/');
}

/** URL absoluta dentro del sitio: url('admin/planes.php') → /maxisgym/admin/planes.php */
function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

/** URL de un archivo en assets/ con versión para evitar caché vieja. */
function asset(string $ruta): string
{
    $archivo = ROOT_PATH . '/assets/' . ltrim($ruta, '/');
    $version = is_file($archivo) ? filemtime($archivo) : 1;
    return url('assets/' . ltrim($ruta, '/')) . '?v=' . $version;
}

/** Redirige y termina la ejecución (el exit es obligatorio para que no siga el código). */
function redirigir(string $ruta): void
{
    $destino = preg_match('#^https?://#', $ruta) ? $ruta : url($ruta);
    header('Location: ' . $destino);
    exit;
}

/** Guarda un mensaje para mostrarlo en la siguiente página (tipo: success, danger, warning, info). */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Devuelve y borra los mensajes pendientes. */
function flash_obtener(): array
{
    $mensajes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $mensajes;
}

/** HTML de las alertas pendientes (Bootstrap). */
function flash_html(): string
{
    $html = '';
    foreach (flash_obtener() as $f) {
        $html .= '<div class="alert alert-' . e($f['tipo']) . ' alert-dismissible fade show" role="alert">'
            . e($f['mensaje'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button></div>';
    }
    return $html;
}

/** ¿La petición es POST? */
function es_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** ¿La petición viene de fetch()/AJAX? */
function es_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** Valor enviado por formulario (POST), recortado. */
function entrada(string $campo, string $porDefecto = ''): string
{
    $v = $_POST[$campo] ?? $porDefecto;
    return is_string($v) ? trim($v) : $porDefecto;
}

/** Valor de la URL (GET), recortado. */
function parametro(string $campo, string $porDefecto = ''): string
{
    $v = $_GET[$campo] ?? $porDefecto;
    return is_string($v) ? trim($v) : $porDefecto;
}

/** Recuerda lo que el usuario escribió para volver a llenar el formulario si hubo errores. */
function viejo(string $campo, string $porDefecto = ''): string
{
    return e($_SESSION['viejo'][$campo] ?? $porDefecto);
}

function guardar_viejo(array $datos): void
{
    unset($datos['password'], $datos['password_confirmacion'], $datos['csrf_token']);
    $_SESSION['viejo'] = $datos;
}

function limpiar_viejo(): void
{
    unset($_SESSION['viejo']);
}

/** Responde JSON (para AJAX) y termina. */
function responder_json(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Formatea dinero: 1500 → $1,500.00 */
function dinero($monto): string
{
    return '$' . number_format((float) $monto, 2);
}

/** Formatea fecha: 2026-10-01 → 01/10/2026 */
function fecha($valor, bool $conHora = false): string
{
    if (!$valor) {
        return '—';
    }
    $t = strtotime((string) $valor);
    return $t ? date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $t) : '—';
}

/** Valida un correo electrónico. */
function correo_valido(string $correo): bool
{
    return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Paginación de Bootstrap.
 * $params: parámetros de la URL que se conservan al cambiar de página (búsqueda, filtros).
 */
function paginacion(int $total, int $porPagina, int $pagina, string $ruta, array $params = []): string
{
    $paginas = (int) ceil($total / max(1, $porPagina));
    if ($paginas <= 1) {
        return '';
    }
    $enlace = fn(int $p) => url($ruta) . '?' . http_build_query($params + ['pagina' => $p]);
    $html = '<nav aria-label="Paginación"><ul class="pagination justify-content-end mb-0">';
    $html .= '<li class="page-item' . ($pagina <= 1 ? ' disabled' : '') . '"><a class="page-link" href="' . e($enlace(max(1, $pagina - 1))) . '" aria-label="Anterior">&laquo;</a></li>';
    for ($p = 1; $p <= $paginas; $p++) {
        $activa = $p === $pagina;
        $html .= '<li class="page-item' . ($activa ? ' active' : '') . '"><a class="page-link" href="' . e($enlace($p)) . '"'
            . ($activa ? ' aria-current="page"' : '') . '>' . $p . '</a></li>';
    }
    $html .= '<li class="page-item' . ($pagina >= $paginas ? ' disabled' : '') . '"><a class="page-link" href="' . e($enlace(min($paginas, $pagina + 1))) . '" aria-label="Siguiente">&raquo;</a></li>';
    return $html . '</ul></nav>';
}

/** Número de página pedido en la URL (mínimo 1). */
function pagina_actual_num(): int
{
    return max(1, (int) parametro('pagina', '1'));
}
