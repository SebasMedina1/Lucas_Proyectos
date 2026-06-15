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

        // Consultar las notas de compra con estado específico
        $query = $pdo->prepare("
            SELECT 
                nc.nota_id, 
                u.username AS usuario, 
                nc.nota_fecha AS fecha, 
                nc.nota_nro AS numero, 
                nc.nota_total AS total, 
                m.motivo_descripcion AS motivo,
                p.p_descrip AS producto, 
                ncd.nota_cantidad AS cantidad, 
                ncd.nota_precio AS precio, 
                ncd.nota_iva AS iva
            FROM notas_compra nc
            JOIN notas_compra_detalle ncd ON nc.nota_id = ncd.nota_id
            JOIN producto p ON ncd.cod_producto = p.cod_producto
            JOIN usuarios u ON nc.id_usuario = u.id_usuario
            JOIN motivo m ON nc.motivo_id = m.motivo_id
            WHERE nc.nota_estado = :estado
            ORDER BY nc.nota_id, ncd.cod_producto
        ");
        $query->bindParam(':estado', $estado, PDO::PARAM_STR);
        $query->execute();

        // Obtener los resultados
        $notas = $query->fetchAll(PDO::FETCH_ASSOC);

        if (count($notas) > 0) {
            // Crear un nuevo documento PDF
            $pdf = new BasePDF();
            $pdf->AddPage();
            $pdf->SetFont('Times', '', 10);

            // Título del informe
            $pdf->SetFont('Times', 'B', 12);
            $pdf->Cell(0, 10, convertir("Informe de Notas de Crédito - Estado: " . $estado), 0, 1, 'C');
            $pdf->Ln(5);

            // Encabezado de la tabla
            $pdf->SetFont('Times', 'B', 8);
            $pdf->SetFillColor(200, 220, 255);
            $pdf->Cell(20, 6, convertir('Nro Nota'), 1, 0, 'C', true);
            $pdf->Cell(25, 6, convertir('Usuario'), 1, 0, 'C', true);
            $pdf->Cell(25, 6, convertir('Fecha'), 1, 0, 'C', true);
            $pdf->Cell(35, 6, convertir('Motivo'), 1, 0, 'C', true);
            $pdf->Cell(20, 6, convertir('Total'), 1, 0, 'C', true);
            $pdf->Cell(30, 6, convertir('Producto'), 1, 0, 'C', true);
            $pdf->Cell(15, 6, convertir('Cantidad'), 1, 0, 'C', true);
            $pdf->Cell(15, 6, convertir('Precio'), 1, 0, 'C', true);
            $pdf->Cell(10, 6, convertir('IVA'), 1, 1, 'C', true);

            // Recorrer los resultados y agregar filas a la tabla
            $pdf->SetFont('Times', '', 8);
            foreach ($notas as $nota) {
                $pdf->Cell(20, 6, convertir($nota['numero']), 1, 0, 'C');
                $pdf->Cell(25, 6, convertir($nota['usuario']), 1, 0, 'C');
                $pdf->Cell(25, 6, convertir($nota['fecha']), 1, 0, 'C');
                $pdf->Cell(35, 6, convertir($nota['motivo']), 1, 0, 'C');
                $pdf->Cell(20, 6, number_format($nota['total'], 2), 1, 0, 'C');
                $pdf->Cell(30, 6, convertir($nota['producto']), 1, 0, 'C');
                $pdf->Cell(15, 6, convertir($nota['cantidad']), 1, 0, 'C');
                $pdf->Cell(15, 6, number_format($nota['precio'], 2), 1, 0, 'C');
                $pdf->Cell(10, 6, convertir($nota['iva']), 1, 1, 'C');
            }

            // Salida del PDF
            $pdf->Output('I', 'Informe_Notas_Compra_' . $estado . '.pdf');
        } else {
            echo "<h2>No se encontraron notas de compra con el estado: " . htmlspecialchars($estado) . "</h2>";
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
