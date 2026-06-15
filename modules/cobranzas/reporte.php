<?php
require_once '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase BasePDF

// Verificar si se recibió el parámetro 'id'
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("No se proporcionó un ID de cobro válido.");
}

$cobro_id = intval($_GET['id']);

$pdf = new BasePDF('P', 'mm', 'A4', ['titulo' => 'Comprobante de Cobro']);
$pdf->AddPage();

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Consulta para obtener la información general del cobro
    $queryGeneral = "
        SELECT 
            c.id_cobro,
            c.numero_recibo,
            to_char(c.fecha_cobro, 'DD/MM/YYYY') AS fecha_cobro,
            to_char(c.hora_cobro, 'HH24:MI:SS') AS hora_cobro,
            c.total_cobrado,
            c.vuelto,
            c.efectivo_recibido,
            c.estado,
            c.observaciones,
            cl.cliente_nombre || ' ' || cl.cliente_apellido AS cliente_nombre,
            cl.cliente_ruc,
            us.username AS usuario, 
            suc.descripcion_sucursal AS sucursal
        FROM 
            cobros c
        JOIN 
            clientes cl ON c.id_cliente = cl.id_cliente
        JOIN 
            sucursales suc ON c.id_sucursal = suc.id_sucursal
        JOIN 
            usuarios us ON c.id_usuario = us.id_usuario
        WHERE 
            c.id_cobro = :cobro_id
        LIMIT 1
    ";

    $stmtGeneral = $pdo->prepare($queryGeneral);
    $stmtGeneral->bindParam(':cobro_id', $cobro_id, PDO::PARAM_INT);
    $stmtGeneral->execute();
    $general = $stmtGeneral->fetch(PDO::FETCH_ASSOC);

    if ($general) {
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, convertir('COMPROBANTE DE COBRO'), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 8, 'Recibo N°:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['numero_recibo'], 0, 1, 'L');
        $pdf->Cell(50, 8, 'Fecha:', 0, 0, 'L');
        $pdf->Cell(80, 8, $general['fecha_cobro'] . ' ' . $general['hora_cobro'], 0, 1, 'L');
        $pdf->Cell(50, 8, 'Cliente:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['cliente_nombre']), 0, 1, 'L');
        if (!empty($general['cliente_ruc'])) {
            $pdf->Cell(50, 8, 'RUC:', 0, 0, 'L');
            $pdf->Cell(80, 8, $general['cliente_ruc'], 0, 1, 'L');
        }
        $pdf->Cell(50, 8, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['usuario']), 0, 1, 'L');
        $pdf->Cell(50, 8, 'Sucursal:', 0, 0, 'L');
        $pdf->Cell(80, 8, convertir($general['sucursal']), 0, 1, 'L');
        $pdf->Ln(10);
    } else {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'No se encontraron datos para este cobro.', 0, 1, 'C');
        $pdf->Output();
        exit;
    }

    // Consulta para obtener los detalles del cobro con información de cheques
    $queryDetails = "
        SELECT 
            fv.numero_factura,
            cd.tipo_pago,
            cd.importe_aplicado,
            ch.cheque_numero,
            ch.cheque_fecha_emision,
            ch.cheque_fecha_vencimiento,
            ch.cheque_tipo,
            b.banco_descri
        FROM 
            cobros_detalle cd
        JOIN 
            factura_ventas fv ON cd.id_factura_venta = fv.id_factura_venta
        LEFT JOIN 
            cobro_cheques cc ON cc.id_cobro = cd.id_cobro
        LEFT JOIN 
            cheque ch ON ch.id_cheque = cc.id_cheque AND cd.tipo_pago = 'CHEQUE'
        LEFT JOIN 
            banco b ON b.id_banco = ch.id_banco
        WHERE 
            cd.id_cobro = :cobro_id
        ORDER BY 
            fv.numero_factura, cd.tipo_pago
    ";

    $stmtDetails = $pdo->prepare($queryDetails);
    $stmtDetails->bindParam(':cobro_id', $cobro_id, PDO::PARAM_INT);
    $stmtDetails->execute();

    if ($stmtDetails->rowCount() > 0) {
        // Encabezado de la tabla
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(50, 8, convertir('N° Factura'), 1, 0, 'C', true);
        $pdf->Cell(50, 8, convertir('Tipo de Pago'), 1, 0, 'C', true);
        $pdf->Cell(50, 8, convertir('Importe'), 1, 0, 'C', true);
        $pdf->Cell(40, 8, convertir('Detalle'), 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 9);
        $total = 0;
        $resumenPagos = [];
        while ($row = $stmtDetails->fetch(PDO::FETCH_ASSOC)) {
            $importe = (float)$row['importe_aplicado'];
            $total += $importe;
            
            $tipoPago = convertir($row['tipo_pago']);
            $detalle = '';
            
            // Si es CHEQUE, mostrar información del cheque
            if ($row['tipo_pago'] === 'CHEQUE' && !empty($row['cheque_numero'])) {
                $detalle = 'N° ' . $row['cheque_numero'];
                if (!empty($row['banco_descri'])) {
                    $detalle .= ' - ' . convertir($row['banco_descri']);
                }
                $tipoPago = 'CHEQUE';
            }
            
            $pdf->Cell(50, 8, $row['numero_factura'], 1, 0, 'C');
            $pdf->Cell(50, 8, $tipoPago, 1, 0, 'C');
            $pdf->Cell(50, 8, number_format($importe, 0, ',', '.'), 1, 0, 'R');
            $pdf->Cell(40, 8, convertir($detalle), 1, 1, 'L');
            
            // Acumular por tipo de pago para resumen
            $tipoPagoKey = $row['tipo_pago'];
            if (!isset($resumenPagos[$tipoPagoKey])) {
                $resumenPagos[$tipoPagoKey] = 0;
            }
            $resumenPagos[$tipoPagoKey] += $importe;
        }

        // Total
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(100, 8, convertir('TOTAL COBRADO:'), 1, 0, 'R');
        $pdf->Cell(50, 8, number_format($total, 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(40, 8, '', 1, 1, 'L');

        $pdf->Ln(5);
        
        // Resumen por medio de pago
        if (!empty($resumenPagos)) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 8, convertir('RESUMEN POR MEDIO DE PAGO:'), 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            foreach ($resumenPagos as $tipo => $monto) {
                $pdf->Cell(100, 6, convertir($tipo . ':'), 0, 0, 'L');
                $pdf->Cell(50, 6, number_format($monto, 0, ',', '.') . ' Gs', 0, 1, 'R');
            }
            $pdf->Ln(3);
        }

        // Información adicional
        if ($general['efectivo_recibido'] > 0) {
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(50, 8, 'Efectivo Recibido:', 0, 0, 'L');
            $pdf->Cell(80, 8, number_format((float)$general['efectivo_recibido'], 0, ',', '.') . ' Gs', 0, 1, 'L');
        }

        if ($general['vuelto'] > 0) {
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(50, 8, 'Vuelto:', 0, 0, 'L');
            $pdf->Cell(80, 8, number_format((float)$general['vuelto'], 0, ',', '.') . ' Gs', 0, 1, 'L');
        }

        if (!empty($general['observaciones'])) {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 8, 'Observaciones: ' . convertir($general['observaciones']), 0, 1, 'L');
        }
    } else {
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 10, 'No se encontraron detalles para este cobro.', 0, 1, 'C');
    }

    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 8, 'Este es un comprobante no fiscal. No tiene validez como documento tributario.', 0, 1, 'C');

    $pdf->Output();

} catch (PDOException $e) {
    die("Error al generar el comprobante: " . $e->getMessage());
}

