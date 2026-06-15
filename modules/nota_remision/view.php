<?php
session_start();
require "../../config/database.php";

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Obtener el nombre de usuario de la sesión
$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username LIMIT 1");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $auth_user = $query->fetch(PDO::FETCH_ASSOC);

    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }

    $permisoAcceso = isset($auth_user['id_cargo']) ? (int)$auth_user['id_cargo'] : 0;

} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Notas de Remisión (Compras)';
$extra_css = [
    'vendor/datatables/dataTables.bootstrap4.min.css'
];
$extra_js_plugins = [
    'vendor/datatables/jquery.dataTables.min.js',
    'vendor/datatables/dataTables.bootstrap4.min.js'
];

// Variables para el sidebar (necesarias para header.php)
$allowedCargos = [1,3,5];
$showCoreSidebar = in_array($permisoAcceso, $allowedCargos, true);
$showReportes = in_array($permisoAcceso, [3,5], true);
$showAdministracion = ($permisoAcceso === 5);

// Incluir header común
include '../../header.php';
?>

<!-- Contenido específico del módulo -->
<div class="container-fluid">
        <?php if (!isset($_GET['nueva_nota']) || (($_GET['form'] ?? '') !== 'add' && ($_GET['form'] ?? '') !== 'edit')): ?>
          <!-- LISTA -->
          <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Notas de Remisión (Compras)</h1>
            <a href="?nueva_nota=add&form=add" class="btn btn-primary btn-sm shadow-sm">
              <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Nota
            </a>
          </div>
<!-- 
          <button id="btn-informes" class="btn btn-info btn-sm shadow-sm mb-3">
            <i class="fas fa-file-alt fa-sm text-white-50"></i> Generar Informe
          </button>

          Alertas -->
          <?php
            if (!empty($_GET['alert'])) {
              $alertMessage = ''; $alertClass = 'alert-success';
              if ($_GET['alert'] == 1) { $alertMessage = "Datos registrados correctamente."; }
              elseif ($_GET['alert'] == 2) { $alertMessage = "Datos modificados correctamente."; }
              elseif ($_GET['alert'] == 3) { $alertMessage = "Registro anulado correctamente."; }
              elseif ($_GET['alert'] == 4) { $alertMessage = "No se pudo realizar la operación."; $alertClass = 'alert-danger'; }
              echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>";
              echo $alertMessage;
              echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
              echo "<span aria-hidden='true'>&times;</span>";
              echo "</button></div>";
            }
          ?>

          <!-- Tabla -->
          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Lista de Notas de Remisión</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Estado</th>
                      <th>Proveedor</th>
                      <th>OC</th>
                      <th>Nº Remisión</th>
                      <th>Fecha</th>
                      <th>Depósito</th>
                      <th>Conductor</th>
                      <th>Vehículo</th>
                      <th>Total</th>
                      <th>Detalle</th>
                      <th>Usuario</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      try {
                        $dsnL = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdoL = new PDO($dsnL, $user, $pass);
                        $pdoL->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        // Columnas reales según tu esquema:
                        // vehiculos: vehiculo_id, vehiculo_marca, vehiculo_ano, vehiculo_color
                        // NOTA: No tiene vehiculo_modelo ni matricula
                        // nota_remision_compra contiene: id_nota_remision, nota_estado, nota_fecha,
                        // nota_remision_total, nota_remision_nro, id_factura_compra, id_proveedor, id_usuario,
                        // conductor_id, vehiculo_id
                        // La orden de compra se obtiene a través de factura_compra

                        // Consulta optimizada: usar subconsulta para id_orden_compra
                        $sql = "SELECT 
                                  r.id_nota_remision,
                                  r.nota_estado,
                                  r.nota_fecha,
                                  r.nota_remision_total,
                                  r.nota_remision_nro,
                                  r.id_factura_compra,
                                  r.deposito_id,
                                  COALESCE(r.id_orden_compra, 
                                    (SELECT fc.id_orden_compra FROM factura_compra fc WHERE fc.id_factura_compra = r.id_factura_compra LIMIT 1),
                                    0) AS id_orden_compra,
                                  pv.razon_social,
                                  u.username,
                                  c.conductor_nombre,
                                  c.conductor_apellido,
                                  v.vehiculo_marca,
                                  v.vehiculo_ano,
                                  v.vehiculo_color,
                                  d.deposito_descri
                                FROM nota_remision_compra r
                                JOIN proveedor pv           ON pv.id_proveedor = r.id_proveedor
                                JOIN usuarios u             ON u.id_usuario   = r.id_usuario
                                LEFT JOIN deposito d        ON d.deposito_id = r.deposito_id
                                LEFT JOIN conductores c     ON c.conductor_id = r.conductor_id
                                LEFT JOIN vehiculos v       ON v.vehiculo_id  = r.vehiculo_id
                                ORDER BY r.id_nota_remision DESC
                                LIMIT 1000";

                        $query = $pdoL->query($sql);

                        while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                          $vehTxt = trim(
                            ($data['vehiculo_marca'] ?? '') . ' ' .
                            ($data['vehiculo_ano'] ?? '') . ' ' .
                            ($data['vehiculo_color'] ?? '')
                          );

                          $condTxt = trim(($data['conductor_nombre'] ?? '').' '.($data['conductor_apellido'] ?? ''));

                          $depositoTxt = htmlspecialchars($data['deposito_descri'] ?? 'N/A');
                          $estadoTxt = htmlspecialchars($data['nota_estado']);
                          $estadoClass = '';
                          if (strtoupper($estadoTxt) === 'EMITIDA') $estadoClass = 'badge-success';
                          elseif (strtoupper($estadoTxt) === 'ANULADO') $estadoClass = 'badge-danger';
                          else $estadoClass = 'badge-secondary';
                          
                          echo '<tr>
                                  <td>'.htmlspecialchars($data['id_nota_remision']).'</td>
                                  <td><span class="badge '.$estadoClass.'">'.$estadoTxt.'</span></td>
                                  <td>'.htmlspecialchars($data['razon_social']).'</td>
                                  <td>'.htmlspecialchars($data['id_orden_compra'] ?? 'N/A').'</td>
                                  <td>'.htmlspecialchars($data['nota_remision_nro'] ?? '').'</td>
                                  <td>'.htmlspecialchars($data['nota_fecha']).'</td>
                                  <td>'.$depositoTxt.'</td>
                                  <td>'.htmlspecialchars($condTxt).'</td>
                                  <td>'.htmlspecialchars($vehTxt).'</td>
                                  <td>'.number_format((int)$data['nota_remision_total'],0,',','.').'</td>
                                  <td>
                                    <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="'.htmlspecialchars($data['id_nota_remision']).'">
                                      Ver Detalle
                                    </button>
                                  </td>
                                  <td>'.htmlspecialchars($data['username']).'</td>
                                  <td>
                                    <a href="reporte.php?nr_id='.htmlspecialchars($data['id_nota_remision']).'" target="_blank" class="btn btn-warning btn-sm mb-1" title="Imprimir">
                                      <i class="fas fa-print"></i>
                                    </a>';

                          $estadoNR = strtoupper(trim($data['nota_estado']));
                          $idFacturaCompra = (int)($data['id_factura_compra'] ?? 0);
                          $estaConciliada = ($idFacturaCompra > 0);
                          
                          // Mostrar botón Editar solo si está EMITIDA y no conciliada
                          if ($estadoNR === 'EMITIDA' && !$estaConciliada) {
                            echo '
                                    <a href="?nueva_nota=edit&form=edit&id='.htmlspecialchars($data['id_nota_remision']).'" class="btn btn-primary btn-sm mb-1" title="Editar">
                                      <i class="fas fa-edit"></i>
                                    </a>';
                          }
                          
                          // Mostrar botón Anular solo si está EMITIDA y no conciliada
                          if ($estadoNR === 'EMITIDA' && !$estaConciliada) {
                            echo '
                                    <button type="button" class="btn btn-danger btn-sm btn-anular-nr mb-1" 
                                            data-id="'.htmlspecialchars($data['id_nota_remision']).'"
                                            data-num="'.htmlspecialchars($data['nota_remision_nro']).'"
                                            data-proveedor="'.htmlspecialchars($data['razon_social']).'"
                                            title="Anular">
                                      <i class="fas fa-trash"></i>
                                    </button>';
                          }

                          echo '
                                  </td>
                                </tr>';
                        }
                      } catch (PDOException $e) {
                        die("Error al consultar los datos: " . $e->getMessage());
                      }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Modal Informe -->
          <div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalEstadoLabel" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title" id="modalEstadoLabel">Seleccionar Estado</h5>
                  <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                  </button>
                </div>
                <form action="generar_informe.php" method="GET" target="_blank">
                  <div class="modal-body">
                    <label for="estado">Estado:</label>
                    <select id="estado" name="estado" class="form-control" required>
                      <option value="">Seleccione un estado</option>
                      <option value="PENDIENTE_FACTURA">PENDIENTE_FACTURA</option>
                      <option value="ANULADO">ANULADO</option>
                    </select>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Generar PDF</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

        <?php else: ?>
          <!-- FORMULARIO -->
          <?php include "form.php"; ?>
        <?php endif; ?>
      </div>

    <!-- Modal Detalle -->
    <div class="modal fade" id="detalleModal" tabindex="-1" aria-labelledby="detalleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detalleModalLabel">Detalle de la Nota de Remisión</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                </tr>
              </thead>
              <tbody id="detalleNRBody"></tbody>
            </table>
          </div>
        </div>
    </div>
</div>

<!-- Modal de anulación -->
<div class="modal fade" id="anularNRModal" tabindex="-1" role="dialog" aria-labelledby="anularNRModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="anularNRModalLabel">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular esta Nota de Remisión?</strong></p>
        <p class="mb-0" id="anularNRInfo"></p>
        <div class="alert alert-warning mt-3">
          <strong>Advertencia:</strong> Se revertirá el stock ingresado.
        </div>
        <div id="anularNRVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-danger">
            <strong>Error:</strong> Esta Nota de Remisión no puede ser anulada porque:
            <ul id="listaVinculosNR" class="mb-0 mt-2"></ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarAnularNRBtn">Sí, anular</button>
      </div>
    </div>
  </div>
</div>

    <?php
    $inline_js = "
    document.addEventListener('DOMContentLoaded', () => {
        const alertMessage = document.getElementById('alert-message');
        if (alertMessage) setTimeout(() => alertMessage.remove(), 3000);

        if (window.jQuery && jQuery().DataTable) {
            jQuery('#dataTable').DataTable({
                language: {
                    decimal: '',
                    emptyTable: 'No hay datos disponibles en la tabla',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                    infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                    infoFiltered: '(filtrado de _MAX_ registros totales)',
                    lengthMenu: 'Mostrar _MENU_ registros',
                    loadingRecords: 'Cargando...',
                    processing: 'Procesando...',
                    search: 'Buscar:',
                    zeroRecords: 'No se encontraron registros coincidentes',
                    paginate: {
                        first: 'Primero',
                        last: 'Último',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    },
                    aria: {
                        sortAscending: ': activar para ordenar de manera ascendente',
                        sortDescending: ': activar para ordenar de manera descendente'
                    }
                }
            });
        }

        document.querySelectorAll('.btn-detalle').forEach(button => {
            button.addEventListener('click', function () {
                const idNR = this.getAttribute('data-id');
                fetch('get_detalle.php?id_nota_remision=' + idNR)
                    .then(r => r.json())
                    .then(data => {
                        const body = document.getElementById('detalleNRBody');
                        body.innerHTML = '';
                        if (data.error) {
                            body.innerHTML = '<tr><td colspan=\"2\" class=\"text-center text-danger\">' + data.error + '</td></tr>';
                        } else if (data.length === 0) {
                            body.innerHTML = '<tr><td colspan=\"2\" class=\"text-center\">Sin detalles.</td></tr>';
                        } else {
                            data.forEach(d => {
                                body.innerHTML += '<tr>' +
                                    '<td>' + (d.producto || '') + '</td>' +
                                    '<td>' + (d.cantidad || '') + '</td>' +
                                    '</tr>';
                            });
                        }
                        jQuery('#detalleModal').modal('show');
                    })
                    .catch(err => {
                        console.error('Error al obtener el detalle NR:', err);
                        alert('No se pudo cargar el detalle de la nota de remisión.');
                    });
            });
        });

        const btnInformes = document.getElementById('btn-informes');
        if (btnInformes) {
            btnInformes.addEventListener('click', function () {
                jQuery('#modalEstado').modal('show');
            });
        }

        // Anular Nota de Remisión con modal
        let nrIdAnular = null;
        document.querySelectorAll('.btn-anular-nr').forEach(btn => {
            btn.addEventListener('click', function () {
                nrIdAnular = this.getAttribute('data-id');
                const nrNum = this.getAttribute('data-num');
                const nrProveedor = this.getAttribute('data-proveedor');
                
                document.getElementById('anularNRInfo').textContent = 
                    'Nota de Remisión N° ' + nrNum + ' - Proveedor: ' + nrProveedor;
                document.getElementById('anularNRVinculos').style.display = 'none';
                document.getElementById('listaVinculosNR').innerHTML = '';
                document.getElementById('confirmarAnularNRBtn').style.display = 'block';
                document.getElementById('confirmarAnularNRBtn').disabled = false;
                document.getElementById('confirmarAnularNRBtn').textContent = 'Sí, anular';
                
                jQuery('#anularNRModal').modal('show');
            });
        });

        // Confirmar anulación
        const confirmarAnularNRBtn = document.getElementById('confirmarAnularNRBtn');
        if (confirmarAnularNRBtn) {
            confirmarAnularNRBtn.addEventListener('click', function () {
                if (!nrIdAnular) return;
                
                const btn = this;
                btn.disabled = true;
                btn.textContent = 'Procesando...';
                
                // Redirigir a proses.php para anular
                window.location.href = 'proses.php?act=anular_nr&id_nota_remision=' + nrIdAnular;
            });
        }
    });
    ";
    include '../../footer.php';
    ?>
