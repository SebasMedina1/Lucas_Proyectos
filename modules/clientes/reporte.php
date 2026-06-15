<?php
require_once '../../config/database.php';
require_once '../../reporte/pedido_ventas.php'; // Clase BasePDF

function convertir($text) {
    return mb_strtoupper($text, 'UTF-8');
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Filtros opcionales
    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
    $filtro_tipo = isset($_GET['tipo_cliente']) && $_GET['tipo_cliente'] !== '' ? $_GET['tipo_cliente'] : null;

    $where = array();
    $params = array();
    
    if ($filtro_estado) {
        $where[] = "c.cliente_estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($filtro_tipo) {
        $where[] = "c.tipo_cliente = :tipo_cliente";
        $params[':tipo_cliente'] = $filtro_tipo;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $pdf = new BasePDF(['titulo' => 'Reporte de Clientes']);
    $pdf->AddPage();

    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, convertir('REPORTE DE CLIENTES'), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
    
    // Mostrar filtros aplicados
    if ($filtro_estado || $filtro_tipo) {
        $pdf->Cell(0, 6, 'Filtros aplicados:', 0, 1, 'L');
        if ($filtro_estado) {
            $pdf->Cell(0, 6, '  - Estado: ' . convertir($filtro_estado), 0, 1, 'L');
        }
        if ($filtro_tipo) {
            $pdf->Cell(0, 6, '  - Tipo: ' . convertir($filtro_tipo), 0, 1, 'L');
        }
    }
    
    $pdf->Ln(5);

    // Tabla
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(200, 220, 255);

    // Encabezados
    $pdf->Cell(15, 8, convertir('Código'), 1, 0, 'C', true);
    $pdf->Cell(50, 8, convertir('Nombre/Razón Social'), 1, 0, 'C', true);
    $pdf->Cell(15, 8, convertir('Tipo'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('RUC/CI'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('Teléfono'), 1, 0, 'C', true);
    $pdf->Cell(40, 8, convertir('Email'), 1, 0, 'C', true);
    $pdf->Cell(20, 8, convertir('Estado'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 7);

    $sql = "
        SELECT 
            c.id_cliente,
            c.cliente_nombre || ' ' || COALESCE(c.cliente_apellido, '') AS nombre_completo,
            COALESCE(c.tipo_cliente, 'PERSONA') AS tipo_cliente,
            c.cliente_ruc,
            COALESCE(c.cliente_ci, '') AS cliente_ci,
            COALESCE(c.cliente_telefono, '') AS cliente_telefono,
            COALESCE(c.cliente_email, '') AS cliente_email,
            c.cliente_estado
        FROM clientes c
        {$whereClause}
        ORDER BY c.id_cliente DESC
    ";

    $query = $pdo->prepare($sql);
    $query->execute($params);

    $totalClientes = 0;
    $activos = 0;
    $inactivos = 0;
    $personas = 0;
    $empresas = 0;

    while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
        $rucCi = !empty($data['cliente_ci']) ? $data['cliente_ci'] : $data['cliente_ruc'];
        $tipoCliente = strtoupper($data['tipo_cliente'] ?? 'PERSONA');
        
        $pdf->Cell(15, 7, $data['id_cliente'], 1, 0, 'C');
        $pdf->Cell(50, 7, convertir(substr($data['nombre_completo'], 0, 28)), 1, 0, 'L');
        $pdf->Cell(15, 7, convertir(substr($tipoCliente, 0, 6)), 1, 0, 'C');
        $pdf->Cell(25, 7, substr($rucCi, 0, 12), 1, 0, 'C');
        $pdf->Cell(25, 7, substr($data['cliente_telefono'], 0, 12), 1, 0, 'C');
        $pdf->Cell(40, 7, substr($data['cliente_email'], 0, 25), 1, 0, 'L');
        $pdf->Cell(20, 7, convertir($data['cliente_estado']), 1, 1, 'C');
        
        $totalClientes++;
        if ($data['cliente_estado'] === 'ACTIVO') $activos++;
        else $inactivos++;
        if ($tipoCliente === 'PERSONA') $personas++;
        else $empresas++;
    }

    // Totales
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, convertir('RESUMEN:'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, 'Total de Clientes: ' . $totalClientes, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Clientes Activos: ' . $activos, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Clientes Inactivos: ' . $inactivos, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Personas Físicas: ' . $personas, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Empresas: ' . $empresas, 0, 1, 'L');

    $pdf->Output('I', 'Reporte_Clientes_' . date('YmdHis') . '.pdf');

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

