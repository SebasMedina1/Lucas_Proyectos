<?php
require('../../reporte/fpdf185/fpdf.php');

function convertir($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class BasePDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        $this->SetFont('Arial', 'B', 19);
        $this->Cell(45);
        $this->SetTextColor(39, 42, 87);
        $this->Cell(120, 15, convertir('Nick Store'), 1, 1, 'C', 0);
        $this->Ln(3);

        $this->Cell(45);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(96, 10, convertir('Ubicación : Ruta Departamental D027, San Lorenzo - Paraguay'), 0, 0, '', 0);
        $this->Ln(5);

        $this->Cell(45);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(59, 10, convertir('Teléfono : +595 972 957421'), 0, 0, '', 0);
        $this->Ln(5);

        $this->Cell(45);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(85, 10, convertir('Correo : nicolasdominguez180804@gmail.com'), 0, 0, '', 0);
        $this->Ln(10);

        $this->SetTextColor(39, 42, 87);
        $this->Cell(50);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(100, 10, convertir('REPORTE DE PROVEEDORES'), 0, 1, 'C', 0);
        $this->Ln(7);

        // Tabla de cabecera
        $this->SetFillColor(39, 42, 87);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(20, 10, convertir('Cod.'), 1, 0, 'C', true);
        $this->Cell(50, 10, convertir('Razon Social'), 1, 0, 'C', true);
        $this->Cell(25, 10, convertir('Ruc'), 1, 0, 'C', true);
        $this->Cell(70, 10, convertir('Dirección'), 1, 0, 'C', true);
        $this->Cell(30, 10, convertir('Teléfono'), 1, 1, 'C', true);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, convertir('Página ') . $this->PageNo(), 0, 0, 'C');

        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $hoy = date('d/m/Y');
        $this->Cell(0, 10, $hoy, 0, 0, 'R');
    }
}
