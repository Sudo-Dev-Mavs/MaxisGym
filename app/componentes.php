<?php
/**
 * Piezas de interfaz reutilizables (HTML que se repite en varias pantallas).
 * Cada función regresa una cadena de HTML ya escapada.
 */

/**
 * Imagen con varios tamaños (srcset).
 * $ruta es relativa a assets/, por ejemplo 'img/sitio/hero.jpg'.
 * Si existen 'hero-720.jpg', 'hero-1200.jpg'... junto a ella, se agregan al srcset
 * para que el celular descargue la versión ligera.
 * Regresa '' si la imagen no existe (la pantalla decide qué mostrar en su lugar).
 */
function foto(string $ruta, string $alt, array $op = []): string
{
    $archivo = ROOT_PATH . '/assets/' . $ruta;
    if (!is_file($archivo)) {
        return '';
    }
    $info = pathinfo($ruta);
    $base = $info['dirname'] . '/' . $info['filename'];
    [$ancho, $alto] = getimagesize($archivo);

    $fuentes = [];
    foreach (glob(ROOT_PATH . '/assets/' . $base . '-*.' . $info['extension']) as $variante) {
        if (preg_match('/-(\d+)\.\w+$/', $variante, $m)) {
            $fuentes[(int) $m[1]] = substr($variante, strlen(ROOT_PATH . '/assets/'));
        }
    }
    $fuentes[$ancho] = $ruta;
    ksort($fuentes);

    $srcset = [];
    foreach ($fuentes as $w => $r) {
        $srcset[] = asset($r) . ' ' . $w . 'w';
    }

    $prioritaria = !empty($op['prioritaria']);
    $atributos = [
        'src'      => asset($ruta),
        'srcset'   => implode(', ', $srcset),
        'sizes'    => $op['sizes'] ?? '100vw',
        'alt'      => $alt,
        'width'    => $ancho,
        'height'   => $alto,
        'class'    => $op['clase'] ?? '',
        'loading'  => $prioritaria ? 'eager' : 'lazy',
        'decoding' => 'async',
    ];
    if ($prioritaria) {
        $atributos['fetchpriority'] = 'high';
    }
    if (!empty($op['estilo'])) {
        $atributos['style'] = $op['estilo'];
    }

    $html = '<img';
    foreach ($atributos as $nombre => $valor) {
        if ($valor !== '') {
            $html .= ' ' . $nombre . '="' . e((string) $valor) . '"';
        }
    }
    return $html . '>';
}

/** Flecha de los botones "↗" (SVG en línea, hereda el color del texto). */
function flecha(): string
{
    return '<svg class="flecha" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">'
        . '<path d="M4.5 11.5 11.5 4.5M6 4.5h5.5V10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

/**
 * Encabezado de las páginas internas del sitio: foto a todo lo ancho,
 * título grande y una línea de texto.
 */
function encabezado_pagina(string $titulo, string $texto, string $foto, string $alt, string $posicion = '50% 50%'): string
{
    $img = foto($foto, $alt, ['clase' => 'encabezado-foto', 'prioritaria' => true, 'estilo' => 'object-position:' . $posicion]);
    return '<section class="encabezado" data-entrada>'
        . $img
        . '<div class="envoltura encabezado-contenido">'
        . '<h1 class="titulo-xl"><span class="linea"><span>' . e($titulo) . '</span></span></h1>'
        . '<p class="encabezado-texto aparece">' . e($texto) . '</p>'
        . '</div></section>';
}

/**
 * Tarjeta de un plan: el disco (precio y duración), nombre, descripción y beneficios.
 * El disco es decorativo; el nombre y el precio se leen en el texto de la tarjeta.
 */
function tarjeta_plan(array $plan): string
{
    $beneficios = '';
    foreach ($plan['beneficios'] as $b) {
        $beneficios .= '<li><i class="bi bi-check2" aria-hidden="true"></i>' . e($b['descripcion']) . '</li>';
    }
    // La descripción sólo se muestra si dice algo distinto al primer beneficio
    $descripcion = trim((string) ($plan['descripcion'] ?? ''));
    $primero = $plan['beneficios'][0]['descripcion'] ?? '';
    $mostrarDescripcion = $descripcion !== '' && mb_strtolower($descripcion) !== mb_strtolower($primero);

    return '<article class="plan" style="--color: var(--disco-' . e($plan['color']) . ')">'
        . disco_html($plan)
        . '<div class="plan-cuerpo">'
        . '<h3 class="plan-nombre">' . e($plan['nombre_plan']) . '</h3>'
        . '<p class="plan-precio"><strong>' . e(precio_corto($plan['precio'])) . '</strong> por '
        . e(duracion_texto((int) $plan['duracion_dias'])) . '</p>'
        . ($mostrarDescripcion ? '<p class="plan-descripcion">' . e($descripcion) . '</p>' : '')
        . ($beneficios ? '<ul class="plan-beneficios">' . $beneficios . '</ul>' : '')
        . '</div></article>';
}

/**
 * Medidor de la membresía: el disco del plan como un anillo que se vacía
 * conforme pasan los días. $restantes/$total en días.
 */
function disco_progreso(int $restantes, int $total, string $color, string $etiqueta, string $clase = 'llenar'): string
{
    $restantes = max(0, $restantes);
    $p = $total > 0 ? (int) round(min(100, $restantes / $total * 100)) : 0;
    return '<div class="anillo ' . e($clase) . '" style="--p:' . $p . '; --color: var(--disco-' . e($color) . ')" role="img" aria-label="' . e($etiqueta) . '">'
        . '<strong>' . $restantes . '</strong><span>' . ($restantes === 1 ? 'día' : 'días') . '</span></div>';
}

/** Color de disco de un plan por su id (según los planes activos); amarillo si ya no está activo. */
function color_de_plan(int $planId): string
{
    static $colores = null;
    if ($colores === null) {
        require_once ROOT_PATH . '/app/planes.php';
        $colores = array_column(planes_activos(), 'color', 'id');
    }
    return $colores[$planId] ?? 'amarillo';
}

/** Enlace al mapa del gimnasio (OpenStreetMap) con las coordenadas de la configuración. */
function enlace_mapa(): string
{
    $g = config('gimnasio');
    $lat = (float) $g['latitud'];
    $lon = (float) $g['longitud'];
    return 'https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '#map=17/' . $lat . '/' . $lon;
}

/** Precio del plan más barato ("$300") o '' si no hay planes. */
function precio_desde(array $planes): string
{
    if (!$planes) {
        return '';
    }
    return precio_corto(min(array_map(fn($p) => (float) $p['precio'], $planes)));
}
