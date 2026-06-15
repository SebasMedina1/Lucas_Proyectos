<?php
// Incluir la conexión a la base de datos y la clase para generar PDF
require '../../config/database.php';
require_once '../../reporte/reporte_compras.php'; // Clase BasePDF para generar el PDF

// Verificar si se recibió el estado desde el combo box
if (isset($_GET['estado'])) {
    $estado = $_GET['estado'];

    try {
        // Crear la conexión usando PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Consultar las facturas por estado
        $query = $pdo->prepare("
            SELECT fc.fact_id, fc.fact_nro, fc.fact_total, fc.fact_fecha, fc.fact_estado, pv.razon_social, u.username
            FROM facturas_compra fc
            JOIN proveedor pv ON fc.cod_proveedor = pv.cod_proveedor
            JOIN usuarios u ON fc.id_usuario = u.id_usuario
            WHERE fc.fact_estado = :estado
            ORDER BY fc.fact_fecha DESC
        ");
        $query->bindParam(':estado', $estado, PDO::PARAM_STR);
        $query->execute();

        // Obtener los resultados
        $facturas = $query->fetchAll(PDO::FETCH_ASSOC);

        if (count($facturas) > 0) {
            // Crear un nuevo documento PDF
            $pdf = new BasePDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', '', 12);

            // Título del informe
            $pdf->Cell(0, 10, convertir("Informe de Facturas - Estado: " . $estado), 0, 1, 'C');
            $pdf->Ln(5);

            // Encabezado de la tabla
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
            $pdf->Cell(40, 10, 'Nro Factura', 1, 0, 'C', true);
            $pdf->Cell(60, 10, 'Proveedor', 1, 0, 'C', true);
            $pdf->Cell(30, 10, 'Monto Total', 1, 0, 'C', true);
            $pdf->Cell(40, 10, 'Fecha', 1, 1, 'C', true);

            // Recorrer los resultados y agregar filas a la tabla
            foreach ($facturas as $factura) {
                $pdf->Cell(20, 10, convertir($factura['fact_id']), 1);
                $pdf->Cell(40, 10, convertir($factura['fact_nro']), 1);
                $pdf->Cell(60, 10, convertir($factura['razon_social']), 1);
                $pdf->Cell(30, 10, number_format($factura['fact_total'], 2) . ' Gs', 1, 0, 'R');
                $pdf->Cell(40, 10, convertir($factura['fact_fecha']), 1, 1);
            }

            // Salida del PDF
            $pdf->Output('I', 'Informe_Facturas_' . $estado . '.pdf');
        } else {
            echo "<h2>No se encontraron facturas con el estado: " . htmlspecialchars($estado) . "</h2>";
        }

    } catch (PDOException $e) {
        // Mostrar el error si ocurre alguno
        echo "Error al consultar los datos: " . $e->getMessage();
    }

} else {
    // Si no se recibe el estado, redirigir al inicio
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = 'view.php';
          </script>";
    exit();
}
?>
