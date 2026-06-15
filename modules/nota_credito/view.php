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
$page_title = 'Notas (Crédito / Débito)';
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
        <?php if (!isset($_GET['nueva_nota']) || ($_GET['form'] ?? '') !== 'add'): ?>
          <!-- LISTA -->
          <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Notas (Crédito / Débito)</h1>
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
              elseif ($_GET['alert'] == 5) { $alertMessage = "La nota seleccionada ya está anulada."; $alertClass = 'alert-danger'; }
              elseif ($_GET['alert'] == 6) { $alertMessage = "La suma de las Notas de Crédito excede el total de la factura."; $alertClass = 'alert-danger'; }
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
              <h6 class="m-0 font-weight-bold text-primary">Lista de Notas</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                  <thead>
                    <tr>
                      <th>Código</th>
                      <th>Estado</th>
                      <th>Proveedor</th>
                      <th>Tipo</th>
                      <th>Monto Nota</th>
                      <th>Fecha</th>
                      <th>Detalle</th>
                      <th>Usuario</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      try {
                        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                        $pdo = new PDO($dsn, $user, $pass);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "SELECT 
                                  nc.id_nota_compra, 
                                  nc.nota_compra_estado,
                                  pv.razon_social,
                                  nc.nota_compra_tipo,
                                  nc.nota_total,
                                  nc.nota_compra_fecha,
                                  nc.nota_nro,
                                  u.username
                                FROM nota_compra nc
                                JOIN usuarios u ON nc.id_usuario = u.id_usuario
                                JOIN proveedor pv ON nc.id_proveedor = pv.id_proveedor
                                ORDER BY nc.id_nota_compra DESC";
                        $query = $pdo->query($sql);

                        while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                          echo '<tr>
                                  <td>'.htmlspecialchars($data['id_nota_compra']).'</td>
                                  <td>'.htmlspecialchars($data['nota_compra_estado']).'</td>
                                  <td>'.htmlspecialchars($data['razon_social']).'</td>
                                  <td>'.htmlspecialchars($data['nota_compra_tipo']).'</td>
                                  <td>'.htmlspecialchars($data['nota_total']).'</td>
                                  <td>'.htmlspecialchars($data['nota_compra_fecha']).'</td>
                                  <td>
                                    <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="'.htmlspecialchars($data['id_nota_compra']).'">
                                      Ver Detalle
                                    </button>
                                  </td>
                                  <td>'.htmlspecialchars($data['username']).'</td>
                                  <td>
                                    <a href="reporte.php?nota_id='.htmlspecialchars($data['id_nota_compra']).'" target="_blank" class="btn btn-warning btn-sm">
                                      <i class="fas fa-print"></i>
                                    </a>
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
                      <option value="PROCESADO">PROCESADO</option>
                      <option value="ANULADA">ANULADA</option>
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
            <h5 class="modal-title" id="detalleModalLabel">Detalle de la nota</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Número Factura</th>
                  <th>Producto</th>
                  <th>Precio</th>
                  <th>Cantidad</th>
                  <th>Sub total</th>
                </tr>
              </thead>
              <tbody id="detallePedidoBody"></tbody>
            </table>
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
                const idNota = this.getAttribute('data-id');
                fetch('get_detalle.php?id_nota_compra=' + idNota)
                    .then(r => r.json())
                    .then(data => {
                        const body = document.getElementById('detallePedidoBody');
                        body.innerHTML = '';
                        if (data.error) {
                            body.innerHTML = '<tr><td colspan=\"5\" class=\"text-center text-danger\">' + data.error + '</td></tr>';
                        } else if (data.length === 0) {
                            body.innerHTML = '<tr><td colspan=\"5\" class=\"text-center\">Sin detalles.</td></tr>';
                        } else {
                            data.forEach(d => {
                                body.innerHTML += '<tr>' +
                                    '<td>' + (d.factura || '') + '</td>' +
                                    '<td>' + (d.producto || '') + '</td>' +
                                    '<td>' + (d.precio || '') + '</td>' +
                                    '<td>' + (d.cantidad || '') + '</td>' +
                                    '<td>' + (d.subtotal || '') + '</td>' +
                                    '</tr>';
                            });
                        }
                        jQuery('#detalleModal').modal('show');
                    })
                    .catch(err => {
                        console.error('Error al obtener el detalle:', err);
                        alert('No se pudo cargar el detalle de la nota.');
                    });
            });
        });

        const btnInformes = document.getElementById('btn-informes');
        if (btnInformes) {
            btnInformes.addEventListener('click', function () {
                jQuery('#modalEstado').modal('show');
            });
        }
    });
    ";
    include '../../footer.php';
    ?>
