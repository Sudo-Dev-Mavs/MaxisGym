<?php
/**
 * Consultas de planes y beneficios que usan varias páginas
 * (Inicio, Planes y Precios, Gestión de planes, Mi membresía).
 */

/**
 * Planes activos ordenados del más económico al más caro,
 * cada uno con su lista de beneficios y el color de disco que le toca.
 */
function planes_activos(): array
{
    $planes = filas(
        'SELECT id, nombre_plan, precio, duracion_dias, descripcion
           FROM planes
          WHERE activo = 1
          ORDER BY precio ASC, nombre_plan ASC'
    );

    if (!$planes) {
        return [];
    }

    // Beneficios de todos los planes en una sola consulta
    $ids = array_column($planes, 'id');
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $beneficios = filas(
        "SELECT pb.plan_id, b.id, b.descripcion
           FROM plan_beneficio pb
           JOIN beneficios b ON b.id = pb.beneficio_id
          WHERE pb.plan_id IN ($marcas)
          ORDER BY b.descripcion",
        $ids
    );

    $porPlan = [];
    foreach ($beneficios as $b) {
        $porPlan[$b['plan_id']][] = $b;
    }

    $colores = colores_disco(count($planes));
    foreach ($planes as $i => &$plan) {
        $plan['beneficios'] = $porPlan[$plan['id']] ?? [];
        $plan['color'] = $colores[$i];
    }
    unset($plan);

    return $planes;
}

/** Beneficios que aparecen en al menos un plan activo (filas de la tabla comparativa). */
function beneficios_de_planes_activos(): array
{
    return filas(
        'SELECT DISTINCT b.id, b.descripcion
           FROM beneficios b
           JOIN plan_beneficio pb ON pb.beneficio_id = b.id
           JOIN planes p ON p.id = pb.plan_id AND p.activo = 1
          ORDER BY b.descripcion'
    );
}

/**
 * Asigna colores de disco olímpico según el lugar del plan por precio:
 * verde (10 kg), amarillo (15 kg), azul (20 kg), rojo (25 kg).
 * El plan más caro siempre es el disco rojo, el "más pesado".
 */
function colores_disco(int $cantidad): array
{
    $discos = ['verde', 'amarillo', 'azul', 'rojo'];
    if ($cantidad <= 0) {
        return [];
    }
    if ($cantidad === 1) {
        return ['rojo'];
    }
    if ($cantidad > count($discos)) {
        $lista = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $lista[] = $discos[$i % count($discos)];
        }
        return $lista;
    }
    // Reparte los colores de forma pareja entre el más ligero y el más pesado
    $lista = [];
    for ($i = 0; $i < $cantidad; $i++) {
        $lista[] = $discos[(int) round($i * (count($discos) - 1) / ($cantidad - 1))];
    }
    return $lista;
}

/** Texto de duración: 30 → "30 días", 365 → "1 año". */
function duracion_texto(int $dias): string
{
    if ($dias === 365) {
        return '1 año';
    }
    if ($dias % 30 === 0 && $dias >= 30) {
        $meses = intdiv($dias, 30);
        return $meses === 1 ? '1 mes' : "$meses meses";
    }
    return $dias === 1 ? '1 día' : "$dias días";
}

/** Precio sin centavos cuando es cerrado: 500.00 → "$500", 499.50 → "$499.50". */
function precio_corto($precio): string
{
    $p = (float) $precio;
    return '$' . (floor($p) == $p ? number_format($p, 0) : number_format($p, 2));
}

/**
 * HTML de un plan dibujado como disco olímpico.
 * Si se indica $enlace, el disco es un link (por ejemplo, a planes.php).
 */
function disco_html(array $plan, string $enlace = ''): string
{
    $etiqueta = $enlace !== '' ? 'a' : 'div';
    $texto = $plan['nombre_plan'] . ': ' . precio_corto($plan['precio']) . ' por ' . duracion_texto((int) $plan['duracion_dias']);
    // Sin enlace, el disco es decorativo: el nombre y el precio ya se leen en la tarjeta del plan.
    $atributos = $enlace !== ''
        ? ' href="' . e($enlace) . '" aria-label="' . e($texto) . '"'
        : ' aria-hidden="true"';

    // El nombre del plan no va dentro del disco: un nombre largo no cabe en el círculo.
    return '<' . $etiqueta . ' class="disco disco-' . e($plan['color']) . '"' . $atributos . '>'
        . '<span class="disco-precio">' . e(precio_corto($plan['precio'])) . '</span>'
        . '<span class="disco-duracion">' . e(duracion_texto((int) $plan['duracion_dias'])) . '</span>'
        . '</' . $etiqueta . '>';
}
