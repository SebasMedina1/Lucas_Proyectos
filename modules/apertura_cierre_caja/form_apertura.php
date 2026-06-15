<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Verificar si la sesión es válida
if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_apertura']) && $_GET['form'] == 'add') { 
    require "../../config/database.php";
?>
 <div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Apertura de Caja
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Apertura y Cierre de Cajas</a></li>
        <li class="breadcrumb-item active">Nueva Apertura</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert" method="POST" id="formApertura">
                <?php
                try {
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    // Obtener número de apertura
                    $query = $pdo->query("SELECT obtener_numero_apertura() AS numero");
                    $data = $query->fetch(PDO::FETCH_ASSOC);
                    $numeroApertura = $data['numero'] ?? 1;

                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d");
                    $hora = date("H:i:s");

                    $userSesion = $_SESSION['username'];
                    $sqlUser = "
                        SELECT 
                            u.id_usuario,
                            u.username,
                            u.id_sucursal,
                            s.descripcion_sucursal
                        FROM usuarios u
                        JOIN sucursales s ON s.id_sucursal = u.id_sucursal
                        WHERE u.username = :user
                        LIMIT 1
                    ";
                    $q = $pdo->prepare($sqlUser);
                    $q->execute([':user' => $userSesion]);
                    $usr = $q->fetch(PDO::FETCH_ASSOC);

                    if (!$usr) {
                        throw new Exception('No se encontró el usuario logueado.');
                    }

                    $usuarioId      = (int)$usr['id_usuario'];
                    $usuarioNombre  = $usr['username'];
                    $sucursalId     = (int)$usr['id_sucursal'];
                    $sucursalNombre = $usr['descripcion_sucursal'];

                } catch (PDOException $e) {
                    die("Error al obtener los datos: " . $e->getMessage());
                }
                ?>
                
                <input type="hidden" name="usuario_id" value="<?= $usuarioId ?>">
                <input type="hidden" name="sucursal_id" value="<?= $sucursalId ?>">
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?= $fecha ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?= $hora ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="numero_apertura" class="form-label">N° Apertura</label>
                        <input type="text" class="form-control" id="numero_apertura" name="numero_apertura" value="<?= $numeroApertura ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" value="<?= htmlspecialchars($usuarioNombre) ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="sucursal" class="form-label">Sucursal</label>
                        <input type="text" class="form-control" id="sucursal" value="<?= htmlspecialchars($sucursalNombre) ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label for="caja" class="form-label">Caja <span class="text-danger">*</span></label>
                        <select class="form-control" id="caja" name="caja_id" required>
                            <option value="" selected>Seleccione una Caja</option>
                            <?php
                            try {
                                // Primero verificar si hay cajas en la sucursal (sin filtrar por estado)
                                $query_check = $pdo->prepare("
                                    SELECT COUNT(*) as total
                                    FROM caja 
                                    WHERE id_sucursal = :sucursal_id
                                ");
                                $query_check->execute([':sucursal_id' => $sucursalId]);
                                $totalCajas = (int)$query_check->fetchColumn();
                                
                                if ($totalCajas > 0) {
                                    // Obtener cajas de la sucursal que NO tienen una apertura activa
                                    // Verificamos en apertura_cierre_caja si hay alguna apertura ABIERTA para cada caja
                                    $query_caja = $pdo->prepare("
                                        SELECT 
                                            c.id_caja, 
                                            c.descripcion_caja, 
                                            c.estado,
                                            CASE 
                                                WHEN EXISTS (
                                                    SELECT 1 
                                                    FROM apertura_cierre_caja acc
                                                    WHERE acc.id_caja = c.id_caja
                                                      AND acc.apertura_estado = 'ABIERTA'
                                                ) THEN true
                                                ELSE false
                                            END AS tiene_apertura_activa
                                        FROM caja c
                                        WHERE c.id_sucursal = :sucursal_id
                                        ORDER BY c.descripcion_caja ASC
                                    ");
                                    $query_caja->execute([':sucursal_id' => $sucursalId]);
                                    $cajasEncontradas = false;
                                    while ($caja = $query_caja->fetch(PDO::FETCH_ASSOC)) {
                                        // Solo mostrar cajas que NO tienen una apertura activa
                                        if (!$caja['tiene_apertura_activa']) {
                                            $cajasEncontradas = true;
                                            $estadoTexto = ($caja['estado'] === 'CERRADA') ? ' (Cerrada - puede abrirse)' : '';
                                            echo "<option value=\"{$caja['id_caja']}\">{$caja['descripcion_caja']}{$estadoTexto}</option>";
                                        }
                                    }
                                    if (!$cajasEncontradas) {
                                        echo "<option value=\"\">No hay cajas disponibles (hay {$totalCajas} caja(s) pero todas tienen una apertura activa)</option>";
                                    }
                                } else {
                                    echo "<option value=\"\">No hay cajas registradas en esta sucursal</option>";
                                }
                            } catch (PDOException $e) {
                                // Mostrar el error para debugging
                                error_log("Error al cargar cajas: " . $e->getMessage());
                                echo "<option value=\"\">Error: " . htmlspecialchars($e->getMessage()) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cajero" class="form-label">Cajero <span class="text-danger">*</span></label>
                        <select class="form-control" id="cajero" name="cajero_id" required>
                            <option value="" selected>Seleccione un Cajero</option>
                            <?php
                            try {
                                // Obtener cajeros activos de la sucursal
                                $query_cajero = $pdo->prepare("
                                    SELECT 
                                        c.cajero_id,
                                        p.personal_nombre || ' ' || p.personal_apellido AS nombre_completo,
                                        c.id_personal
                                    FROM cajero c
                                    JOIN personal p ON c.id_personal = p.id_personal
                                    WHERE c.cajero_estado = 'ACTIVO'
                                      AND p.id_sucursal = :sucursal_id
                                    ORDER BY p.personal_nombre ASC, p.personal_apellido ASC
                                ");
                                $query_cajero->execute([':sucursal_id' => $sucursalId]);
                                $cajerosEncontrados = false;
                                while ($cajero = $query_cajero->fetch(PDO::FETCH_ASSOC)) {
                                    $cajerosEncontrados = true;
                                    echo "<option value=\"{$cajero['cajero_id']}\">{$cajero['nombre_completo']}</option>";
                                }
                                if (!$cajerosEncontrados) {
                                    echo "<option value=\"\">No hay cajeros activos en esta sucursal</option>";
                                }
                            } catch (PDOException $e) {
                                // Mostrar el error para debugging
                                error_log("Error al cargar cajeros: " . $e->getMessage());
                                echo "<option value=\"\">Error: " . htmlspecialchars($e->getMessage()) . "</option>";
                            }
                            ?>
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="monto_inicial" class="form-label">Monto Inicial <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="monto_inicial" name="monto_inicial" step="0.01" min="0" value="0" required>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success me-2" name="Guardar" id="btn-guardar">Guardar</button>
                    <a href="view.php" class="btn btn-warning me-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
} elseif (isset($_GET['form_apertura']) && $_GET['form'] == 'edit') { 
    require "../../config/database.php";

    $aperturaId = isset($_GET['apertura_id']) ? (int)$_GET['apertura_id'] : 0;
    if ($aperturaId <= 0) { 
        header("Location: view.php?alert=4"); 
        exit; 
    }

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Cabecera
        $cab = $pdo->prepare("
            SELECT acc.id_apertura,
                   acc.id_apertura AS numero_apertura,
                   acc.fecha_apertura,
                   acc.hora_apertura,
                   acc.monto_apertura AS monto_inicial,
                   acc.id_caja,
                   acc.cajero_id AS id_cajero,
                   c.descripcion_caja,
                   p.personal_nombre || ' ' || p.personal_apellido AS cajero_nombre,
                   s.descripcion_sucursal,
                   s.id_sucursal
            FROM apertura_cierre_caja acc
            JOIN caja c ON c.id_caja = acc.id_caja
            JOIN cajero cj ON cj.cajero_id = acc.cajero_id
            JOIN personal p ON p.id_personal = cj.id_personal
            JOIN sucursales s ON s.id_sucursal = acc.id_sucursal
            WHERE acc.id_apertura = :id
            LIMIT 1
        ");
        $cab->execute([':id'=>$aperturaId]);
        $cabecera = $cab->fetch(PDO::FETCH_ASSOC);
        if (!$cabecera) { 
            header("Location: view.php?alert=4"); 
            exit; 
        }

        date_default_timezone_set('America/Asuncion');
        $fechaHoy = date('Y-m-d');
        $horaAhora = date('H:i:s');

    } catch (PDOException $e) {
        die("Error al cargar la apertura: ".$e->getMessage());
    }
?>

<div class="container-fluid">
  <h1 class="h3 mb-4 text-gray-800"><i class="fas fa-edit"></i> Editar Apertura de Caja</h1>
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
    <li class="breadcrumb-item"><a href="view.php">Apertura y Cierre de Cajas</a></li>
    <li class="breadcrumb-item active">Editar</li>
  </ol>

  <div class="card shadow mb-4">
    <div class="card-body">
      <form action="proses.php?act=update" method="POST">
        <input type="hidden" name="apertura_id" value="<?= (int)$cabecera['id_apertura'] ?>">

        <div class="row mb-3">
          <div class="col-md-3">
            <label class="form-label">N° Apertura</label>
            <input class="form-control" value="<?= (int)$cabecera['numero_apertura'] ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Fecha</label>
            <input class="form-control" value="<?= $fechaHoy ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Hora</label>
            <input class="form-control" value="<?= $horaAhora ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label">Caja</label>
            <input class="form-control" value="<?= htmlspecialchars($cabecera['descripcion_caja']) ?>" readonly>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <label for="cajero_edit" class="form-label">Cajero</label>
            <select class="form-control" id="cajero_edit" name="cajero_id">
              <?php
              try {
                  $query_cajero = $pdo->prepare("
                      SELECT 
                          c.cajero_id,
                          p.personal_nombre || ' ' || p.personal_apellido AS nombre_completo
                      FROM cajero c
                      JOIN personal p ON c.id_personal = p.id_personal
                      WHERE c.cajero_estado = 'ACTIVO'
                        AND p.id_sucursal = :sucursal_id
                      ORDER BY p.personal_nombre ASC, p.personal_apellido ASC
                  ");
                  $query_cajero->execute([':sucursal_id' => (int)$cabecera['id_sucursal']]);
                  while ($cajero = $query_cajero->fetch(PDO::FETCH_ASSOC)) {
                      $selected = ($cajero['cajero_id'] == $cabecera['id_cajero']) ? 'selected' : '';
                      echo "<option value=\"{$cajero['cajero_id']}\" {$selected}>{$cajero['nombre_completo']}</option>";
                  }
              } catch (PDOException $e) {
                  die("Error al cargar cajeros: " . $e->getMessage());
              }
              ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="monto_inicial_edit" class="form-label">Monto Inicial</label>
            <input type="number" class="form-control" id="monto_inicial_edit" name="monto_inicial" step="0.01" min="0" value="<?= $cabecera['monto_inicial'] ?>" required>
          </div>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-success me-2" name="Guardar">Guardar cambios</button>
          <a href="view.php" class="btn btn-warning">Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php } ?>

