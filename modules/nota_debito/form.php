<?php

// Verificar si existe el parámetro 'form' en la URL
if (isset($_GET['gestionar_compras']) && $_GET['form'] == 'add') { ?>
<div class="container-fluid">
    
    <h1 class="h3 mb-4 text-gray-800">
        <i class="fas fa-plus-circle"></i> Registrar Nota de Débito
    </h1>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="../../index.php">Inicio</a></li>
        <li class="breadcrumb-item"><a href="view.php">Notas de Débito</a></li>
        <li class="breadcrumb-item active">Nueva Nota de Débito</li>
    </ol>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form id="form-nota-credito" action="proses.php?act=insert_nota_credito" method="POST">
                <?php
                try {
                    require "../../config/database.php";
                    $dsn = "pgsql:host=$host;port=$port;dbname=$database;";
                    $pdo = new PDO($dsn, $user, $pass);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                    $query = $pdo->query("SELECT MAX(nota_debito_id) AS id FROM nota_debito");
                    $data = $query->fetch(PDO::FETCH_ASSOC);

                    $codigo = ($data['id'] !== null) ? $data['id'] + 1 : 1;
                    date_default_timezone_set('America/Asuncion');
                    $fecha = date("Y-m-d"); // Formato: YYYY-MM-DD (año:mes:dia)
                    $hora = date("H:i "); // Formato: hh:mm:ss AM/PM (hora:minutos)
                } catch (PDOException $e) {
                    die("Error: " . $e->getMessage());
                }
                ?>
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label for="nota_fecha" class="form-label">Fecha</label>
                        <input type="text" class="form-control" id="nota_fecha" name="nota_fecha" value="<?php echo $fecha; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="hora" class="form-label">Hora</label>
                        <input type="text" class="form-control" id="hora" name="hora" value="<?php echo $hora; ?>" readonly>
                    </div>

                    <div class="col-md-3">
                        <label for="nota_id" class="form-label">Nota ID</label>
                        <input type="text" class="form-control" id="nota_id" name="nota_id" value="<?php echo $codigo; ?>" readonly>
                    </div>
                </div>

                <div class="row mb-3">

                    <div class="col-md-4">
                        <label for="fact_id" class="form-label">Factura</label>
                        <select class="form-control" id="fact_id" name="fact_id" required>
                            <option value="" selected>Seleccione una factura</option>
                            <?php
                            $query_facturas = $pdo->query("SELECT fact_id, fact_nro FROM facturas_compra WHERE fact_estado = 'FINALIZADO' ORDER BY fact_id ASC");
                            while ($factura = $query_facturas->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$factura['fact_id']}\">Factura N° {$factura['fact_nro']}</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="hidden_fact_id" name="fact_id">
                    </div>

                    <div class="col-md-4">
                        <label for="motivo_id" class="form-label">Motivo</label>
                        <select class="form-control" id="motivo_id" name="motivo_id" required>
                            <option value="" selected>Seleccione un motivo</option>
                            <?php
                            $query_motivos = $pdo->query("SELECT motivo_id, motivo_descripcion FROM motivo_debito ORDER BY motivo_id ASC");
                            while ($motivo = $query_motivos->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value=\"{$motivo['motivo_id']}\">{$motivo['motivo_descripcion']}</option>";
                            }
                            ?>
                        </select>
                        <input type="hidden" id="hidden_motivo_id" name="motivo_id">
                    </div>

                    <div class="col-md-4">
                        <label for="nota_cargo" class="form-label">Cargo adicional</label>
                        <input type="text" class="form-control" id="nota_cargo" name="nota_cargo"
                            placeholder="Ingrese el monto del cargo adicional." required>
                    </div>



                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="nota_nro" class="form-label">Número de Nota</label>
                        <input type="text" class="form-control" id="nota_nro" name="nota_nro"
                            placeholder="Ingrese el número de la nota" required>
                    </div>

                </div>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="nota_inicio" class="form-label">Fecha de Inicio</label>
                        <input type="date" class="form-control" id="nota_inicio" name="nota_inicio" required>
                    </div>
                    <div class="col-md-4">
                        <label for="nota_vto" class="form-label">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="nota_vto" name="nota_vto" required>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-striped" id="tabla-factura-detalles">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio</th>
                                <th>Subtotal</th>
                                <th>Monoto IVA</th>
                                <th>IVA</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Los detalles se cargarán dinámicamente -->
                        </tbody>
                    </table>
                </div>

                <input type="hidden" name="productos" id="productos">

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="nota_total" class="form-label">Monto Total</label>
                        <input type="number" class="form-control" id="nota_total" name="nota_total" readonly>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-success mx-2" id="btn-guardar">Guardar</button>
                    <a href="../../modules/nota_debito/view.php" class="btn btn-danger mx-2" id="cancelar">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Script para manejar la carga de datos de la factura -->
<script>
// Evento para cargar los detalles de la factura al seleccionar una factura
document.getElementById('fact_id').addEventListener('change', async function () {
    const facturaId = this.value;

    if (!facturaId) return;

    try {
        const response = await fetch(`get_pedido_detalle.php?fact_id=${facturaId}`);
        const detalles = await response.json();

        const tbody = document.querySelector('#tabla-factura-detalles tbody');
        tbody.innerHTML = ''; // Limpiar la tabla antes de cargar nuevos detalles

        let total = 0;

        detalles.forEach(detalle => {
            // Calcular el subtotal y el monto IVA
            const subtotal = detalle.cantidad * detalle.precio;
            let montoIva = 0;

            if (detalle.iva === 10) {
                montoIva = Math.floor(detalle.precio / 11);
            } else if (detalle.iva === 5) {
                montoIva = Math.floor(detalle.precio / 21);
            }

            const totalIva = montoIva * detalle.cantidad;

            // Crear la fila de la tabla con los valores calculados
            const row = `
                <tr>
                    <td>${detalle.producto}</td>
                    <td>
                        <input type="number" class="form-control cantidad" 
                            value="${detalle.cantidad}" 
                            data-original="${detalle.cantidad}" 
                            data-codigo="${detalle.codigo}" />
                    </td>
                    <td>
                        <input type="number" class="form-control precio" 
                            value="${detalle.precio}" 
                            data-codigo="${detalle.codigo}" readonly />
                    </td>
                    <td class="subtotal">${subtotal.toFixed(2)}</td>
                    <td class="monto-iva">${totalIva.toFixed(2)}</td>
                    <td>${detalle.iva} %</td>
                    <td><button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button></td>
                </tr>`;
            tbody.insertAdjacentHTML('beforeend', row);
        });

        // Calcular el total general con IVA
        calcularTotales();

        // Deshabilitar el campo de factura y habilitar el siguiente campo
        this.setAttribute('disabled', 'disabled'); // Deshabilitar fact_id
        document.getElementById('motivo_id').removeAttribute('disabled'); // Habilitar motivo_id
        document.getElementById('motivo_id').focus(); // Enfocar motivo_id
    } catch (error) {
        console.error('Error al cargar detalles de la factura:', error);
        alert('Ocurrió un error al intentar cargar los detalles de la factura.');
    }
});



// Validar cantidades y habilitar o deshabilitar el botón "Guardar"
async function validarCantidadYHabilitarBoton() {
    const motivoField = document.getElementById('motivo_id');
    const botonGuardar = document.getElementById('btn-guardar');

    // Si el motivo no es "Devolución de productos", habilitar directamente el botón
    if (motivoField.value !== '1') {
        botonGuardar.removeAttribute('disabled');
        return;
    }

    // Obtener el ID de la factura seleccionada
    const facturaId = document.getElementById('fact_id').value;

    if (!facturaId) {
        botonGuardar.setAttribute('disabled', 'disabled');
        return;
    }

    try {
        // Llamar al archivo `get_pedido_detalle.php` para obtener las cantidades originales
        const response = await fetch(`get_pedido_detalle.php?fact_id=${facturaId}`);
        const detalles = await response.json();

        let esValido = true;

        // Comparar las cantidades ingresadas con las originales
        document.querySelectorAll('#tabla-factura-detalles tbody tr').forEach(row => {
            const cantidadIngresada = parseFloat(row.querySelector('.cantidad').value) || 0;
            const codigoProducto = row.querySelector('.cantidad').dataset.codigo;

            // Buscar el producto en los detalles originales
            const detalleOriginal = detalles.find(det => det.codigo == codigoProducto);
            const cantidadOriginal = detalleOriginal ? detalleOriginal.cantidad : 0;

            // Verificar que la cantidad ingresada esté en el rango permitido
            if (cantidadIngresada < 1 || cantidadIngresada >= cantidadOriginal) {
                esValido = false;
            }
        });

        // Habilitar o deshabilitar el botón según la validación
        if (esValido) {
            botonGuardar.removeAttribute('disabled');
        } else {
            botonGuardar.setAttribute('disabled', 'disabled');
        }
    } catch (error) {
        console.error('Error al validar cantidades:', error);
        botonGuardar.setAttribute('disabled', 'disabled');
    }
}




// Función para recalcular los totales
function calcularTotales() {
    let total = 0;

    // Recorre cada fila de la tabla para calcular los valores
    document.querySelectorAll('#tabla-factura-detalles tbody tr').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(row.querySelector('.precio').value) || 0;
        const tipoIva = parseFloat(row.children[5].textContent.replace('%', '')) || 0;

        let montoIva = 0;

        // Calcular el monto del IVA según el tipo de IVA
        if (tipoIva === 10) {
            montoIva = Math.floor(precio / 11);
        } else if (tipoIva === 5) {
            montoIva = Math.floor(precio / 21);
        }

        // Calcular Subtotal y Monto IVA total
        const subtotal = cantidad * precio;
        const totalIva = montoIva * cantidad;

        // Actualizar los valores en la fila
        row.querySelector('.subtotal').textContent = subtotal.toFixed(2);
        row.querySelector('.monto-iva').textContent = totalIva.toFixed(2);

        // Sumar al total general
        total += subtotal + totalIva;
    });

    // Actualizar el total general en el formulario
    document.getElementById('nota_total').value = total.toFixed(2);
}



// Manejar cambios en cantidad y precio
document.querySelector('#tabla-factura-detalles').addEventListener('input', function (e) {
    if (e.target.classList.contains('cantidad') || e.target.classList.contains('precio')) {
        calcularTotales();
        validarCantidadYHabilitarBoton();
    }
});

// Manejar la eliminación de filas
document.querySelector('#tabla-factura-detalles').addEventListener('click', function (e) {
    if (e.target.classList.contains('btn-quitar')) {
        const row = e.target.closest('tr');
        row.remove(); // Eliminar la fila de la tabla

        // Recalcular totales
        calcularTotales();

        // Limpiar los campos si no hay más filas en la tabla
        const filasRestantes = document.querySelectorAll('#tabla-factura-detalles tbody tr').length;
        if (filasRestantes === 0) {
            limpiarCamposFormulario();
            // Volver a habilitar el campo "Factura" y enfocar
            const factIdField = document.getElementById('fact_id');
            factIdField.removeAttribute('disabled'); // Habilitar el campo de factura
            factIdField.focus(); // Llevar el foco al campo de factura 


            // Deshabilitar el campo "Motivo"
            const motivoIdField = document.getElementById('motivo_id');
            motivoIdField.setAttribute('disabled', 'disabled'); // Deshabilitar el combo "Motivo"
        }
    }
});

// Función para limpiar los campos del formulario
function limpiarCamposFormulario() {
    // Limpiar select de factura
    const factIdField = document.getElementById('fact_id');
    factIdField.value = "";
    factIdField.removeAttribute('disabled'); // Habilitar el campo de factura
    factIdField.focus(); // Enfocar el campo de factura

    // Limpiar select de motivo
    document.getElementById('motivo_id').value = "";
    document.getElementById('motivo_id').setAttribute('disabled', 'disabled'); // Deshabilitar el combo "Motivo"

    // Limpiar campos de texto
    document.getElementById('nota_cargo').value = "";
    document.getElementById('nota_nro').value = "";
    document.getElementById('nota_inicio').value = "";
    document.getElementById('nota_vto').value = "";
    document.getElementById('nota_total').value = "0.00";

    // Deshabilitar los campos
    disableAllFields();
}

// Función para deshabilitar todos los campos excepto la factura
function disableAllFields() {
    document.getElementById('motivo_id').setAttribute('disabled', 'disabled');
    document.getElementById('nota_cargo').setAttribute('readonly', 'readonly');
    document.getElementById('nota_nro').setAttribute('readonly', 'readonly');
    document.getElementById('nota_timbrado').setAttribute('readonly', 'readonly');
    document.getElementById('nota_inicio').setAttribute('readonly', 'readonly');
    document.getElementById('nota_vto').setAttribute('readonly', 'readonly');
    document.getElementById('btn-guardar').setAttribute('readonly', 'readonly');
}

// Función para habilitar la selección de factura
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('disabled').removeAttribute('disabled');
    document.getElementById('motivo_id').focus();
});





// Validar antes de enviar el formulario
document.getElementById('form-nota-credito').addEventListener('submit', function (e) {
    const detalles = [];
    let cantidadInvalida = false; // Variable para rastrear si hay alguna cantidad inválida

    document.querySelectorAll('#tabla-factura-detalles tbody tr').forEach(row => {
        const producto = row.children[0].textContent.trim();
        const cantidad = parseFloat(row.querySelector('.cantidad').value) || 0;
        const cantidadOriginal = parseFloat(row.querySelector('.cantidad').getAttribute('data-original')) || 0;
        const precio = parseFloat(row.querySelector('.precio').value) || 0;
        const iva = parseFloat(row.children[4].textContent) || 0;

        // Verificar si la cantidad ingresada es igual a la cantidad original
        if (cantidad === cantidadOriginal) {
            cantidadInvalida = true; // Marcar como inválido
        }

        if (producto && cantidad > 0 && precio > 0) {
            detalles.push({
                codigo: row.querySelector('.cantidad').dataset.codigo,
                cantidad: cantidad,
                precio: precio,
                iva: iva
            });
        }
    });

    
    if (cantidadInvalida) {
        e.preventDefault(); // Evitar el envío del formulario
        alert('Si desea devolver todos los productos, seleccione "Devolución de todos los productos" como motivo.');
        return;
    }

    if (detalles.length === 0) {
        e.preventDefault(); // Detener el envío si no hay productos
        alert('Debe agregar al menos un producto a la nota de crédito.');
        return;
    }

    document.getElementById('productos').value = JSON.stringify(detalles);
});


document.addEventListener('DOMContentLoaded', function () {
    // Referencias a los campos del formulario
    const factIdField = document.getElementById('fact_id');
    const motivoField = document.getElementById('motivo_id');
    const notaNroField = document.getElementById('nota_nro');
    const cargoField = document.getElementById('nota_cargo');
    const inicioField = document.getElementById('nota_inicio');
    const vtoField = document.getElementById('nota_vto');
    const tablaDetalles = document.querySelector('#tabla-factura-detalles tbody');

    // Función para deshabilitar campos
    function disableAllFields() {
        motivoField.setAttribute('disabled', 'disabled');
        cargoField.setAttribute('readonly', 'readonly');
        notaNroField.setAttribute('readonly', 'readonly');
        
        inicioField.setAttribute('readonly', 'readonly');
        vtoField.setAttribute('readonly', 'readonly');
        disableCantidadYPrecio();
    }

    // Función para habilitar campos de manera secuencial
    function enableField(field) {
        field.removeAttribute('readonly');
        field.focus();
    }

    // Deshabilitar los campos de cantidad y precio
    function disableCantidadYPrecio() {
        tablaDetalles.querySelectorAll('.cantidad, .precio').forEach(input => {
            input.setAttribute('readonly', 'readonly');
        });
    }

    // Habilitar los campos de cantidad y precio
    function enableCantidadYPrecio() {
        tablaDetalles.querySelectorAll('.cantidad, .precio').forEach(input => {
            input.removeAttribute('readonly');
        });
    }

    // Inicializar el formulario
    disableAllFields(); // Deshabilitar todos los campos excepto "Factura"
    factIdField.removeAttribute('readonly'); // Habilitar el campo de factura
    factIdField.focus(); // Enfocar el campo de factura

    /*/ Al seleccionar una factura
    factIdField.addEventListener('change', async function () {
        const facturaId = this.value;
        if (!facturaId) return;

        try {
            // Llamada a la API para obtener detalles de la factura
            const response = await fetch(`get_pedido_detalle.php?fact_id=${facturaId}`);
            const detalles = await response.json();

            tablaDetalles.innerHTML = ''; // Limpiar la tabla antes de cargar nuevos detalles

            // Rellenar la tabla con los datos
            let total = 0;
            detalles.forEach(detalle => {
                const subtotal = detalle.cantidad * detalle.precio;
                total += subtotal;

                const row = `
                    <tr>
                        <td>${detalle.producto}</td>
                        <td><input type="number" class="form-control cantidad" value="${detalle.cantidad}" data-codigo="${detalle.codigo}" readonly /></td>
                        <td><input type="number" class="form-control precio" value="${detalle.precio}" data-codigo="${detalle.codigo}" readonly /></td>
                        <td class="subtotal">${subtotal.toFixed(2)}</td>
                        <td>${detalle.iva} %</td>
                        <td><button type="button" class="btn btn-danger btn-sm btn-quitar">Quitar</button></td>
                    </tr>`;
                tablaDetalles.insertAdjacentHTML('beforeend', row);
            });

            document.getElementById('nota_total').value = total.toFixed(2);

            disableCantidadYPrecio(); // Deshabilitar cantidad y precio por defecto
            enableField(motivoField); // Habilitar el campo "Motivo"
        } catch (error) {
            console.error('Error al cargar detalles de la factura:', error);
            alert('Ocurrió un error al intentar cargar los detalles de la factura.');
        }
    });

    */

    // Al seleccionar un motivo
    motivoField.addEventListener('change', function () {
        const cargoField = document.getElementById('nota_cargo');
        const notaNroField = document.getElementById('nota_nro');
        
        if (motivoField.value === '2') { // '2' es el ID para "correción de errores"
            enablePrecio(); // Habilitar solo la cantidad
            //validarCantidadYHabilitarBoton();
            validarPrecioUnitario();
            cargoField.setAttribute('readonly', 'readonly'); // Deshabilitar el campo "Cargo adicional"
            notaNroField.removeAttribute('readonly');
            notaNroField.focus(); // Pasar el foco al siguiente campo si no es "Cargo adicional"
        } else if (motivoField.value === '1') {
                // Si el motivo es "cargo adicional"
                disableCantidadYPrecio();
                cargoField.removeAttribute('readonly');
                cargoField.focus();

                // Al presionar Enter en el campo "Cargo adicional", pasar el foco a "Número de Nota"
                document.getElementById('nota_cargo').addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault(); // Evitar el envío del formulario
                        document.getElementById('nota_nro').focus(); // Pasar el foco a "Número de Nota"
                    }
                });   

                document.getElementById('nota_cargo').addEventListener('change', function () {
                    if (this.value.trim() !== '') {
                        enableNextAndDisableCurrent(this, document.getElementById('nota_nro')); // Número de Nota -> Timbrado
                    }
                });
            } else {
                // De lo contrario, habilitar cantidad y precio
                enableCantidadYPrecio();
            }
            motivoField.setAttribute('disabled', 'disabled'); // Deshabilitar motivo_id
        //enableField(notaNroField); // Habilitar el campo "Número de Nota"
    });

    //Actualizar el valor de los campos ocultos al seleccionar una opción
    document.getElementById('fact_id').addEventListener('change', function () {
        const selectedValue = this.value;
        document.getElementById('hidden_fact_id').value = selectedValue;
        this.setAttribute('disabled', 'disabled'); // Deshabilitar el select para evitar cambios
    });

    document.getElementById('motivo_id').addEventListener('change', function () {
        const selectedValue = this.value;
        document.getElementById('hidden_motivo_id').value = selectedValue;
        this.setAttribute('disabled', 'disabled'); // Deshabilitar el select para evitar cambios
    });




    // Función para habilitar la cantidad y el precio
    function enablePrecio() {
        // Habilitar solo el campo de precio
        document.querySelectorAll('#tabla-factura-detalles .precio').forEach(input => {
            input.removeAttribute('readonly');
        });

        // Asegurarse de que los campos de cantidad permanezcan deshabilitados
        document.querySelectorAll('#tabla-factura-detalles .cantidad').forEach(input => {
            input.removeAttribute('readonly');
        });
    }

    // Secuencia de habilitación de campos
    // Validar y habilitar el siguiente campo al presionar Enter en "Número de Nota"
    // Función para habilitar campos de manera secuencial
    document.getElementById('nota_nro').addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && this.value.trim() !== '') {
            event.preventDefault(); // Evitar el envío del formulario
            enableNextAndDisableCurrent(this, document.getElementById('nota_inicio')); // Número de Nota -> Fecha de Inicio
        }
    });

    // Habilitar campo "Fecha de Vencimiento" después de ingresar la fecha de inicio
    document.getElementById('nota_inicio').addEventListener('change', function () {
        if (this.value.trim() !== '') {
            enableNextAndDisableCurrent(this, document.getElementById('nota_vto')); // Fecha de Inicio -> Fecha de Vencimiento
        }
    });

    // Deshabilitar el campo "Fecha de Vencimiento" después de ingresar la fecha
    document.getElementById('nota_vto').addEventListener('change', function () {
        if (this.value.trim() !== '') {
            this.setAttribute('readonly', 'readonly'); // Deshabilitar el campo "Fecha de Vencimiento"
            document.getElementById('btn-guardar').focus(); // Enfocar el botón Guardar
        }
    });

    
/*
    inicioField.addEventListener('change', function () {
        if (inicioField.value) {
            enableField(vtoField);
        }
    });
*/





        // Función para habilitar un campo y deshabilitar el actual
    function enableNextAndDisableCurrent(currentField, nextField) {
        currentField.setAttribute('readonly', 'readonly'); // Deshabilitar el campo actual
        nextField.removeAttribute('readonly'); // Habilitar el siguiente campo
        nextField.focus(); // Enfocar el siguiente campo
    }

    /* Secuencia de habilitación de campos
    document.getElementById('motivo_id').addEventListener('change', function () {
        if (this.value) {
            enableNextAndDisableCurrent(this, document.getElementById('nota_nro')); // Motivo -> Número de Nota
        }
    });
    */


    document.getElementById('nota_nro').addEventListener('change', function () {
        if (this.value.trim() !== '') {
            enableNextAndDisableCurrent(this, document.getElementById('nota_timbrado')); // Número de Nota -> Timbrado
        }
    });

    document.getElementById('nota_timbrado').addEventListener('change', function () {
        if (this.value.trim() !== '') {
            enableNextAndDisableCurrent(this, document.getElementById('nota_inicio')); // Timbrado -> Fecha de Inicio
        }
    });

    document.getElementById('nota_inicio').addEventListener('change', function () {
        if (this.value) {
            enableNextAndDisableCurrent(this, document.getElementById('nota_vto')); // Fecha de Inicio -> Fecha de Vencimiento
        }
    });

    document.getElementById('nota_vto').addEventListener('change', function () {
        if (this.value) {
            this.setAttribute('readonly', 'readonly'); // Deshabilitar el último campo
            document.getElementById('btn-guardar').removeAttribute('readonly'); // Habilitar el botón Guardar
        }
    });


    document.getElementById('form-nota-credito').addEventListener('submit', function () {
        //const factIdField = document.getElementById('fact_id');
        //const motivoIdField = document.getElementById('motivo_id');

        // Reactivar los campos antes de enviar el formulario
        factIdField.removeAttribute('disabled');
        motivoIdField.removeAttribute('disabled');
    });





});


// Función para validar que solo se permitan números del 1 al 9 y la tecla Enter
function validarNumero(e) {
    const tecla = e.key;

    // Permitir solo números del 1 al 9 y la tecla Enter
    if (!/^[1-9]$/.test(tecla) && tecla !== 'Enter') {
        e.preventDefault();
    }
}

// Validar el campo "Número de Nota"
const inputNotaNro = document.getElementById('nota_nro');
inputNotaNro.addEventListener('keypress', validarNumero);

// Validar el campo "Cargo Adicional"
const inputNotaCargo = document.getElementById('nota_cargo');
inputNotaCargo.addEventListener('keypress', validarNumero);



// Obtener los elementos de fecha
const fechaInicio = document.getElementById('nota_inicio');
    const fechaVencimiento = document.getElementById('nota_vto');

    // Establecer la fecha mínima para "Fecha de Inicio" como la fecha actual
    const today = new Date().toISOString().split('T')[0];
    fechaInicio.setAttribute('min', today);

    // Validar que "Fecha de Vencimiento" sea igual o mayor que "Fecha de Inicio"
    fechaInicio.addEventListener('change', function () {
        // Ajustar la fecha mínima de "Fecha de Vencimiento" según "Fecha de Inicio"
        fechaVencimiento.setAttribute('min', fechaInicio.value);

        // Limpiar "Fecha de Vencimiento" si su valor es menor que "Fecha de Inicio"
        if (fechaVencimiento.value && fechaVencimiento.value < fechaInicio.value) {
            fechaVencimiento.value = '';
            alert('La fecha de vencimiento debe ser igual o posterior a la fecha de inicio.');
        }
    });

    fechaVencimiento.addEventListener('change', function () {
        // Validar que "Fecha de Vencimiento" sea igual o mayor que "Fecha de Inicio"
        if (fechaVencimiento.value < fechaInicio.value) {
            alert('La fecha de vencimiento debe ser igual o posterior a la fecha de inicio.');
            fechaVencimiento.value = '';
        }
    });


async function validarPrecioUnitario() {
    const facturaId = document.getElementById('fact_id').value;
    const botonGuardar = document.getElementById('btn-guardar');

    if (!facturaId) return;

    try {
        // Llamada a la API para obtener los precios originales
        const response = await fetch(`get_pedido_detalle.php?fact_id=${facturaId}`);
        const preciosOriginales = await response.json();

        console.log('Precios originales:', preciosOriginales);

        // Recorrer los inputs de precio en la tabla
        document.querySelectorAll('#tabla-factura-detalles .precio').forEach(input => {
            const codigoProducto = input.dataset.codigo;
            const detalleOriginal = preciosOriginales.find(det => det.codigo == codigoProducto);

            if (detalleOriginal) {
                const precioOriginal = parseFloat(detalleOriginal.precio.replace(',', '').trim());
                console.log(`Código Producto: ${codigoProducto}, Precio Original: ${precioOriginal}`);

                // Escuchar cambios en el input
                input.addEventListener('input', function () {
                    const nuevoPrecio = parseFloat(this.value.replace(',', '').trim()) || 0;
                    console.log(`Nuevo Precio Ingresado: ${nuevoPrecio}`);

                    // Deshabilitar el botón "Guardar" si el precio es menor que el original
                    if (nuevoPrecio < precioOriginal) {
                        console.log('Botón deshabilitado');
                        botonGuardar.setAttribute('disabled', 'disabled');
                    } else {
                        console.log('Botón habilitado');
                        botonGuardar.removeAttribute('disabled');
                    }
                });
            } else {
                console.warn(`No se encontró el detalle original para el producto con código: ${codigoProducto}`);
            }
        });

    } catch (error) {
        console.error('Error al validar los precios unitarios:', error);
    }
}









</script>

<?php } ?>
