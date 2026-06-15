<?php
header('Content-Type: application/json; charset=utf-8');

try {
  require "../../config/database.php";
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);

  $oc_id = isset($_GET['oc_id']) ? (int)$_GET['oc_id'] : 0;
  if ($oc_id <= 0) { echo json_encode(['error'=>'oc_id inválido']); exit; }
  
  // Si se está editando una NR, excluirla del cálculo del saldo
  $excluir_nr_id = isset($_GET['excluir_nr_id']) ? (int)$_GET['excluir_nr_id'] : 0;

  // Obtener detalle de OC con saldo disponible y depósito desde stock_materia_prima
  // El saldo es la cantidad de OC menos lo ya remitido en NRs no anuladas
  // Si estamos editando (excluir_nr_id > 0), excluimos esa NR del cálculo
  // El depósito se obtiene desde stock_materia_prima (donde está el stock actual)
  $sql = "
    SELECT
      odc.id_materia_prima,
      odc.oc_cantidad_compra AS cantidad_oc,
      odc.oc_precio_compra   AS precio,
      mp.materia_prima_descripcion AS producto_descripcion,
      COALESCE(SUM(CASE WHEN nr.nota_estado <> 'ANULADO' AND (nr.id_nota_remision <> :excluir_nr_id OR :excluir_nr_id = 0) THEN nrd.nota_cantidad ELSE 0 END), 0) AS cantidad_remitida,
      (odc.oc_cantidad_compra - COALESCE(SUM(CASE WHEN nr.nota_estado <> 'ANULADO' AND (nr.id_nota_remision <> :excluir_nr_id OR :excluir_nr_id = 0) THEN nrd.nota_cantidad ELSE 0 END), 0)) AS saldo_disponible,
      smp.deposito_id,
      d.deposito_descri
    FROM orden_detalle_compra odc
    LEFT JOIN materia_prima mp ON mp.id_materia_prima = odc.id_materia_prima
    LEFT JOIN nota_remision_compra nr ON nr.id_orden_compra = odc.id_orden_compra
    LEFT JOIN nota_remision_detalle_compra nrd ON nrd.id_nota_remision = nr.id_nota_remision 
      AND nrd.id_materia_prima = odc.id_materia_prima
    LEFT JOIN stock_materia_prima smp ON smp.id_materia_prima = odc.id_materia_prima
    LEFT JOIN deposito d ON d.deposito_id = smp.deposito_id
    WHERE odc.id_orden_compra = :id
    GROUP BY odc.id_materia_prima, odc.oc_cantidad_compra, odc.oc_precio_compra, 
             mp.materia_prima_descripcion, smp.deposito_id, d.deposito_descri
    ORDER BY odc.id_materia_prima
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':id'=>$oc_id, ':excluir_nr_id'=>$excluir_nr_id]);

  $data = [];
  while ($r = $st->fetch()) {
    $saldo = max(0, (int)$r['saldo_disponible']); // No permitir saldo negativo
    $data[] = [
      'id_materia_prima' => (int)$r['id_materia_prima'],
      'producto'    => $r['producto_descripcion'] ?? ('ID ' . (int)$r['id_materia_prima']),
      'cantidad'    => (int)$r['cantidad_oc'],
      'cantidad_remitida' => (int)$r['cantidad_remitida'],
      'saldo_disponible' => $saldo,
      'precio'      => (int)$r['precio'],
      'deposito_id' => (int)($r['deposito_id'] ?? 0),
      'deposito_descri' => $r['deposito_descri'] ?? 'Sin depósito',
      'iva_descri'  => '' // opcional (solo display)
    ];
  }

  echo json_encode($data, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  echo json_encode(['error'=>$e->getMessage()]);
}
