<?php
    require_once '../../config/database.php';
    require_once '../../reporte/reporte_compras.php'; // Clase para generar PDF

    // Verificar si se recibió el parámetro 'fact_id'
    if (!isset($_GET['fact_id']) || empty($_GET['fact_id'])) {
        die("No se proporcionó un ID de factura válido.");
    }

    $fact_id = intval($_GET['fact_id']); // Asegurarse de que sea un entero

    $pdf = new BasePDF();
    $pdf->AddPage();

    // Configurar el título del reporte
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Detalle de Factura de Compra', 0, 1, 'C');
    $pdf->Ln(5);

    // Mostrar datos generales de la factura
    try {
        // Configurar conexión PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Consulta para obtener los datos generales de la factura
        $queryFactura = "
            SELECT 
                f.fact_nro,
                f.fact_fecha,
                f.fact_hora,
                f.fact_total,
                f.fact_estado,
                p.razon_social,
                f.fact_plazo,
                f.fact_inicio,
                f.fact_vencimiento,
                u.username,
                f.fact_timbrado
            FROM 
                facturas_compra f
            JOIN 
                proveedor p ON f.cod_proveedor = p.cod_proveedor
            JOIN 
                usuarios u ON f.id_usuario = u.id_usuario
            WHERE 
                f.fact_id = :fact_id
        ";

        $stmtFactura = $pdo->prepare($queryFactura);
        $stmtFactura->bindParam(':fact_id', $fact_id, PDO::PARAM_INT);
        $stmtFactura->execute();

        $factura = $stmtFactura->fetch(PDO::FETCH_ASSOC);
        if (!$factura) {
            die("No se encontró información para la factura proporcionada.");
        }

        // Mostrar datos de la factura
        $pdf->SetFont('Arial', '', 12);

        // Línea para Nro Factura y Timbrado en la misma línea con espacio
        $pdf->Cell(50, 8, 'Nro Factura:', 0, 0, 'L');
        $pdf->Cell(50, 8, $factura['fact_nro'], 0, 0, 'L'); // Factura a la izquierda
        $pdf->Cell(50, 8, 'Timbrado:', 0, 0, 'L'); // Timbrado con espacio a la derecha
        $pdf->Cell(50, 8, $factura['fact_timbrado'], 0, 1, 'L');

        // Línea para Fecha y Hora en la misma línea con espacio
        $pdf->Cell(50, 8, 'Fecha:', 0, 0, 'L');
        $pdf->Cell(50, 8, $factura['fact_fecha'], 0, 0, 'L'); // Fecha a la izquierda
        $pdf->Cell(50, 8, 'Hora:', 0, 0, 'L'); // Hora con espacio a la derecha
        $pdf->Cell(50, 8, $factura['fact_hora'], 0, 1, 'L');

        // Mostrar Proveedor, Usuario y Estado
        $pdf->Cell(50, 8, 'Proveedor:', 0, 0, 'L');
        $pdf->Cell(100, 8, $factura['razon_social'], 0, 1, 'L');
        $pdf->Cell(50, 8, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(100, 8, $factura['username'], 0, 1, 'L');
        $pdf->Cell(50, 8, 'Estado:', 0, 0, 'L');
        $pdf->Cell(100, 8, $factura['fact_estado'], 0, 1, 'L');

        // Mostrar Plazo, Fecha de Inicio y Vencimiento
        $pdf->Cell(50, 8, 'Plazo:', 0, 0, 'L');
        $pdf->Cell(100, 8, $factura['fact_plazo'] . ' cuotas', 0, 1, 'L');
        $pdf->Cell(50, 8, 'Fecha de Inicio:', 0, 0, 'L');
        $pdf->Cell(100, 8, $factura['fact_inicio'], 0, 1, 'L');
        $pdf->Cell(50, 8, 'Fecha de Vencimiento:', 0, 0, 'L');
        $pdf->Cell(100, 8, $factura['fact_vencimiento'], 0, 1, 'L');

        $pdf->Ln(10);

        // Consulta para obtener los detalles de la factura
        $queryDetalles = "
        SELECT 
            fd.cod_producto,
            fd.fact_cantidad::double precision AS fact_cantidad,
            fd.fact_precio::double precision AS fact_precio,
            fd.fact_iva::double precision AS fact_iva,
            (fd.fact_cantidad::double precision * fd.fact_precio::double precision) AS subtotal,
            mp.p_descrip
        FROM 
            facturas_detalle_compra fd
        JOIN 
            producto mp ON fd.cod_producto = mp.cod_producto
        WHERE 
            fd.fact_id = :fact_id
        ";

        // Preparar y ejecutar la consulta
        $stmtDetalles = $pdo->prepare($queryDetalles);
        $stmtDetalles->bindParam(':fact_id', $fact_id, PDO::PARAM_INT);
        $stmtDetalles->execute();


        // Configurar los encabezados de la tabla de detalles
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255); // Fondo azul claro para los encabezados
        $pdf->Cell(55, 8, 'Producto', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell(30, 8, 'Precio', 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'IVA', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Subtotal', 1, 1, 'C', true);

        // Añadir datos al PDF
        $pdf->SetFont('Arial', '', 10);
        while ($detalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(55, 8, $detalle['p_descrip'], 1, 0, 'C');
            $pdf->Cell(30, 8, number_format($detalle['fact_cantidad'], 0, '', '.'), 1, 0, 'C');
            $pdf->Cell(30, 8, number_format($detalle['fact_precio'], 0, '', '.'), 1, 0, 'C');
            $pdf->Cell(20, 8, $detalle['fact_iva'] . '%', 1, 0, 'C');
            $pdf->Cell(40, 8, number_format($detalle['subtotal'], 0, '', '.'), 1, 1, 'C');
        }

        // Agregar Total debajo de la tabla
        // Agregar Total debajo de la tabla y alinearlo a la derecha
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(170, 8, 'Total: ' . number_format($factura['fact_total'], 0, '', '.'), 0, 1, 'R'); // Total alineado a la derecha

        // Mostrar el PDF en el navegador
        $pdf->Output('I', "Detalle_Factura_$fact_id.pdf");

    } catch (PDOException $e) {
        echo "Error en la conexión o consulta: " . $e->getMessage();
        exit;
    }
?>
