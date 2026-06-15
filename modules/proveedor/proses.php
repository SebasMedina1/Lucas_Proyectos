<?php
session_start();
require "../../config/database.php"; // Asegúrate de tener la conexión a la base de datos

if (empty($_SESSION['username'])) {
    echo "<script>
            alert('Token de sesión inválido, serás redirigido al inicio de sesión');
            window.location.href = '../../login.html';
          </script>";
    exit();
}

    // ... session_start(), require, conexión PDO ($pdo) ...

    if (isset($_GET['act']) && $_GET['act'] === 'toggle_estado' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (ob_get_length()) { ob_clean(); }
        header('Content-Type: application/json; charset=UTF-8');

        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $estado = isset($_POST['estado']) ? strtoupper(trim($_POST['estado'])) : '';

        // Normalizar variantes
        if ($estado === 'ANULAR' || $estado === 'ANULADA') { 
            $estado = 'ANULADO'; 
        }

        if (!$id || !in_array($estado, ['ACTIVO', 'ANULADO'], true)) {
            echo json_encode([
                'ok'  => false,
                'msg' => 'Parámetros inválidos',

            ]);
            exit;
        }

        try {
            $q = $pdo->prepare("UPDATE proveedor SET estado_proveedor = :estado WHERE id_proveedor = :id");
            $q->bindParam(':estado', $estado, PDO::PARAM_STR);
            $q->bindParam(':id', $id, PDO::PARAM_INT);
            $q->execute();

            echo json_encode(['ok' => true, 'estado' => $estado]); 
            exit;
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'msg' => 'DB: '.$e->getMessage()]);
            exit;
        }
    }

    function normalize_desc(string $s): string {
        $s = trim($s);                      // quita espacios al inicio/fin
        $s = preg_replace('/\s+/u', ' ', $s); // colapsa varios espacios internos a 1
        return mb_strtoupper($s, 'UTF-8');  // mayúsculas respetando UTF-8
    }

    function normalize_email(string $s): string {
        return trim($s); // NO cambiar mayúsc/minúsc; solo trim
    }

    // Verificar si existe la acción
    if (isset($_GET['act'])) {
        $action = $_GET['act'];

        try {
            // Configurar conexión PDO
            $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Agregar proveedor
            if ($action == 'insert' && isset($_POST['Guardar'])) {
                // Normalizaciones
                $id_proveedor = (int)($_POST['id_proveedor']);
                $razon_social = normalize_desc($_POST['razon_social']);
                $ruc          = normalize_desc($_POST['ruc_proveedor'] ?? '');
                $telefono     = normalize_desc($_POST['telefono_proveedor'] ?? '');
                $direccion    = normalize_desc($_POST['direccion_proveedor'] ?? '');
                $email        = normalize_email($_POST['email_proveedor'] ?? '');

                // Validaciones de duplicado
                // 1) Razón social
                $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(razon_social)=UPPER(:rs) LIMIT 1");
                $stmt->execute([':rs'=>$razon_social]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // 2) RUC
                $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(ruc_proveedor)=UPPER(:ruc) LIMIT 1");
                $stmt->execute([':ruc'=>$ruc]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // 3) Teléfono 
                $stmt = $pdo->prepare("SELECT 1 FROM proveedor WHERE UPPER(telefono_proveedor)=UPPER(:tel) LIMIT 1");
                $stmt->execute([':tel'=>$telefono]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // Obtener id_usuario de la sesión
                $usuario_id = $_SESSION['id_usuario'] ?? 1;

                // Insert
                $q = $pdo->prepare("
                    INSERT INTO proveedor
                        (id_proveedor, estado_proveedor, razon_social, ruc_proveedor, telefono_proveedor, direccion_proveedor, email_proveedor, id_usuario)
                    VALUES
                        (:id, 'ACTIVO', :rs, :ruc, :tel, :dir, :email, :id_usuario)
                ");
                $q->bindParam(':id',    $id_proveedor, PDO::PARAM_INT);
                $q->bindParam(':rs',    $razon_social, PDO::PARAM_STR);
                $q->bindParam(':ruc',   $ruc,          PDO::PARAM_STR);
                $q->bindParam(':tel',   $telefono,     PDO::PARAM_STR);
                $q->bindParam(':dir',   $direccion,    PDO::PARAM_STR);
                $q->bindParam(':email', $email,        PDO::PARAM_STR);
                $q->bindParam(':id_usuario', $usuario_id, PDO::PARAM_INT);
                $q->execute();

                header("Location: view.php?alert=1");
                exit();
            }


            // Actualizar proveedor
            elseif ($action == 'update' && isset($_POST['Guardar'])) {
                $id_proveedor = (int)($_POST['id_proveedor'] ?? 0);

                $razon_social = normalize_desc($_POST['razon_social']);
                $ruc          = normalize_desc($_POST['ruc_proveedor'] ?? '');
                $telefono     = normalize_desc($_POST['telefono_proveedor'] ?? '');
                $direccion    = normalize_desc($_POST['direccion_proveedor'] ?? '');
                $email        = normalize_email($_POST['email_proveedor'] ?? '');
                $estado       = normalize_desc($_POST['estado_proveedor'] ?? 'ACTIVO'); 

                // 1) Razón social duplicada (excluyendo el mismo proveedor)
                $stmt = $pdo->prepare("
                    SELECT 1 FROM proveedor
                    WHERE UPPER(razon_social)=UPPER(:rs) AND id_proveedor <> :id LIMIT 1
                ");
                $stmt->execute([':rs'=>$razon_social, ':id'=>$id_proveedor]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // 2) RUC duplicado
                $stmt = $pdo->prepare("
                    SELECT 1 FROM proveedor
                    WHERE UPPER(ruc_proveedor)=UPPER(:ruc) AND id_proveedor <> :id LIMIT 1
                ");
                $stmt->execute([':ruc'=>$ruc, ':id'=>$id_proveedor]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // 3) Teléfono duplicado
                $stmt = $pdo->prepare("
                    SELECT 1 FROM proveedor
                    WHERE UPPER(telefono_proveedor)=UPPER(:tel) AND id_proveedor <> :id LIMIT 1
                ");
                $stmt->execute([':tel'=>$telefono, ':id'=>$id_proveedor]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // 4) Email duplicado
                $stmt = $pdo->prepare("
                    SELECT 1 FROM proveedor
                    WHERE UPPER(email_proveedor)=UPPER(:email) AND id_proveedor <> :id LIMIT 1
                ");
                $stmt->execute([':email'=>$email, ':id'=>$id_proveedor]);
                if ($stmt->fetchColumn()) { header("Location: view.php?alert=5"); exit(); }

                // Update
                $q = $pdo->prepare("
                    UPDATE proveedor
                    SET
                        razon_social      = :rs,
                        ruc_proveedor     = :ruc,
                        telefono_proveedor= :tel,
                        direccion_proveedor= :dir,
                        email_proveedor   = :email,
                        estado_proveedor  = :estado
                    WHERE id_proveedor = :id
                ");
                $q->bindParam(':rs',     $razon_social, PDO::PARAM_STR);
                $q->bindParam(':ruc',    $ruc,          PDO::PARAM_STR);
                $q->bindParam(':tel',    $telefono,     PDO::PARAM_STR);
                $q->bindParam(':dir',    $direccion,    PDO::PARAM_STR);
                $q->bindParam(':email',  $email,        PDO::PARAM_STR);
                $q->bindParam(':estado', $estado,       PDO::PARAM_STR);
                $q->bindParam(':id',     $id_proveedor, PDO::PARAM_INT);
                $q->execute();

                header("Location: view.php?alert=2");
                exit();
            }


            /* Eliminar proveedor
            elseif ($action == 'delete' && isset($_GET['id'])) {
                $codigo = $_GET['id'];

                // Eliminar de la base de datos
                $query = $pdo->prepare("DELETE FROM proveedor WHERE cod_proveedor = :codigo");
                $query->bindParam(':codigo', $codigo, PDO::PARAM_INT);
                $query->execute();

                // Redirigir con un mensaje de éxito
                header("Location: view.php?alert=3");
            } */
        } catch (PDOException $e) {
            // En caso de error, mostrar el mensaje
            die("Error en la operación con la base de datos: " . $e->getMessage());
        }
    }

?>
