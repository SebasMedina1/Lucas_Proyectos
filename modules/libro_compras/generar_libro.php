<?php
// Consolidar documentos fiscales del período usando la función consolidarDocumentos
require 'consolidar_documentos.php';

$registros = [];
$totales = [
  'exento' => 0,
  'gravado5' => 0,
  'gravado10' => 0,
  'iva5' => 0,
  'iva10' => 0,
  'total' => 0,
];

try {
  $proveedorId = ($filtro_proveedor > 0) ? $filtro_proveedor : null;
  $sucursalId = ($filtro_sucursal > 0) ? $filtro_sucursal : null;
  $tipoDoc = ($filtro_tipo !== '') ? $filtro_tipo : null;
  
  $documentos = consolidarDocumentos($pdo, $desde, $hasta, $proveedorId, $tipoDoc, $sucursalId);
  
  // Convertir formato de documentos a formato de registros para la vista
  foreach ($documentos as $doc) {
    $tipoLabel = ($doc['tipo'] === 'FACTURA') ? 'Factura' : 
                 (($doc['tipo'] === 'NOTA_CREDITO') ? 'Nota de Crédito' : 'Nota de Débito');
    
    $registros[] = [
      'id' => $doc['id'],
      'fecha' => $doc['fecha'],
      'tipo' => $tipoLabel,
      'numero' => $doc['numero'],
      'timbrado' => $doc['timbrado'],
      'ruc' => $doc['ruc'],
      'razon' => $doc['razon'],
      'condicion' => $doc['condicion'],
      'exento' => $doc['exento'],
      'gravado5' => $doc['base_5'],
      'gravado10' => $doc['base_10'],
      'iva5' => $doc['iva_5'],
      'iva10' => $doc['iva_10'],
      'total' => $doc['total'],
      'sucursal' => $doc['sucursal'],
      'id_documento' => $doc['id_documento'],
      'tipo_documento' => $doc['tipo_documento']
    ];
    
    $totales['exento'] += $doc['exento'];
    $totales['gravado5'] += $doc['base_5'];
    $totales['gravado10'] += $doc['base_10'];
    $totales['iva5'] += $doc['iva_5'];
    $totales['iva10'] += $doc['iva_10'];
    $totales['total'] += $doc['total'];
  }
  
  // Ordenar registros
  $ordenPermitidos = ['fecha', 'tipo', 'numero', 'ruc', 'razon', 'total'];
  $campoOrden = in_array($ordenar_por, $ordenPermitidos) ? $ordenar_por : 'fecha';
  
  usort($registros, function($a, $b) use ($campoOrden, $orden) {
    $valA = $a[$campoOrden] ?? '';
    $valB = $b[$campoOrden] ?? '';
    
    if ($campoOrden === 'fecha') {
      $cmp = strcmp($valA, $valB);
    } elseif (in_array($campoOrden, ['exento', 'gravado5', 'gravado10', 'iva5', 'iva10', 'total'])) {
      $cmp = (int)$valA <=> (int)$valB;
    } else {
      $cmp = strcmp((string)$valA, (string)$valB);
    }
    
    return $orden === 'DESC' ? -$cmp : $cmp;
  });
  
  // Paginación
  $total_registros = count($registros);
  $total_paginas = ceil($total_registros / $por_pagina);
  $offset = ($pagina - 1) * $por_pagina;
  $registros_paginados = array_slice($registros, $offset, $por_pagina);
  
} catch (PDOException $e) {
  echo '<div class="alert alert-danger">Error al generar el libro: ' . htmlspecialchars($e->getMessage()) . '</div>';
  $registros_paginados = [];
  $totales = ['exento' => 0, 'gravado5' => 0, 'gravado10' => 0, 'iva5' => 0, 'iva10' => 0, 'total' => 0];
  $total_registros = 0;
  $total_paginas = 0;
}
?>

<?php if (!empty($registros_paginados) || $total_registros === 0): ?>
<div class="card shadow mb-4">
  <div class="card-header py-3 d-flex justify-content-between align-items-center">
    <h6 class="m-0 font-weight-bold text-primary">
      Vista Previa del Libro (<?= number_format($total_registros, 0, ',', '.') ?> documentos)
    </h6>
    <div>
      <a href="exportar_pdf.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-danger btn-sm">
        <i class="fas fa-file-pdf"></i> PDF
      </a>
      <a href="exportar_excel.php?<?= http_build_query($_GET) ?>" target="_blank" class="btn btn-success btn-sm">
        <i class="fas fa-file-excel"></i> Excel
      </a>
      <a href="exportar_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-info btn-sm">
        <i class="fas fa-file-csv"></i> CSV
      </a>
    </div>
  </div>
  <div class="card-body">
    <?php if (empty($registros_paginados)): ?>
      <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> No se encontraron documentos en el período seleccionado.
      </div>
    <?php else: ?>
      <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
        <table class="table table-bordered table-sm table-hover" id="tabla-libro">
          <thead class="table-secondary" style="position: sticky; top: 0; background: white; z-index: 10;">
            <tr>
              <th>
                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar' => 'fecha', 'orden' => $ordenar_por === 'fecha' && $orden === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="text-dark">
                  Fecha <i class="fas fa-sort"></i>
                </a>
              </th>
              <th>
                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar' => 'tipo', 'orden' => $ordenar_por === 'tipo' && $orden === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="text-dark">
                  Tipo <i class="fas fa-sort"></i>
                </a>
              </th>
              <th>Número</th>
              <th>Timbrado</th>
              <th>RUC</th>
              <th>
                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar' => 'razon', 'orden' => $ordenar_por === 'razon' && $orden === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="text-dark">
                  Razón Social <i class="fas fa-sort"></i>
                </a>
              </th>
              <th>Condición</th>
              <th class="text-end">Exento</th>
              <th class="text-end">Gravado 5%</th>
              <th class="text-end">Gravado 10%</th>
              <th class="text-end">IVA 5%</th>
              <th class="text-end">IVA 10%</th>
              <th>
                <a href="?<?= http_build_query(array_merge($_GET, ['ordenar' => 'total', 'orden' => $ordenar_por === 'total' && $orden === 'ASC' ? 'DESC' : 'ASC'])) ?>" class="text-dark">
                  Total <i class="fas fa-sort"></i>
                </a>
              </th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($registros_paginados as $reg): ?>
              <tr>
                <td><?= htmlspecialchars($reg['fecha']) ?></td>
                <td><?= htmlspecialchars($reg['tipo']) ?></td>
                <td><?= htmlspecialchars($reg['numero']) ?></td>
                <td><?= htmlspecialchars($reg['timbrado']) ?></td>
                <td><?= htmlspecialchars($reg['ruc']) ?></td>
                <td><?= htmlspecialchars($reg['razon']) ?></td>
                <td><?= htmlspecialchars($reg['condicion']) ?></td>
                <td class="text-end"><?= number_format($reg['exento'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($reg['gravado5'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($reg['gravado10'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($reg['iva5'], 0, ',', '.') ?></td>
                <td class="text-end"><?= number_format($reg['iva10'], 0, ',', '.') ?></td>
                <td class="text-end"><strong><?= number_format($reg['total'], 0, ',', '.') ?></strong></td>
                <td>
                  <?php if ($reg['tipo_documento'] === 'FACTURA'): ?>
                    <a href="../gestionar_compras/reporte.php?fac_id=<?= $reg['id_documento'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Ver Factura">
                      <i class="fas fa-eye"></i>
                    </a>
                  <?php elseif ($reg['tipo_documento'] === 'CREDITO' || $reg['tipo_documento'] === 'DEBITO'): ?>
                    <a href="../nota_credito/reporte.php?nota_id=<?= $reg['id_documento'] ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Ver Nota">
                      <i class="fas fa-eye"></i>
                    </a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-secondary">
            <tr>
              <td colspan="7" class="text-end"><strong>TOTALES</strong></td>
              <td class="text-end"><strong><?= number_format($totales['exento'], 0, ',', '.') ?></strong></td>
              <td class="text-end"><strong><?= number_format($totales['gravado5'], 0, ',', '.') ?></strong></td>
              <td class="text-end"><strong><?= number_format($totales['gravado10'], 0, ',', '.') ?></strong></td>
              <td class="text-end"><strong><?= number_format($totales['iva5'], 0, ',', '.') ?></strong></td>
              <td class="text-end"><strong><?= number_format($totales['iva10'], 0, ',', '.') ?></strong></td>
              <td class="text-end"><strong><?= number_format($totales['total'], 0, ',', '.') ?></strong></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
      
      <?php if ($total_paginas > 1): ?>
        <nav aria-label="Paginación">
          <ul class="pagination justify-content-center">
            <?php if ($pagina > 1): ?>
              <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina - 1])) ?>">Anterior</a>
              </li>
            <?php endif; ?>
            
            <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
              <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            
            <?php if ($pagina < $total_paginas): ?>
              <li class="page-item">
                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina + 1])) ?>">Siguiente</a>
              </li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
      
      <div class="card shadow mb-4" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px;">
        <h5 class="mb-3">Resumen por Tasa de IVA</h5>
        <div class="row">
          <div class="col-md-4">
            <strong>Exentas:</strong> <?= number_format($totales['exento'], 0, ',', '.') ?>
          </div>
          <div class="col-md-4">
            <strong>Gravado 5%:</strong> <?= number_format($totales['gravado5'], 0, ',', '.') ?> 
            (IVA: <?= number_format($totales['iva5'], 0, ',', '.') ?>)
          </div>
          <div class="col-md-4">
            <strong>Gravado 10%:</strong> <?= number_format($totales['gravado10'], 0, ',', '.') ?> 
            (IVA: <?= number_format($totales['iva10'], 0, ',', '.') ?>)
          </div>
        </div>
        <div class="row mt-2">
          <div class="col-md-12">
            <strong>Total General:</strong> <?= number_format($totales['total'], 0, ',', '.') ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

