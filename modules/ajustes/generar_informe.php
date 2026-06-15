<?php
require '../../config/database.php';
require_once '../../reporte/reporte_ajustes.php';
if (!function_exists('convertir')) {
    function convertir($texto) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$texto);
    }
}
if (!function_exists('inferTipoDesdeDescripcion')) {
    function inferTipoDesdeDescripcion(string $descripcion): string {
        $texto = strtoupper($descripcion);
        if (strpos($texto, 'SOBRANTE') !== false) {
            return 'ENTRADA';
        }
        if (
            strpos($texto, 'FALTANTE') !== false ||
            strpos($texto, 'PRODUCTO DAÑADO') !== false ||
            strpos($texto, 'PRODUCTO DANADO') !== false
        ) {
            return 'SALIDA';
        }
        return 'SALIDA';
    }
}
// Validaciones de parámetros
if (!isset($_GET['estado']) || $_GET['estado'] === '') {
    echo "<script>
            alert('No se recibió un estado válido para generar el informe.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}
$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$reFecha = '/^\d{4}-\d{2}-\d{2}$/';
if ($desde === '' || $hasta === '' || !preg_match($reFecha, $desde) || !preg_match($reFecha, $hasta)) {
    echo "<script>
            alert('El rango de fechas es obligatorio y debe tener el formato YYYY-MM-DD.');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}
if ($hasta < $desde) {
    echo "<script>
            alert('La fecha \"Hasta\" no puede ser anterior a \"Desde\".');
            window.location.href = '../reportes/view.php';
          </script>";
    exit();
}
$estadoParam = trim($_GET['estado']);
$estadoUpper = mb_strtoupper($estadoParam);
$esTotal     = in_array($estadoUpper, ['REPORTE TOTAL','TOTAL'], true);
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $sql = "
        SELECT
            a.id_ajuste,
            TO_CHAR(a.ajuste_fecha,'YYYY-MM-DD') AS fecha,
            a.ajuste_estado,
            u.username AS usuario,
            COALESCE(s.descripcion_sucursal, 'S/D') AS sucursal,
            d.deposito_descri AS deposito,
            m.motivo_descripcion AS motivo,
            mp.materia_prima_descripcion AS producto,
            ad.ajuste_cantidad AS cantidad
        FROM ajustes a
        JOIN usuarios u        ON u.id_usuario      = a.id_usuario
        LEFT JOIN sucursales s ON s.id_sucursal     = u.id_sucursal
        JOIN deposito d        ON d.deposito_id     = a.deposito_id
        JOIN ajustes_detalle ad ON ad.id_ajuste     = a.id_ajuste
        JOIN motivo m  ON m.id_motivo = ad.id_motivo
        JOIN materia_prima mp  ON mp.id_materia_prima = ad.id_materia_prima
        WHERE DATE(a.ajuste_fecha) BETWEEN :desde AND :hasta
    ";
    if (!$esTotal) {
        $sql .= " AND UPPER(a.ajuste_estado) = :estado ";
    }
    $sql .= " ORDER BY a.ajuste_fecha DESC, a.id_ajuste DESC, mp.materia_prima_descripcion ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':desde', $desde, PDO::PARAM_STR);
    $stmt->bindParam(':hasta', $hasta, PDO::PARAM_STR);
    if (!$esTotal) {
        $stmt->bindParam(':estado', $estadoUpper, PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (!$rows) {
        header("Location: ../reportes/view.php?alert=6");
        exit();
    }
    $ajustes = [];
    foreach ($rows as $row) {
        $id = (int)$row['id_ajuste'];
        if (!isset($ajustes[$id])) {
            $ajustes[$id] = [
                'fecha'    => $row['fecha'],
                'estado'   => $row['ajuste_estado'],
                'usuario'  => $row['usuario'],
                'sucursal' => $row['sucursal'],
                'deposito' => $row['deposito'],
                'motivo'   => $row['motivo'],
                'tipo'     => inferTipoDesdeDescripcion($row['motivo']),
                'detalle'  => [],
            ];
        }
        $ajustes[$id]['detalle'][] = [
            'producto' => $row['producto'],
            'cantidad' => (int)$row['cantidad'],
        ];
    }
    $titulo = $esTotal
        ? 'Informe de Ajustes - Todos los estados'
        : 'Informe de Ajustes - Estado: ' . $estadoUpper;
    $pdf = new BasePDF('P','mm','A4', ['titulo' => $titulo]);
    $pdf->AddPage();
    $pdf->SetFont('Arial','',10);
    foreach ($ajustes as $id => $ajuste) {
        // Salto de página si es necesario
        if ($pdf->GetY() > 240) {
            $pdf->AddPage();
            $pdf->SetFont('Arial','',10);
        }
        $pdf->SetFont('Arial','B',11);
        $pdf->Cell(0, 7, convertir("Ajuste #{$id} - Fecha: {$ajuste['fecha']}"), 0, 1, 'L');
        $pdf->SetFont('Arial','',10);
        $pdf->Cell(0, 6, convertir("Usuario: {$ajuste['usuario']} | Estado: {$ajuste['estado']}"), 0, 1, 'L');
        $pdf->Cell(0, 6, convertir("Sucursal: {$ajuste['sucursal']} | Depósito: {$ajuste['deposito']}"), 0, 1, 'L');
        $pdf->Cell(0, 6, convertir("Motivo: {$ajuste['motivo']} | Tipo: {$ajuste['tipo']}"), 0, 1, 'L');
        $pdf->Ln(2);
        $pdf->SetFont('Arial','B',10);
        $pdf->SetFillColor(200,220,255);
        $pdf->Cell(15, 8, convertir('#'), 1, 0, 'C', true);
        $pdf->Cell(120, 8, convertir('Producto'), 1, 0, 'L', true);
        $pdf->Cell(35, 8, convertir('Cantidad'), 1, 1, 'C', true);
        $pdf->SetFont('Arial','',10);
        if (empty($ajuste['detalle'])) {
            $pdf->Cell(170, 8, convertir('Sin ítems cargados para este ajuste.'), 1, 1, 'C');
        } else {
            $n = 1;
            foreach ($ajuste['detalle'] as $det) {
                if ($pdf->GetY() > 260) {
                    $pdf->AddPage();
                    $pdf->SetFont('Arial','B',10);
                    $pdf->SetFillColor(200,220,255);
                    $pdf->Cell(15, 8, convertir('#'), 1, 0, 'C', true);
                    $pdf->Cell(120, 8, convertir('Producto'), 1, 0, 'L', true);
                    $pdf->Cell(35, 8, convertir('Cantidad'), 1, 1, 'C', true);
                    $pdf->SetFont('Arial','',10);
                }
                $pdf->Cell(15, 8, convertir($n++), 1, 0, 'C');
                $pdf->Cell(120, 8, convertir($det['producto']), 1, 0, 'L');
                $pdf->Cell(35, 8, convertir($det['cantidad']), 1, 1, 'C');
            }
        }
        $pdf->SetFont('Arial','B',10);
        $totalItems = count($ajuste['detalle']);
        $pdf->Cell(170, 8, convertir("Total de ítems: {$totalItems}"), 0, 1, 'R');
        $pdf->Ln(4);
    }
    $pdf->Output('I', 'Informe_Ajustes.pdf');
} catch (PDOException $e) {
    echo "Error al generar el informe: " . htmlspecialchars($e->getMessage());
    exit();
}