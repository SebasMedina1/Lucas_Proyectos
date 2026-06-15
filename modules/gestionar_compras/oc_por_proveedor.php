<?php
require "../../config/database.php";
header('Content-Type: application/json; charset=utf-8');
$prov = (int)($_GET['prov_id'] ?? 0);
$dsn = "pgsql:host=$host;port=$port;dbname=$database;";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

/*  trae órdenes cuyo orden_estado es PENDIENTE. y tambien permite incluir aquellas que sí 
tienen facturas pero que están en estado ANULADO (f.id_orden_compra IS NULL OR UPPER(f.fac_estado) = 'ANULADO'). */
$st = $pdo->prepare("SELECT oc.id_orden_compra
FROM orden_de_compra oc
LEFT JOIN factura_compra f
  ON f.id_orden_compra = oc.id_orden_compra
WHERE oc.id_proveedor = :p
  AND oc.orden_estado = 'EMITIDA'
  AND (f.id_orden_compra IS NULL OR UPPER(f.fac_estado) = 'ANULADO')
ORDER BY oc.id_orden_compra DESC;


");
$st->execute([':p'=>$prov]);
echo json_encode(['items'=>$st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
