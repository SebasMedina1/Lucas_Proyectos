<?php
    require_once '../../config/database.php';
    require_once '../../reporte/reporte_remision.php'; // Clase para generar PDF

    // Verificar si se recibió el parámetro 'nr_id'
    if (!isset($_GET['nr_id']) || empty($_GET['nr_id'])) {
        die("No se proporcionó un ID de nota válido.");
    }

    $nr_id = intval($_GET['nr_id']); // Asegurarse de que sea un entero

    $pdf = new BasePDF('P','mm','A4',['titulo'=>'Detalle de Nota de Remisión']);
    $pdf->AddPage();

    // Configurar el título del reporte
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, convertir('Detalle de la Nota de Remisión'), 0, 1, 'C');
    $pdf->Ln(5);

    // Mostrar datos generales de la nota
    try {
        // Configurar conexión PDO
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Consulta para obtener los datos generales de la nota
        $queryNota = "
            SELECT 
                nr.id_nota_remision,
                nr.nota_fecha,
                nr.nota_remision_total,
                nr.nota_remision_nro,
                nr.nota_estado,
                u.username,
                pr.razon_social,
                COALESCE(c.conductor_nombre || ' ' || c.conductor_apellido, 'N/D') AS conductor,
                COALESCE(v.vehiculo_marca || ' ' || v.vehiculo_ano || ' ' || v.vehiculo_color, 'N/D') AS vehiculo,
                oc.id_orden_compra
            FROM 
                nota_remision_compra nr
            JOIN 
                usuarios u ON u.id_usuario = nr.id_usuario
            JOIN 
                proveedor pr ON pr.id_proveedor = nr.id_proveedor
            LEFT JOIN 
                orden_de_compra oc ON oc.id_orden_compra = nr.id_orden_compra
            LEFT JOIN 
                conductores c ON c.conductor_id = nr.conductor_id
            LEFT JOIN 
                vehiculos v ON v.vehiculo_id = nr.vehiculo_id
            WHERE 
                nr.id_nota_remision = :nr_id
        ";

        $stmtNota = $pdo->prepare($queryNota);
        $stmtNota->bindParam(':nr_id', $nr_id, PDO::PARAM_INT);
        $stmtNota->execute();

        $nota = $stmtNota->fetch(PDO::FETCH_ASSOC);
        if (!$nota) {
            die("No se encontró información para la nota proporcionada.");
        }

        // Mostrar datos de la nota
        $pdf->SetFont('Arial', '', 12);

        $pdf->Cell(50, 8, convertir('N° Remisión:'), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir($nota['nota_remision_nro'] ?? 'N/D'), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir('Estado:'), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir($nota['nota_estado']), 0, 1, 'L');

        $pdf->Cell(50, 8, convertir('Fecha:'), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir($nota['nota_fecha']), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir('Orden Compra:'), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir($nota['id_orden_compra'] ?? 'N/D'), 0, 1, 'L');

        $pdf->Cell(50, 8, convertir('Total:'), 0, 0, 'L');
        $pdf->Cell(50, 8, convertir(number_format($nota['nota_remision_total'], 0, ',', '.') . ' Gs'), 0, 1, 'L');

        $pdf->Cell(50, 8, convertir('Proveedor:'), 0, 0, 'L');
        $pdf->Cell(100, 8, convertir($nota['razon_social']), 0, 1, 'L');
        $pdf->Cell(50, 8, convertir('Usuario:'), 0, 0, 'L');
        $pdf->Cell(100, 8, convertir($nota['username']), 0, 1, 'L');
        $pdf->Cell(50, 8, convertir('Conductor:'), 0, 0, 'L');
        $pdf->Cell(100, 8, convertir($nota['conductor']), 0, 1, 'L');
        $pdf->Cell(50, 8, convertir('Vehículo:'), 0, 0, 'L');
        $pdf->Cell(100, 8, convertir($nota['vehiculo']), 0, 1, 'L');

        $pdf->Ln(10);

        // Consulta para obtener los detalles de la nota
        $queryDetalles = "
        SELECT 
            mp.materia_prima_descripcion AS producto,
            d.nota_cantidad        AS cantidad
        FROM 
            nota_remision_detalle_compra d
        JOIN 
            materia_prima mp ON mp.id_materia_prima = d.id_materia_prima
        WHERE 
            d.id_nota_remision = :nr_id
        ORDER BY mp.materia_prima_descripcion
        ";

        // Preparar y ejecutar la consulta
        $stmtDetalles = $pdo->prepare($queryDetalles);
        $stmtDetalles->bindParam(':nr_id', $nr_id, PDO::PARAM_INT);
        $stmtDetalles->execute();


        // Configurar los encabezados de la tabla de detalles
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255); // Fondo azul claro para los encabezados
        $pdf->Cell(55, 8, convertir('Producto'), 1, 0, 'C', true);
        $pdf->Cell(30, 8, convertir('Cantidad'), 1, 1, 'C', true);

        // Añadir datos al PDF
        $pdf->SetFont('Arial', '', 10);
        while ($detalle = $stmtDetalles->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(55, 8, convertir($detalle['producto']), 1, 0, 'L');
            $pdf->Cell(30, 8, number_format($detalle['cantidad'], 0, '', '.'), 1, 1, 'C');
        }

        // Mostrar el PDF en el navegador
        $pdf->Output('I', "Detalle_Nota_Remision_$nr_id.pdf");

    } catch (PDOException $e) {
        echo "Error en la conexión o consulta: " . $e->getMessage();
        exit;
    }
?>
