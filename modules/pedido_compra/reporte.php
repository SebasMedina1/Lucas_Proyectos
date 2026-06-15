<?php
require_once '../../config/database.php';
require_once '../../reporte/pedido_compras.php'; // Clase para generar PDF

// Verificar si se recibió el parámetro 'ped_id'
if (!isset($_GET['ped_id']) || empty($_GET['ped_id'])) {
    die("No se proporcionó un ID de pedido válido.");
}

$ped_id = intval($_GET['ped_id']); // Asegurarse de que sea un entero

$pdf = new BasePDF();
$pdf->AddPage();

try {
    // Configurar conexión PDO
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general del pedido
    $queryGeneral = "   SELECT 
            pc.id_pedido_compra,
                to_char(pc.pedido_fecha_emision, 'YYYY-MM-DD') AS pedido_fecha,
                to_char(pc.pedido_fecha_emision, 'HH24:MI:SS') AS pedido_hora,
            pc.pedido_estado, 
            us.username AS usuario, 
            suc.descripcion_sucursal AS sucursal
        FROM 
            pedidos_compra pc
        JOIN 
            sucursales suc ON pc.id_sucursal = suc.id_sucursal
        JOIN 
            usuarios us ON pc.id_usuario = us.id_usuario
        WHERE 
            pc.id_pedido_compra = :ped_id
        LIMIT 1;
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        // Mostrar información general del pedido
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 8, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['usuario'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Fecha:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['pedido_fecha'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Hora:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['pedido_hora'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Sucursal:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['sucursal'], 0, 1, 'L');
        $pdf->Cell(40, 8, 'Pedido Nro:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['id_pedido_compra'], 0, 1, 'L');
        $pdf->Ln(10); // Espacio antes de la tabla
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos generales para este pedido.', 0, 1, 'C');
    }

    // Consulta para obtener los detalles del pedido
    $queryDetails = "SELECT 
        mp.materia_prima_descripcion AS producto, 
        pd.cantidad_pedido AS cantidad,
        pc.pedido_estado AS estado
    FROM 
        pedido_detalle_compra pd
    JOIN 
        materia_prima mp ON pd.id_materia_prima = mp.id_materia_prima
    JOIN 
        pedidos_compra pc ON pd.id_pedido_compra = pc.id_pedido_compra
    WHERE 
        pd.id_pedido_compra = :ped_id
    ORDER BY 
        mp.materia_prima_descripcion;
        
";


    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->bindParam(':ped_id', $ped_id, PDO::PARAM_INT);
    $stmtDetails->execute();

    // Validar si existen detalles
    if ($stmtDetails->rowCount() > 0) {
        // Encabezado de la tabla de detalles
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(100, 8, 'Producto', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell(40, 8, 'Estado', 1, 1, 'C', true);

        // Añadir los detalles al PDF
        $pdf->SetFont('Arial', '', 10);
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $pdf->Cell(100, 8, $row['producto'], 1, 0, 'L');
            $pdf->Cell(40, 8, $row['cantidad'], 1, 0, 'C');
            $pdf->Cell(40, 8, $row['estado'], 1, 1, 'C');
        }
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron productos para este pedido.', 0, 1, 'C');
    }

    // Mostrar el PDF en el navegador
    $pdf->Output('I', "Detalle_Pedido_$ped_id.pdf");
} catch (PDOException $e) {
    echo "Error en la conexión o consulta: " . $e->getMessage();
    exit;
}
?>
