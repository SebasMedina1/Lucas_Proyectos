<?php 
// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['form_pedido_compra']) && $_GET['form'] == 'add') { ?>
 <div class="container-fluid">
    <!-- Encabezado de página -->
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Pedido
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Pedidos de Compras</a></li>
        <li class="breadcrumb-item active">Nuevo Pedido</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="proses.php?act=insert" method="POST">
                <!-- Información general -->
                <?php
                            try {
                                require "../../config/database.php";

                                // Crear conexión con PostgreSQL usando PDO
                                $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                                $pdo = new PDO($dsn, $user, $pass);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                // Obtener el máximo valor de cod_compra
                                $query = $pdo->query("SELECT MAX(pedido_id) AS id FROM pedidos_compras");
                                $data = $query->fetch(PDO::FETCH_ASSOC);

                                // Generar nuevo código incrementado
                                $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;

                                date_default_timezone_set('America/Asuncion');
                                // Obtener fecha y hora actuales
                                $fecha = date("Y-m-d"); // Formato: YYYY-MM-DD (año:mes:dia)
                                $hora = date("H:i A"); // Formato: hh:mm:ss AM/PM (hora:minutos)

                            } catch (PDOException $e) {
                                // Manejar errores de conexión o consulta
                                die("Error al obtener los datos: " . $e->getMessage());
                            }
                            ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="fecha" name="fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="codigo" class="form-label">Pedido N°</label>
                        <input type="text" class="form-control" id="codigo" name="codigo" value="<?php echo $codigo; ?>" readonly>
                    </div>
                </div>

                <!-- Detalle -->
                <div class="row align-items-end mb-3">
                    <div class="col-md-6">
                        <label for="producto" class="form-label">Producto</label>
                        
                        <select class="form-control select2" id="producto" name="producto" >
                            <option value="" selected>Seleccione un Producto</option>
                            <?php
                            try {
                                $query_dep = $pdo->query("SELECT cod_producto, p_descrip FROM producto ORDER BY cod_producto ASC");
                                while ($data_dep = $query_dep->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"{$data_dep['cod_producto']}\">{$data_dep['p_descrip']}</option>";
                                }
                            } catch (PDOException $e) {
                                die("Error en la conexión o consulta: " . $e->getMessage());
                            }
                            ?>
                        </select>


                    </div>
                    
                        <!-- -->



                    <div class="col-md-4">
                        <label for="cantidad_producto" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_producto" min="1" placeholder="Ingrese cantidad" >
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-primary w-100" id="btn-agregar">Agregar</button>
                    </div>
                </div>

                <!-- Tabla de productos -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-productos">
                        <thead class="table-dark">
                            <tr>
                                <th>#</th>
                                <th>Código</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">

                <!-- Botones -->
                <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success me-2" name="Guardar">Guardar</button>
                    <a href="view.php" class="btn btn-warning me-2">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    const productos = [];
    const tablaProductos = document.getElementById('tabla-productos').querySelector('tbody');

    document.getElementById('btn-agregar').addEventListener('click', function () {
        const producto = document.getElementById('producto');
        const cantidadProducto = document.getElementById('cantidad_producto').value;

        if (producto.value && cantidadProducto) {
            // Agregar fila a la tabla
            const row = `
                <tr>
                    <td>${productos.length + 1}</td>
                    <td>${producto.value}</td>
                    <td>${producto.options[producto.selectedIndex].text}</td>
                    <td>${cantidadProducto}</td>
                    <td><button type="button" class="btn btn-danger btn-sm btn-eliminar">Quitar</button></td>
                </tr>`;
            tablaProductos.innerHTML += row;

            // Agregar producto al arreglo
            productos.push({ codigo: producto.value, cantidad: cantidadProducto });
            document.getElementById('productos').value = JSON.stringify(productos);

            // Limpiar campos
            producto.value = '';
            document.getElementById('cantidad_producto').value = '';
        } else {
            alert("Por favor, seleccione un producto y especifique una cantidad válida.");
        }
    });

    // Eliminar producto de la tabla y del arreglo
    tablaProductos.addEventListener('click', function (event) {
        if (event.target.classList.contains('btn-eliminar')) {
            const row = event.target.closest('tr');
            const index = Array.from(tablaProductos.children).indexOf(row);

            // Quitar el producto del arreglo
            productos.splice(index, 1);
            row.remove();

            // Actualizar el índice de la tabla
            Array.from(tablaProductos.children).forEach((row, idx) => {
                row.children[0].innerText = idx + 1;
            });

            document.getElementById('productos').value = JSON.stringify(productos);
        }
    });

    // vaidación para el boton agregar
    const btnAgregar = document.getElementById('btn-agregar');
    const cantidadProductoInput = document.getElementById('cantidad_producto');

    // Validar que la cantidad no sea menor a 1
    cantidadProductoInput.addEventListener('input', function () {
        const cantidad = parseInt(this.value);
        if (isNaN(cantidad) || cantidad < 1) {
            btnAgregar.disabled = true;
        } else {
            btnAgregar.disabled = false;
        }
    });

    // Validación en el evento click del botón "Agregar"
    btnAgregar.addEventListener('click', function () {
        const producto = document.getElementById('producto');
        const cantidadProducto = parseInt(cantidadProductoInput.value);

        if (producto.value && cantidadProducto >= 1) {
            // Agregar fila a la tabla
            const row = `
                <tr>
                    <td>${productos.length + 1}</td>
                    <td>${producto.value}</td>
                    <td>${producto.options[producto.selectedIndex].text}</td>
                    <td>${cantidadProducto}</td>
                    <td><button type="button" class="btn btn-danger btn-sm btn-eliminar">Quitar</button></td>
                </tr>`;
            tablaProductos.innerHTML += row;

            // Agregar producto al arreglo
            productos.push({ codigo: producto.value, cantidad: cantidadProducto });
            document.getElementById('productos').value = JSON.stringify(productos);

            // Limpiar campos
            producto.value = '';
            cantidadProductoInput.value = '';
            btnAgregar.disabled = true; // Deshabilitar el botón después de agregar
        } 
    });

</script>


<?php } ?>