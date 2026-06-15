<?php
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['prov_id']) || !isset($_GET['oc_id'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Faltan parámetros']);
  exit;
}

$prov = (int)$_GET['prov_id'];
$oc   = (int)$_GET['oc_id'];

try {
  $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

  // Verifica que la OC pertenezca al proveedor y esté EMITIDA según especificación punto 6
  $st = $pdo->prepare("
    SELECT id_orden_compra
    FROM orden_de_compra
    WHERE id_orden_compra=:id AND id_proveedor=:prov AND orden_estado='EMITIDA'
    LIMIT 1
  ");
  $st->execute([':id'=>$oc, ':prov'=>$prov]);
  if (!$st->fetch()) {
    echo json_encode(['ok'=>false,'msg'=>'OC inexistente / proveedor no coincide / estado no EMITIDA']);
    exit;
  }

  // Detalle OC + descuento del presupuesto + ya facturado (para tope)
  // Obtener el descuento directamente del presupuesto asociado a la OC
  $sql = "SELECT
            d.id_materia_prima            AS codigo,
            mp.materia_prima_descripcion   AS producto,
            d.oc_cantidad_compra     AS cant_oc,
            d.oc_precio_compra       AS precio,
            COALESCE(pdc.descuento, 0)::numeric AS descuento,
            ti.iva_descri            AS iva
          FROM orden_detalle_compra d
          JOIN materia_prima mp  ON mp.id_materia_prima = d.id_materia_prima
          LEFT JOIN tipo_iva  ti ON ti.iva_id     = mp.iva_id
          LEFT JOIN orden_de_compra oc ON oc.id_orden_compra = d.id_orden_compra
          LEFT JOIN presupuesto_compra pc ON pc.id_presupuesto_compra = oc.id_presupuesto_compra
          LEFT JOIN presupuesto_detalle_compra pdc ON pdc.id_presupuesto_compra = pc.id_presupuesto_compra 
            AND pdc.id_materia_prima = d.id_materia_prima
          WHERE d.id_orden_compra = :oc
          ORDER BY d.id_materia_prima;
  ";
  $st = $pdo->prepare($sql);
  $st->execute([':oc'=>$oc]);
  $items = $st->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
