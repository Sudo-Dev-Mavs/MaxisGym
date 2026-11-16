<?php
/**
 * Reporte en PDF — permiso reportes.ver.
 *   admin/reporte_pdf.php?desde=AAAA-MM-DD&hasta=AAAA-MM-DD
 *
 * Se genera con FPDF (app/lib/fpdf), una librería de un solo archivo PHP
 * sin dependencias. FPDF trabaja en Windows-1252, por eso todo el texto se
 * convierte con pdf_texto() para que salgan bien los acentos y la ñ.
 */
require __DIR__ . '/../app/bootstrap.php';
require_once ROOT_PATH . '/app/reportes.php';
require_once ROOT_PATH . '/app/lib/fpdf/fpdf.php';

$yo = requiere_permiso('reportes.ver');
[$desde, $hasta] = rango_reporte();
$r = datos_reporte($desde, $hasta);

/** UTF-8 → Windows-1252 (lo que entiende FPDF con sus fuentes estándar). */
function pdf_texto(string $texto): string
{
    if (function_exists('iconv')) {
        $convertido = iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
        if ($convertido !== false) {
            return $convertido;
        }
    }
    return mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
}

class ReportePDF extends FPDF
{
    public string $subtitulo = '';

    // Encabezado de cada página: franja oscura con línea amarilla
    function Header(): void
    {
        $this->SetFillColor(38, 44, 51);
        $this->Rect(0, 0, $this->GetPageWidth(), 24, 'F');
        $this->SetFillColor(242, 183, 5);
        $this->Rect(0, 24, $this->GetPageWidth(), 1.6, 'F');
        $this->SetXY(15, 7);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 16);
        $this->Cell(90, 7, 'MaxisGym');
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(201, 207, 213);
        $this->Cell(0, 7, pdf_texto($this->subtitulo), 0, 0, 'R');
        $this->SetY(32);
        $this->SetTextColor(29, 35, 41);
    }

    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(139, 149, 160);
        $this->Cell(0, 6, pdf_texto('Generado el ' . date('d/m/Y H:i') . ' — Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'C');
    }

    function TituloSeccion(string $texto): void
    {
        $this->Ln(3);
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(29, 35, 41);
        $this->Cell(0, 8, pdf_texto($texto), 0, 1);
    }

    /** Tabla simple: $anchos en mm, $alineacion 'L'|'C'|'R' por columna. */
    function Tabla(array $encabezados, array $filas, array $anchos, array $alineacion, ?array $total = null): void
    {
        // Si no cabe el encabezado y al menos una fila, la tabla empieza en otra página
        if ($this->GetY() > $this->GetPageHeight() - 40) {
            $this->AddPage();
        }
        $this->EncabezadoTabla($encabezados, $anchos, $alineacion);
        $par = false;
        foreach ($filas as $fila) {
            // Salto de página manual para repetir el encabezado de la tabla
            if ($this->GetY() > $this->GetPageHeight() - 27) {
                $this->AddPage();
                $this->EncabezadoTabla($encabezados, $anchos, $alineacion);
            }
            $this->SetFillColor($par ? 244 : 255, $par ? 245 : 255, $par ? 243 : 255);
            foreach ($fila as $i => $celda) {
                $this->Cell($anchos[$i], 6.5, pdf_texto((string) $celda), 0, 0, $alineacion[$i], true);
            }
            $this->Ln();
            $par = !$par;
        }
        if (!$filas) {
            $this->SetTextColor(139, 149, 160);
            $this->Cell(array_sum($anchos), 7, pdf_texto('Sin pagos en el periodo.'), 0, 1);
            $this->SetTextColor(29, 35, 41);
        }
        if ($total !== null) {
            $this->SetFont('Helvetica', 'B', 9);
            $this->SetDrawColor(201, 207, 213);
            foreach ($total as $i => $celda) {
                $this->Cell($anchos[$i], 7, pdf_texto((string) $celda), 'T', 0, $alineacion[$i]);
            }
            $this->Ln();
        }
    }

    private function EncabezadoTabla(array $encabezados, array $anchos, array $alineacion): void
    {
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetFillColor(38, 44, 51);
        $this->SetTextColor(255, 255, 255);
        foreach ($encabezados as $i => $h) {
            $this->Cell($anchos[$i], 7, pdf_texto($h), 0, 0, $alineacion[$i], true);
        }
        $this->Ln();
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(29, 35, 41);
    }
}

$pdf = new ReportePDF('P', 'mm', 'Letter');
$pdf->subtitulo = 'Reporte del ' . fecha($desde) . ' al ' . fecha($hasta);
$pdf->SetTitle(pdf_texto('Reporte MaxisGym ' . $desde . ' a ' . $hasta));
$pdf->SetAuthor(pdf_texto($yo['nombre'] . ' ' . $yo['apellido']));
$pdf->SetMargins(15, 32, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AliasNbPages();
$pdf->AddPage();

// Título
$pdf->SetFont('Helvetica', 'B', 18);
$pdf->Cell(0, 9, pdf_texto('Reporte del gimnasio'), 0, 1);
$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(74, 83, 92);
$pdf->Cell(0, 6, pdf_texto('Periodo: ' . texto_rango($desde, $hasta) . '. Elaborado por ' . $yo['nombre'] . ' ' . $yo['apellido'] . '.'), 0, 1);
$pdf->Ln(4);

// Datos principales en 4 recuadros
$datos = [
    ['Socios activos', (string) $r['socios_activos'], 'con membresía vigente hoy'],
    ['Empleados', (string) $r['empleados'], 'personal activo hoy'],
    ['Ingresos del periodo', dinero($r['ingresos']), $r['num_pagos'] . ($r['num_pagos'] === 1 ? ' pago' : ' pagos')],
    ['Socios nuevos', (string) $r['socios_nuevos'], 'cuentas creadas'],
];
$ancho = (216 - 30 - 9) / 4;
$y = $pdf->GetY();
foreach ($datos as $i => [$etiqueta, $valor, $nota]) {
    $x = 15 + $i * ($ancho + 3);
    $pdf->SetDrawColor(227, 230, 233);
    $pdf->Rect($x, $y, $ancho, 24);
    $pdf->SetXY($x + 3, $y + 3);
    $pdf->SetFont('Helvetica', '', 8);
    $pdf->SetTextColor(74, 83, 92);
    $pdf->Cell($ancho - 6, 4, pdf_texto($etiqueta));
    $pdf->SetXY($x + 3, $y + 8);
    $pdf->SetFont('Helvetica', 'B', 15);
    $pdf->SetTextColor(29, 35, 41);
    $pdf->Cell($ancho - 6, 8, pdf_texto($valor));
    $pdf->SetXY($x + 3, $y + 17);
    $pdf->SetFont('Helvetica', '', 7.5);
    $pdf->SetTextColor(139, 149, 160);
    $pdf->Cell($ancho - 6, 4, pdf_texto($nota));
}
$pdf->SetY($y + 30);

$pdf->TituloSeccion('Ingresos por plan');
$pdf->Tabla(['Plan', 'Pagos', 'Total'],
    array_map(fn($f) => [$f['nombre_plan'], $f['pagos'], dinero($f['total'])], $r['por_plan']),
    [110, 30, 46], ['L', 'C', 'R'],
    $r['por_plan'] ? ['Total', $r['num_pagos'], dinero($r['ingresos'])] : null);

$pdf->TituloSeccion('Ingresos por método de pago');
$pdf->Tabla(['Método', 'Pagos', 'Total'],
    array_map(fn($f) => [$f['nombre'], $f['pagos'], dinero($f['total'])], $r['por_metodo']),
    [110, 30, 46], ['L', 'C', 'R']);

$pdf->TituloSeccion('Detalle de pagos');
$pdf->Tabla(['Fecha', 'Socio', 'Plan', 'Método', 'Monto'],
    array_map(fn($p) => [fecha($p['fecha_pago']), $p['socio'], $p['nombre_plan'], $p['metodo'], dinero($p['monto'])], $r['pagos']),
    [25, 58, 40, 33, 30], ['L', 'L', 'L', 'L', 'R'],
    $r['pagos'] ? ['', '', '', 'Total', dinero($r['ingresos'])] : null);

$pdf->Output('D', 'reporte-maxisgym-' . $desde . '-a-' . $hasta . '.pdf');
