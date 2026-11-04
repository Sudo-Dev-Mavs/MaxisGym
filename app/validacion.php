<?php
/**
 * Validaciones de datos personales usadas en Registro, Mi perfil,
 * Gestión de socios, Gestión de empleados y Alta de socios.
 */

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

    if (($d['nombre'] ?? '') === '' || mb_strlen($d['nombre']) > 60) {
        $errores['nombre'] = 'Escribe el nombre (máximo 60 caracteres).';
    }
    if (($d['apellido'] ?? '') === '' || mb_strlen($d['apellido']) > 60) {
        $errores['apellido'] = 'Escribe el apellido (máximo 60 caracteres).';
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
        if (!$fecha || $fecha->format('Y-m-d') !== $f) {
            $errores['fecha_nacimiento'] = 'Escribe una fecha válida.';
        } elseif ($fecha > new DateTime('-12 years')) {
            $errores['fecha_nacimiento'] = 'Debe tener al menos 12 años.';
        } elseif ($fecha < new DateTime('-110 years')) {
            $errores['fecha_nacimiento'] = 'Revisa el año de nacimiento.';
        }
    }

    return $errores;
}

/** Lee del POST los campos de una persona, ya recortados (correo en minúsculas). */
function datos_persona_post(): array
{
    return [
        'nombre'           => entrada('nombre'),
        'apellido'         => entrada('apellido'),
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
