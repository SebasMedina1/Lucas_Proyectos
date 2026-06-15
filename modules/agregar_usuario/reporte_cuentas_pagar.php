<?php
// Incluir la conexión a la base de datos y la clase PDF
require '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // Clase para generar PDF

try {
    // Crear la conexión usando PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consultar todas las cuentas a pagar
    $query = $pdo->query("
        SELECT cp.cta_id, cp.fact_id, cp.cta_total, cp.estado, fc.fact_nro, pv.razon_social
        FROM cuenta_pagar cp
        JOIN facturas_compra fc ON cp.fact_id = fc.fact_id
        JOIN proveedor pv ON fc.cod_proveedor = pv.cod_proveedor
        ORDER BY cp.cta_id ASC
    ");

    // Obtener los resultados
    $cuentas = $query->fetchAll(PDO::FETCH_ASSOC);

    // Crear instancia de la clase PDF
    $pdf = new BasePDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(190, 10, convertir('Informe de Cuentas a Pagar'), 0, 1, 'C');
    $pdf->Ln(10);

    // Verificar si hay resultados
    if (count($cuentas) > 0) {
        // Crear la tabla de cuentas a pagar
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(20, 10, 'Codigo', 1);
        $pdf->Cell(30, 10, 'Factura', 1);
        $pdf->Cell(60, 10, 'Proveedor', 1);
        $pdf->Cell(40, 10, 'Monto Total', 1);
        $pdf->Cell(40, 10, 'Estado', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 10);

        // Recorrer los resultados y agregarlos al PDF
        foreach ($cuentas as $cuenta) {
            $pdf->Cell(20, 10, $cuenta['cta_id'], 1);
            $pdf->Cell(30, 10, convertir($cuenta['fact_nro']), 1);
            $pdf->Cell(60, 10, convertir($cuenta['razon_social']), 1);
            $pdf->Cell(40, 10, number_format($cuenta['cta_total'], 2) . ' Gs', 1);
            $pdf->Cell(40, 10, convertir($cuenta['estado']), 1);
            $pdf->Ln();
        }
    } else {
        // Si no hay resultados
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(190, 10, convertir('No se encontraron cuentas a pagar.'), 0, 1, 'C');
    }

    // Salida del PDF
    $pdf->Output();

} catch (PDOException $e) {
    // Mostrar el error si ocurre alguno
    echo "Error al consultar los datos: " . $e->getMessage();
}
?>
