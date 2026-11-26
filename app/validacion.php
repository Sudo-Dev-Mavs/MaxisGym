<?php
/**
 * Validaciones de datos personales usadas en Registro, Mi perfil,
 * Gestión de socios, Gestión de empleados y Alta de socios.
 */

/** Edades aceptadas en la fecha de nacimiento. */
const EDAD_MINIMA = 12;
const EDAD_MAXIMA = 110;

/**
 * Nombres y apellidos: sólo letras (incluye acentos, ñ y ü). Las palabras se
 * separan con un espacio, o con guion o apóstrofo en nombres como
 * "Ruiz-Esparza" o "D'Angelo". Sin números ni otros símbolos.
 */
const PATRON_NOMBRE = '/^\p{L}[\p{L}\p{M}]*(?:[ \'’-]\p{L}[\p{L}\p{M}]*)*$/u';

/** Mensaje de error para un nombre o apellido, o null si está bien. $que: "el nombre", "tu apellido"... */
function error_nombre(string $valor, string $que): ?string
{
    if ($valor === '' || mb_strlen($valor) > 60) {
        return 'Escribe ' . $que . ' (máximo 60 caracteres).';
    }
    if (!preg_match(PATRON_NOMBRE, $valor)) {
        return ucfirst($que) . ' sólo puede llevar letras, sin números ni símbolos.';
    }
    return null;
}

/** Recorta y deja un solo espacio entre palabras ("Juan   Pablo" → "Juan Pablo"). */
function limpiar_nombre(string $valor): string
{
    return trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor);
}

/** Fechas de nacimiento aceptadas (Y-m-d): [más antigua, más reciente]. */
function rango_fecha_nacimiento(): array
{
    return [
        (new DateTime('today -' . EDAD_MAXIMA . ' years'))->format('Y-m-d'),
        (new DateTime('today -' . EDAD_MINIMA . ' years'))->format('Y-m-d'),
    ];
}

/** Atributos min y max para que el calendario sólo muestre fechas aceptadas. */
function limites_fecha_nacimiento(): string
{
    [$min, $max] = rango_fecha_nacimiento();
    return 'min="' . $min . '" max="' . $max . '"';
}

/**
 * Valida nombre, apellido, correo, teléfono y fecha de nacimiento.
 * Devuelve un arreglo campo => mensaje (vacío si todo está bien).
 *
 * $idExcluir: id del usuario que se está editando (para que su propio
 *             correo no cuente como repetido). 0 al crear.
 */
function validar_persona(array $d, int $idExcluir = 0, bool $telefonoObligatorio = false, bool $fechaObligatoria = false): array
{
    $errores = [];

    if ($msg = error_nombre($d['nombre'] ?? '', 'el nombre')) {
        $errores['nombre'] = $msg;
    }
    if ($msg = error_nombre($d['apellido'] ?? '', 'el apellido')) {
        $errores['apellido'] = $msg;
    }

    $email = $d['email'] ?? '';
    if (!correo_valido($email) || mb_strlen($email) > 120) {
        $errores['email'] = 'Escribe un correo válido, por ejemplo nombre@correo.com.';
    } elseif (valor('SELECT COUNT(*) FROM usuarios WHERE email = ? AND id <> ?', [$email, $idExcluir]) > 0) {
        $errores['email'] = 'Ese correo ya lo usa otra cuenta.';
    }

    $tel = $d['telefono'] ?? '';
    if ($tel === '' && $telefonoObligatorio) {
        $errores['telefono'] = 'Escribe el teléfono.';
    } elseif ($tel !== '' && !preg_match('/^[0-9 +()\-]{7,20}$/', $tel)) {
        $errores['telefono'] = 'Escribe un teléfono de 7 a 20 dígitos (números, espacios o guiones).';
    }

    $f = $d['fecha_nacimiento'] ?? '';
    if ($f === '') {
        if ($fechaObligatoria) {
            $errores['fecha_nacimiento'] = 'Escribe la fecha de nacimiento.';
        }
    } else {
        $fecha = DateTime::createFromFormat('!Y-m-d', $f);
        [$min, $max] = rango_fecha_nacimiento();
        if (!$fecha || $fecha->format('Y-m-d') !== $f) {
            $errores['fecha_nacimiento'] = 'Escribe una fecha válida.';
        } elseif ($fecha > new DateTime($max)) {
            $errores['fecha_nacimiento'] = 'Debe tener al menos ' . EDAD_MINIMA . ' años.';
        } elseif ($fecha < new DateTime($min)) {
            $errores['fecha_nacimiento'] = 'Revisa el año de nacimiento: la fecha más antigua permitida es el ' . fecha($min) . '.';
        }
    }

    return $errores;
}

/** Lee del POST los campos de una persona, ya recortados (correo en minúsculas). */
function datos_persona_post(): array
{
    return [
        'nombre'           => limpiar_nombre(entrada('nombre')),
        'apellido'         => limpiar_nombre(entrada('apellido')),
        'email'            => mb_strtolower(entrada('email')),
        'telefono'         => entrada('telefono'),
        'fecha_nacimiento' => entrada('fecha_nacimiento'),
    ];
}

/** Ayudantes de vista para mostrar errores en formularios de Bootstrap. */
function clase_invalida(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? ' is-invalid' : '';
}

function mensaje_error(array $errores, string $campo): string
{
    return isset($errores[$campo]) ? '<div class="invalid-feedback">' . e($errores[$campo]) . '</div>' : '';
}
