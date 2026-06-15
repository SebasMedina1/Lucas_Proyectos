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
$page_title = 'Ajustes de Stock';
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
        <?php if (!isset($_GET['ajustes']) || (($_GET['form'] ?? '') !== 'add' && ($_GET['form'] ?? '') !== 'edit')): ?>
          <!-- LISTA -->
          <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Ajustes de Stock</h1>
            <a href="?ajustes=add&form=add" class="btn btn-primary btn-sm shadow-sm">
              <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Ajuste
            </a>
          </div>

          <!-- Alertas -->
          <?php
            if (!empty($_GET['alert'])) {
              $alertMessage = ''; $alertClass = 'alert-success';
              if ($_GET['alert'] == 1) { $alertMessage = "Ajuste registrado correctamente."; }
              elseif ($_GET['alert'] == 2) { $alertMessage = "Ajuste modificado correctamente."; }
              elseif ($_GET['alert'] == 3) { $alertMessage = "Ajuste anulado correctamente."; }
              elseif ($_GET['alert'] == 4) { $alertMessage = isset($_GET['msg']) ? urldecode($_GET['msg']) : "No se pudo realizar la operación."; $alertClass = 'alert-danger'; }
              echo "<div id='alert-message' class='alert $alertClass alert-dismissible fade show' role='alert'>";
              echo htmlspecialchars($alertMessage);
              echo "<button type='button' class='close' data-dismiss='alert' aria-label='Close'>";
              echo "<span aria-hidden='true'>&times;</span>";
              echo "</button></div>";
            }
          ?>

          <!-- Tabla -->
          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Lista de Ajustes</h6>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Estado</th>
                      <th>Fecha</th>
                      <th>Depósito</th>
                      <th>Motivo</th>
                      <th>Usuario</th>
                      <th>Detalle</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      try {
                        $pdoL = new PDO($dsn, $user, $pass);
                        $pdoL->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                        $sql = "SELECT 
                                  a.id_ajuste,
                                  a.ajuste_fecha,
                                  a.ajuste_estado,
                                  d.deposito_descri,
                                  u.username,
                                  COALESCE(m.motivo_descripcion, 'N/D') AS motivo_descripcion
                                FROM ajustes a
                                JOIN deposito d ON d.deposito_id = a.deposito_id
                                JOIN usuarios u ON u.id_usuario = a.id_usuario
                                LEFT JOIN ajustes_detalle ad ON ad.id_ajuste = a.id_ajuste
                                LEFT JOIN motivo m ON m.id_motivo = ad.id_motivo
                                GROUP BY a.id_ajuste, a.ajuste_fecha, a.ajuste_estado, d.deposito_descri, u.username, m.motivo_descripcion
                                ORDER BY a.id_ajuste DESC
                                LIMIT 1000";

                        $query = $pdoL->query($sql);
                        while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
                          $estado = strtoupper(trim($data['ajuste_estado']));
                          $estadoClass = ($estado === 'EMITIDO') ? 'badge-success' : (($estado === 'ANULADO') ? 'badge-danger' : 'badge-secondary');
                          $estadoTxt = htmlspecialchars($data['ajuste_estado']);

                          echo '<tr>
                                  <td>'.htmlspecialchars($data['id_ajuste']).'</td>
                                  <td><span class="badge '.$estadoClass.'">'.$estadoTxt.'</span></td>
                                  <td>'.htmlspecialchars($data['ajuste_fecha']).'</td>
                                  <td>'.htmlspecialchars($data['deposito_descri']).'</td>
                                  <td>'.htmlspecialchars($data['motivo_descripcion']).'</td>
                                  <td>'.htmlspecialchars($data['username']).'</td>
                                  <td>
                                    <button type="button" class="btn btn-info btn-sm btn-detalle" data-id="'.htmlspecialchars($data['id_ajuste']).'">
                                      Ver Detalle
                                    </button>
                                  </td>
                                  <td>';

                          // Mostrar botón Editar solo si está EMITIDO
                          if ($estado === 'EMITIDO') {
                            echo '
                                    <a href="?ajustes=edit&form=edit&id='.htmlspecialchars($data['id_ajuste']).'" class="btn btn-primary btn-sm mb-1" title="Editar">
                                      <i class="fas fa-edit"></i>
                                    </a>';
                          }
                          
                          // Mostrar botón Anular solo si está EMITIDO
                          if ($estado === 'EMITIDO') {
                            echo '
                                    <button type="button" class="btn btn-danger btn-sm btn-anular-ajuste mb-1" 
                                            data-id="'.htmlspecialchars($data['id_ajuste']).'"
                                            data-num="'.htmlspecialchars($data['id_ajuste']).'"
                                            data-deposito="'.htmlspecialchars($data['deposito_descri']).'"
                                            title="Anular">
                                      <i class="fas fa-trash"></i>
                                    </button>';
                          }
                          
                          // Botón Reporte (siempre visible)
                          echo '
                                    <a href="reporte.php?ajuste_id='.htmlspecialchars($data['id_ajuste']).'" 
                                       target="_blank" 
                                       class="btn btn-warning btn-sm mb-1" 
                                       title="Imprimir Reporte">
                                      <i class="fas fa-print"></i>
                                    </a>';

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
            <h5 class="modal-title" id="detalleModalLabel">Detalle del Ajuste</h5>
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
              <tbody id="detalleAjusteBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

<!-- Modal de anulación -->
<div class="modal fade" id="anularAjusteModal" tabindex="-1" role="dialog" aria-labelledby="anularAjusteModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="anularAjusteModalLabel">Confirmar Anulación</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p><strong>¿Está seguro que desea anular este Ajuste?</strong></p>
        <p class="mb-0" id="anularAjusteInfo"></p>
        <div class="alert alert-warning mt-3">
          <strong>Advertencia:</strong> Se revertirá el stock ajustado.
        </div>
        <div id="anularAjusteVinculos" class="mt-3" style="display:none;">
          <div class="alert alert-danger">
            <strong>Error:</strong> Este Ajuste no puede ser anulado porque:
            <ul id="listaVinculosAjuste" class="mb-0 mt-2"></ul>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarAnularAjusteBtn">Sí, anular</button>
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
                const idAjuste = this.getAttribute('data-id');
                fetch('get_detalle.php?ajuste_id=' + idAjuste)
                    .then(r => r.json())
                    .then(data => {
                        const body = document.getElementById('detalleAjusteBody');
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
                        console.error('Error al obtener el detalle del ajuste:', err);
                        alert('No se pudo cargar el detalle del ajuste.');
                    });
            });
        });

        // Anular Ajuste con modal
        let ajusteIdAnular = null;
        document.querySelectorAll('.btn-anular-ajuste').forEach(btn => {
            btn.addEventListener('click', function () {
                ajusteIdAnular = this.getAttribute('data-id');
                const ajusteNum = this.getAttribute('data-num');
                const ajusteDeposito = this.getAttribute('data-deposito');
                
                document.getElementById('anularAjusteInfo').textContent = 
                    'Ajuste N° ' + ajusteNum + ' - Depósito: ' + ajusteDeposito;
                document.getElementById('anularAjusteVinculos').style.display = 'none';
                document.getElementById('listaVinculosAjuste').innerHTML = '';
                document.getElementById('confirmarAnularAjusteBtn').style.display = 'block';
                document.getElementById('confirmarAnularAjusteBtn').disabled = false;
                document.getElementById('confirmarAnularAjusteBtn').textContent = 'Sí, anular';
                
                jQuery('#anularAjusteModal').modal('show');
            });
        });

        // Confirmar anulación
        const confirmarAnularAjusteBtn = document.getElementById('confirmarAnularAjusteBtn');
        if (confirmarAnularAjusteBtn) {
            confirmarAnularAjusteBtn.addEventListener('click', function () {
                if (!ajusteIdAnular) return;
                
                const btn = this;
                btn.disabled = true;
                btn.textContent = 'Procesando...';
                
                // Redirigir a proses.php para anular
                window.location.href = 'proses.php?act=anular&ajuste_id=' + ajusteIdAnular;
            });
        }
    });
    ";
    include '../../footer.php';
    ?>

