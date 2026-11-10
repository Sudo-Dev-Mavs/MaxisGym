<?php
/**
 * Autenticación y control de acceso por permisos.
 *
 * - La sesión guarda sólo el id del usuario ($_SESSION['usuario_id']).
 * - Los permisos se leen del catálogo (roles → rol_permiso → permisos),
 *   así que crear un rol nuevo o cambiar sus permisos no requiere tocar el código.
 * - Las páginas protegidas llaman a requiere_permiso('clave.del.permiso').
 */

const COOKIE_RECORDAR = 'maxisgym_recordar';
const DIAS_RECORDAR = 30;

/**
 * Usuario con sesión iniciada (con su rol y permisos) o null.
 * Si no hay sesión pero existe la cookie de "Recordarme", inicia sesión con ella.
 */
function usuario_actual(): ?array
{
    static $cargado = false;
    static $usuario = null;

    if ($cargado) {
        return $usuario;
    }
    $cargado = true;

    if (empty($_SESSION['usuario_id'])) {
        $id = id_desde_cookie_recordar();
        if ($id === null) {
            return null;
        }
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $id;
    }

    $usuario = cargar_usuario((int) $_SESSION['usuario_id']);

    // Si lo dieron de baja o lo eliminaron mientras tenía sesión, se cierra
    if ($usuario === null || !$usuario['activo']) {
        unset($_SESSION['usuario_id']);
        $usuario = null;
    }

    return $usuario;
}

/** Lee el usuario con el nombre de su rol y la lista de claves de permiso. */
function cargar_usuario(int $id): ?array
{
    $u = fila(
        'SELECT u.id, u.nombre, u.apellido, u.email, u.telefono, u.fecha_nacimiento,
                u.puesto, u.activo, u.rol_id, u.anuncios_vistos_hasta, r.nombre AS rol
           FROM usuarios u
           JOIN roles r ON r.id = u.rol_id
          WHERE u.id = ?',
        [$id]
    );
    if ($u === null) {
        return null;
    }

    $u['permisos'] = consulta(
        'SELECT p.clave
           FROM rol_permiso rp
           JOIN permisos p ON p.id = rp.permiso_id
          WHERE rp.rol_id = ?',
        [$u['rol_id']]
    )->fetchAll(PDO::FETCH_COLUMN);

    return $u;
}

/** ¿El usuario actual tiene el permiso? */
function tiene_permiso(string $clave): bool
{
    $u = usuario_actual();
    return $u !== null && in_array($clave, $u['permisos'], true);
}

/** ¿Tiene al menos uno de los permisos? */
function tiene_alguno(array $claves): bool
{
    foreach ($claves as $clave) {
        if (tiene_permiso($clave)) {
            return true;
        }
    }
    return false;
}

/**
 * Exige sesión iniciada. Si no hay, manda al login y recuerda a dónde quería ir.
 */
function requiere_sesion(): array
{
    $u = usuario_actual();
    if ($u === null) {
        if (es_ajax()) {
            responder_json(['ok' => false, 'mensaje' => 'Tu sesión terminó. Inicia sesión de nuevo.'], 401);
        }
        $_SESSION['despues_de_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('warning', 'Inicia sesión para continuar.');
        redirigir('login.php');
    }
    return $u;
}

/**
 * Exige sesión y al menos uno de los permisos indicados.
 * Si el usuario no lo tiene, lo regresa a su propio panel con un aviso
 * (el control se hace aquí, en el servidor, no sólo ocultando botones).
 */
function requiere_permiso(string ...$claves): array
{
    $u = requiere_sesion();
    if (!tiene_alguno($claves)) {
        if (es_ajax()) {
            responder_json(['ok' => false, 'mensaje' => 'No tienes permiso para esta acción.'], 403);
        }
        $destino = panel_de($u);
        // Evita un ciclo de redirecciones si su propio panel tampoco le corresponde
        if (str_ends_with(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/' . $destino)) {
            redirigir('sin_acceso.php');
        }
        flash('warning', 'No tienes permiso para entrar a esa sección.');
        redirigir($destino);
    }
    return $u;
}

/**
 * Panel que le corresponde a un usuario según sus permisos.
 * Se decide por permisos y no por el nombre del rol, para que un rol nuevo
 * (por ejemplo "Gerente") funcione sin cambiar código.
 */
function panel_de(array $u): string
{
    $p = $u['permisos'];
    if (array_intersect(['empleados.gestionar', 'reportes.ver', 'planes.gestionar'], $p)) {
        return 'admin/index.php';
    }
    if (array_intersect(['pagos.registrar', 'socios.alta', 'socios.consultar', 'anuncios.publicar'], $p)) {
        return 'empleado/index.php';
    }
    if (in_array('membresia.ver', $p, true)) {
        return 'socio/index.php';
    }
    if (in_array('perfil.editar', $p, true)) {
        return 'cuenta/perfil.php';
    }
    // Un rol sin permisos de ningún panel (por ejemplo, recién creado y sin configurar)
    return 'sin_acceso.php';
}

/** Inicia la sesión de un usuario ya verificado. */
function iniciar_sesion(array $usuario, bool $recordar): void
{
    session_regenerate_id(true); // evita fijación de sesión
    $_SESSION['usuario_id'] = (int) $usuario['id'];

    if ($recordar) {
        crear_cookie_recordar((int) $usuario['id']);
    }
}

/** Cierra la sesión y borra la cookie de "Recordarme". */
function cerrar_sesion(): void
{
    if (!empty($_SESSION['usuario_id'])) {
        consulta('UPDATE usuarios SET remember_token = NULL WHERE id = ?', [$_SESSION['usuario_id']]);
    }
    borrar_cookie(COOKIE_RECORDAR);

    $_SESSION = [];
    borrar_cookie(session_name());
    session_destroy();
}

/**
 * "Recordarme": se guarda en una cookie el id y un token aleatorio.
 * En la base de datos sólo se guarda el hash del token (si alguien
 * robara la base, no podría usar los tokens).
 */
function crear_cookie_recordar(int $usuarioId): void
{
    $token = bin2hex(random_bytes(32));
    consulta('UPDATE usuarios SET remember_token = ? WHERE id = ?', [hash('sha256', $token), $usuarioId]);
    setcookie(COOKIE_RECORDAR, $usuarioId . '|' . $token, [
        'expires'  => time() + DIAS_RECORDAR * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
}

/** Valida la cookie de "Recordarme" y devuelve el id del usuario, o null. */
function id_desde_cookie_recordar(): ?int
{
    $valor = $_COOKIE[COOKIE_RECORDAR] ?? '';
    if (!is_string($valor) || !preg_match('/^(\d+)\|([a-f0-9]{64})$/', $valor, $m)) {
        return null;
    }

    $u = fila('SELECT id, remember_token, activo FROM usuarios WHERE id = ?', [(int) $m[1]]);
    if ($u === null || !$u['activo'] || !$u['remember_token']
        || !hash_equals($u['remember_token'], hash('sha256', $m[2]))) {
        borrar_cookie(COOKIE_RECORDAR);
        return null;
    }

    // Se cambia el token en cada uso: una cookie robada deja de servir pronto
    crear_cookie_recordar((int) $u['id']);
    return (int) $u['id'];
}

function borrar_cookie(string $nombre): void
{
    setcookie($nombre, '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[$nombre]);
}

/** Id de un rol del catálogo por su nombre (por ejemplo 'socio'). */
function rol_id(string $nombre): ?int
{
    $id = valor('SELECT id FROM roles WHERE nombre = ? AND activo = 1', [$nombre]);
    return $id === null ? null : (int) $id;
}

/** Valida una contraseña nueva. Devuelve el mensaje de error o null si es válida. */
function error_password(string $password, string $confirmacion): ?string
{
    if (mb_strlen($password) < 8) {
        return 'La contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'La contraseña debe combinar letras y números.';
    }
    if ($password !== $confirmacion) {
        return 'Las contraseñas no coinciden.';
    }
    return null;
}
