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

    $pdf = new BasePDF(['titulo' => 'Reporte de Productos']);
    $pdf->AddPage();

    // Encabezado
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, convertir('REPORTE DE PRODUCTOS'), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 8, 'Fecha: ' . date('d/m/Y H:i:s'), 0, 1, 'R');
    $pdf->Ln(5);

    // Tabla
    // Filtros opcionales
    $filtro_estado = isset($_GET['estado']) && $_GET['estado'] !== '' ? $_GET['estado'] : null;
    $filtro_iva = isset($_GET['iva_id']) && $_GET['iva_id'] !== '' ? (int)$_GET['iva_id'] : null;
    $filtro_tipo = isset($_GET['tipo_producto']) && $_GET['tipo_producto'] !== '' ? (int)$_GET['tipo_producto'] : null;

    $where = [];
    $params = [];
    
    if ($filtro_estado) {
        $where[] = "p.producto_estado = :estado";
        $params[':estado'] = $filtro_estado;
    }
    if ($filtro_iva) {
        $where[] = "p.iva_id = :iva_id";
        $params[':iva_id'] = $filtro_iva;
    }
    if ($filtro_tipo) {
        $where[] = "p.id_tipo_producto = :tipo_producto";
        $params[':tipo_producto'] = $filtro_tipo;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(200, 220, 255);

    // Encabezados
    $pdf->Cell(18, 8, convertir('Código'), 1, 0, 'C', true);
    $pdf->Cell(55, 8, convertir('Descripción'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('Unidad'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, convertir('Precio'), 1, 0, 'C', true);
    $pdf->Cell(20, 8, convertir('IVA'), 1, 0, 'C', true);
    $pdf->Cell(25, 8, convertir('Tipo'), 1, 0, 'C', true);
    $pdf->Cell(17, 8, convertir('Estado'), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 8);

    // Verificar si existe la tabla tipo_producto
    $checkTipoProducto = $pdo->query("
        SELECT 1 FROM information_schema.tables 
        WHERE table_name = 'tipo_producto' 
        LIMIT 1
    ");
    $existeTipoProducto = $checkTipoProducto->rowCount() > 0;
    
    $joinTipoProducto = '';
    $selectTipoProducto = "'Sin tipo' AS tipo_producto";
    
    if ($existeTipoProducto) {
        $cols = $pdo->query("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'tipo_producto'
        ")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('cod_tipo_prod', $cols) && in_array('t_p_descrip', $cols)) {
            $joinTipoProducto = 'LEFT JOIN tipo_producto tp ON tp.cod_tipo_prod = p.id_tipo_producto';
            $selectTipoProducto = "COALESCE(tp.t_p_descrip, 'Sin tipo') AS tipo_producto";
        }
    }
    
    $sql = "
        SELECT 
            p.producto_id,
            p.producto_descri,
            p.producto_precio,
            p.producto_estado,
            um.unidad_descri,
            COALESCE(ti.iva_descri, 'Sin IVA') AS iva_descri,
            {$selectTipoProducto}
        FROM productos p
        JOIN unidad_medida um ON p.id_unidad = um.id_unidad
        LEFT JOIN tipo_iva ti ON p.iva_id = ti.iva_id
        {$joinTipoProducto}
        {$whereClause}
        ORDER BY p.producto_id DESC
    ";

    $query = $pdo->prepare($sql);
    $query->execute($params);

    $totalProductos = 0;
    $totalPrecio = 0;
    $activos = 0;
    $inactivos = 0;

    while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
        $pdf->Cell(18, 7, $data['producto_id'], 1, 0, 'C');
        $pdf->Cell(55, 7, convertir(substr($data['producto_descri'], 0, 28)), 1, 0, 'L');
        $pdf->Cell(25, 7, convertir(substr($data['unidad_descri'], 0, 12)), 1, 0, 'C');
        $pdf->Cell(30, 7, number_format($data['producto_precio'], 0, ',', '.'), 1, 0, 'R');
        $pdf->Cell(20, 7, convertir(substr($data['iva_descri'], 0, 10)), 1, 0, 'C');
        $pdf->Cell(25, 7, convertir(substr($data['tipo_producto'], 0, 12)), 1, 0, 'C');
        $pdf->Cell(17, 7, convertir($data['producto_estado']), 1, 1, 'C');
        
        $totalProductos++;
        $totalPrecio += $data['producto_precio'];
        if ($data['producto_estado'] === 'ACTIVO') $activos++;
        else $inactivos++;
    }

    // Totales
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 8, convertir('RESUMEN:'), 0, 1, 'L');
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 6, 'Total de Productos: ' . $totalProductos, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Productos Activos: ' . $activos, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Productos Inactivos: ' . $inactivos, 0, 1, 'L');
    $pdf->Cell(0, 6, 'Precio Promedio: ' . number_format($totalProductos > 0 ? $totalPrecio / $totalProductos : 0, 0, ',', '.') . ' Gs.', 0, 1, 'L');

    $pdf->Output('I', 'Reporte_Productos_' . date('YmdHis') . '.pdf');

} catch (PDOException $e) {
    die("Error al generar el reporte: " . $e->getMessage());
}

