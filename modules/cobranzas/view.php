<?php
session_start();
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

$configPath = realpath("../../config/database.php");
if (!$configPath || !file_exists($configPath)) {
    die("Error: No se pudo encontrar el archivo de configuración.");
}
require_once $configPath;

// Validar permisos de acceso
require_once realpath("../../config/permissions.php");
check_permission('COBRANZAS');

$username = $_SESSION['username'];

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $query = $pdo->prepare("SELECT * FROM usuarios WHERE username = :username");
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();
    $auth_user = $query->fetch();
    if (!$auth_user) {
        session_destroy();
        echo "<script>
                alert('Usuario no encontrado, serás redirigido al inicio de sesión');
                window.location.href = '../../login.html';
              </script>";
        exit();
    }
    $permisoAcceso = (int)($auth_user['id_cargo'] ?? 0);
} catch (PDOException $e) {
    die("Error en la conexión a la base de datos: " . $e->getMessage());
}

$mostrarListado = !(
    isset($_GET['cobranzas']) &&
    $_GET['form'] === 'add'
);

// Configuración para el layout común
$BASE_PATH = '../../';
$page_title = 'Gestionar Cobranzas';
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

<!-- Modal para ver detalle del cobro -->
<div class="modal fade" id="detalleCobroModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Detalle del Cobro</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="detalleCobroContent">
        <p class="text-center">Cargando...</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal confirmación de anulación -->
<div class="modal fade" id="confirmAnularModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirmar Anulación</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p>¿Está seguro que desea anular este cobro?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmAnularBtn">Anular</button>
      </div>
    </div>
  </div>
</div>

<!-- Contenido específico del módulo -->
<?php if ($mostrarListado): ?>
  <div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Gestionar Cobranzas</h1>
    <div>
      <a href="?cobranzas=add&form=add" class="btn btn-primary btn-sm shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Cobro
      </a>
    </div>
  </div>
  <?php
  if (!empty($_GET['alert'])) {
      $alertMap = [
          1 => ['msg'=>'Cobro registrado correctamente.','class'=>'alert-success'],
          2 => ['msg'=>'Cobro anulado correctamente.','class'=>'alert-success'],
          3 => ['msg'=>'No se pudo realizar la operación.','class'=>'alert-danger'],
          4 => ['msg'=>'No hay caja abierta en la sucursal.','class'=>'alert-danger'],
          5 => ['msg'=>'El cobro no puede ser anulado (ya tiene movimientos derivados o no es del día actual).','class'=>'alert-danger'],
      ];
      if (isset($alertMap[$_GET['alert']])) {
          $data = $alertMap[$_GET['alert']];
          echo "<div id='alert-message' class='alert {$data['class']} alert-dismissible fade show' role='alert'>
                  {$data['msg']}
                  <button type='button' class='close' data-dismiss='alert' aria-label='Close'>
                    <span aria-hidden='true'>&times;</span>
                  </button>
                </div>";
      }
  }
  ?>

  <div class="card shadow mb-4">
    <div class="card-header py-3">
      <h6 class="m-0 font-weight-bold text-primary">Lista de Cobros</h6>
    </div>
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
          <thead>
            <tr>
              <th>N° Recibo</th>
              <th>Fecha</th>
              <th>Cliente</th>
              <th>Total Cobrado</th>
              <th>Estado</th>
              <th>Usuario</th>
              <th>Detalle</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php
            try {
                $sql = "
                  SELECT 
                      c.id_cobro,
                      c.numero_recibo,
                      c.fecha_cobro,
                      c.hora_cobro,
                      c.total_cobrado,
                      c.estado,
                      cl.cliente_nombre || ' ' || cl.cliente_apellido AS cliente_nombre,
                      u.username
                  FROM cobros c
                  JOIN clientes cl ON cl.id_cliente = c.id_cliente
                  JOIN usuarios u ON u.id_usuario = c.id_usuario
                  ORDER BY c.id_cobro DESC
                ";
                foreach ($pdo->query($sql) as $data) {
                    $estadoClass = '';
                    $estado = strtoupper(trim($data['estado']));
                    if ($estado === 'REGISTRADO') $estadoClass = 'badge-success';
                    elseif ($estado === 'ANULADO') $estadoClass = 'badge-danger';
                    else $estadoClass = 'badge-warning';
                    
                    $idCobro = (int)$data['id_cobro'];
                    $fecha = date('d/m/Y', strtotime($data['fecha_cobro']));
                    $hora = substr($data['hora_cobro'], 0, 5);
                    $total = number_format((float)$data['total_cobrado'], 0, ',', '.');
                    
                    echo "<tr>
                            <td>{$data['numero_recibo']}</td>
                            <td>{$fecha} {$hora}</td>
                            <td>{$data['cliente_nombre']}</td>
                            <td>" . number_format((float)$data['total_cobrado'], 0, ',', '.') . " Gs</td>
                            <td><span class='badge {$estadoClass}'>{$estado}</span></td>
                            <td>{$data['username']}</td>
                            <td>
                              <button type='button' class='btn btn-info btn-sm' onclick='verDetalle({$idCobro})'>
                                <i class='fas fa-eye'></i> Ver
                              </button>
                            </td>
                            <td>";
                    
                    // Solo mostrar acciones si está REGISTRADO y es del día actual
                    $hoy = date('Y-m-d');
                    if ($estado === 'REGISTRADO' && $data['fecha_cobro'] === $hoy) {
                        echo "<a href='reporte.php?id={$idCobro}' target='_blank' class='btn btn-primary btn-sm' title='Imprimir'>
                                <i class='fas fa-print'></i>
                              </a>
                              <button type='button' class='btn btn-danger btn-sm' onclick='anularCobro({$idCobro})' title='Anular'>
                                <i class='fas fa-times'></i>
                              </button>";
                    } else {
                        echo "<a href='reporte.php?id={$idCobro}' target='_blank' class='btn btn-primary btn-sm' title='Imprimir'>
                                <i class='fas fa-print'></i>
                              </a>";
                    }
                    
                    echo "</td>
                          </tr>";
                }
            } catch (PDOException $e) {
                echo "<tr><td colspan='8' class='text-center text-danger'>Error al consultar los datos: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
  function verDetalle(idCobro) {
      const modalEl = document.getElementById('detalleCobroModal');
      const contentEl = document.getElementById('detalleCobroContent');
      if (contentEl) contentEl.innerHTML = '<p class="text-center">Cargando...</p>';
      
      if (modalEl) {
          if (typeof bootstrap !== 'undefined') {
              const bsModal = new bootstrap.Modal(modalEl);
              bsModal.show();
          } else if (typeof $ !== 'undefined') {
              $('#detalleCobroModal').modal('show');
          }
      }
      
      fetch(`get_detalle.php?id=${idCobro}`)
          .then(response => response.json())
          .then(data => {
              if (data.success) {
                  let html = '<div class="table-responsive">';
                  html += '<table class="table table-bordered">';
                  html += '<tr><th>N° Recibo:</th><td>' + data.cobro.numero_recibo + '</td></tr>';
                  html += '<tr><th>Fecha:</th><td>' + data.cobro.fecha_cobro + ' ' + data.cobro.hora_cobro + '</td></tr>';
                  html += '<tr><th>Cliente:</th><td>' + data.cobro.cliente_nombre + '</td></tr>';
                  html += '<tr><th>Total Cobrado:</th><td>' + parseFloat(data.cobro.total_cobrado).toLocaleString('es-PY') + ' Gs</td></tr>';
                  if (data.cobro.vuelto > 0) {
                      html += '<tr><th>Vuelto:</th><td>' + parseFloat(data.cobro.vuelto).toLocaleString('es-PY') + ' Gs</td></tr>';
                  }
                  html += '</table>';
                  
                  if (data.detalle && data.detalle.length > 0) {
                      html += '<h6 class="mt-3">Facturas Cobradas:</h6>';
                      html += '<table class="table table-sm table-bordered">';
                      html += '<thead><tr><th>N° Factura</th><th>Tipo Pago</th><th>Importe</th></tr></thead>';
                      html += '<tbody>';
                      data.detalle.forEach(item => {
                          html += '<tr>';
                          html += '<td>' + item.numero_factura + '</td>';
                          html += '<td>' + item.tipo_pago + '</td>';
                          html += '<td>' + parseFloat(item.importe_aplicado).toLocaleString('es-PY') + ' Gs</td>';
                          html += '</tr>';
                      });
                      html += '</tbody></table>';
                  }
                  
                  html += '</div>';
                  if (contentEl) contentEl.innerHTML = html;
              } else {
                  if (contentEl) contentEl.innerHTML = '<p class="text-danger">Error: ' + (data.message || 'No se pudo cargar el detalle') + '</p>';
              }
          })
          .catch(error => {
              console.error('Error:', error);
              if (contentEl) contentEl.innerHTML = '<p class="text-danger">Error al cargar el detalle</p>';
          });
  }
  
  let cobroAnularId = null;
  function anularCobro(idCobro) {
      cobroAnularId = idCobro;
      // Usar jQuery para mostrar el modal (Bootstrap 4)
      if (typeof $ !== 'undefined') {
          $('#confirmAnularModal').modal('show');
      } else {
          // Fallback: mostrar manualmente si jQuery no está disponible
          const modal = document.getElementById('confirmAnularModal');
          if (modal) {
              modal.style.display = 'block';
              modal.classList.add('show');
              document.body.classList.add('modal-open');
              // Crear backdrop si no existe
              if (!document.querySelector('.modal-backdrop')) {
                  const backdrop = document.createElement('div');
                  backdrop.className = 'modal-backdrop fade show';
                  document.body.appendChild(backdrop);
              }
          }
      }
  }
  
  // Vincular evento del botón de confirmar anulación usando JavaScript vanilla
  // Se ejecuta cuando el DOM está listo
  document.addEventListener('DOMContentLoaded', function() {
      const confirmBtn = document.getElementById('confirmAnularBtn');
      if (confirmBtn) {
          confirmBtn.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();
              
              if (!cobroAnularId) {
                  alert('Error: No se pudo identificar el cobro a anular');
                  return false;
              }
              
              // Deshabilitar botón para evitar doble clic
              const btn = this;
              btn.disabled = true;
              btn.textContent = 'Anulando...';
              
              const formData = new FormData();
              formData.append('id_cobro', cobroAnularId);
              
              fetch('anular_cobro.php', {
                  method: 'POST',
                  body: formData
              })
              .then(response => {
                  if (!response.ok) {
                      throw new Error('Error en la respuesta del servidor');
                  }
                  return response.json();
              })
              .then(data => {
                  if (data.success) {
                      // Cerrar modal usando jQuery (Bootstrap 4)
                      if (typeof $ !== 'undefined') {
                          $('#confirmAnularModal').modal('hide');
                      } else {
                          // Fallback: cerrar manualmente
                          const modal = document.getElementById('confirmAnularModal');
                          if (modal) {
                              modal.style.display = 'none';
                              modal.classList.remove('show');
                              document.body.classList.remove('modal-open');
                              const backdrop = document.querySelector('.modal-backdrop');
                              if (backdrop) backdrop.remove();
                          }
                      }
                      window.location.href = 'view.php?alert=2';
                  } else {
                      alert('Error: ' + (data.message || 'No se pudo anular el cobro'));
                      btn.disabled = false;
                      btn.textContent = 'Anular';
                  }
              })
              .catch(error => {
                  console.error('Error:', error);
                  alert('Error al anular el cobro: ' + error.message);
                  btn.disabled = false;
                  btn.textContent = 'Anular';
              });
              
              return false;
          });
      }
      
      // Inicializar DataTable cuando jQuery esté disponible
      function initDataTable() {
          if (typeof $ !== 'undefined') {
              $('#dataTable').DataTable({
                  "language": {
                      "url": "<?= $BASE_PATH ?>vendor/datatables/Spanish.json"
                  },
                  "order": [[0, "desc"]]
              });
          } else {
              setTimeout(initDataTable, 100);
          }
      }
      initDataTable();
  });
  </script>

<?php else: ?>
  <?php include 'form.php'; ?>
<?php endif; ?>


<?php include '../../footer.php'; ?>

