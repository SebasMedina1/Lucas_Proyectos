<?php
require('../../reporte/fpdf185/fpdf.php');

// Función para convertir texto a una codificación compatible con FPDF
if (!function_exists('convertir')) {
    function convertir($texto) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
    }
}


class BasePDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        $this->SetFont('Arial', 'B', 19);
        $this->Cell(45);
        $this->SetTextColor(39, 42, 87);
        $this->Cell(120, 15, convertir('Debian Service'), 1, 1, 'C', 0);
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
        $this->Cell(85, 10, convertir('Correo : soporteti@debian'), 0, 0, '', 0);
        $this->Ln(10);
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
?>
