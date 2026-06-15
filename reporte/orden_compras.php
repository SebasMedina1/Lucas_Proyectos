<?php
require('../../reporte/fpdf185/fpdf.php');

function convertir($texto) {
    return mb_convert_encoding($texto, 'ISO-8859-1', 'UTF-8');
}

class BasePDF extends FPDF
{
    private $empresa;
    private $ubicacion;
    private $telefono;
    private $correo;
    private $titulo;

    public function __construct($orientation='P', $unit='mm', $size='A4', $opts = [])
    {
        parent::__construct($orientation, $unit, $size);
        $this->empresa  = $opts['empresa']  ?? 'Emmanuels';
        $this->ubicacion= $opts['ubicacion']?? 'Ruta Departamental D027, San Lorenzo - Paraguay';
        $this->telefono = $opts['telefono'] ?? '+595 972 957381';
        $this->correo   = $opts['correo']   ?? 'emmanuels@gmail.com';
        $this->titulo   = $opts['titulo']   ?? 'Orden de Compra';
    }

    // Cabecera
    function Header()
    {
        // Colores corporativos
        $azulOscuro = [39, 42, 87];
        $azulSuave  = [200, 220, 255];


        // Nombre de la empresa en recuadro
        $this->SetXY(40, 12);
        $this->SetFont('Arial','B',18);
        $this->SetTextColor($azulOscuro[0], $azulOscuro[1], $azulOscuro[2]);
        $this->SetDrawColor($azulOscuro[0], $azulOscuro[1], $azulOscuro[2]);
        $this->Cell(130, 14, convertir($this->empresa), 1, 1, 'C');

        // Datos de contacto (tres líneas)
        $this->SetX(40);
        $this->SetFont('Arial','B',10);
        $this->SetTextColor(0,0,0);
        $this->Cell(130, 6, convertir('Ubicación : ' . $this->ubicacion), 0, 1, 'L');
        $this->SetX(40);
        $this->Cell(130, 6, convertir('Teléfono : ' . $this->telefono), 0, 1, 'L');
        $this->SetX(40);
        $this->Cell(130, 6, convertir('Correo : ' . $this->correo), 0, 1, 'L');

        // Separador + título del informe en banda de color
        $this->Ln(2);
        $this->SetDrawColor(230,230,230);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(4);

        $this->SetFillColor($azulOscuro[0], $azulOscuro[1], $azulOscuro[2]);
        $this->SetTextColor(255,255,255);
        $this->SetFont('Arial','B',13);
        $this->Cell(0, 10, convertir($this->titulo), 0, 1, 'C', true);

        // Banda suave bajo el título para aire visual
        $this->SetFillColor($azulSuave[0], $azulSuave[1], $azulSuave[2]);
        $this->Cell(0, 3, '', 0, 1, 'L', true);

        // Margen inferior antes del contenido
        $this->Ln(6);

        // Reset color texto a negro para el cuerpo
        $this->SetTextColor(0,0,0);
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 10, convertir('Página ') . $this->PageNo(), 0, 0, 'C');

        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 10, date('d/m/Y'), 0, 0, 'R');
    }
}
