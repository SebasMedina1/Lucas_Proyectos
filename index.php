<?php
// Iniciar la sesión
session_start();


include 'header.php';
?>



                 
                <!-- Logo grande centrado arriba del copyright -->
                <div class="text-center mb-4" style="margin-top: 50px;">
                    <i class="fas fa-hamburger" style="font-size: 120px; color: #dc2626; opacity: 0.3;"></i>
                </div>

            </div>
            <!-- End of Main Content -->

            

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Emmanuels - Lucas Medina - 2025</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">¿Estás seguro?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Seleccione "Cerrar sesión" si está listo para finalizar su sesión actual.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancelar</button>
                    <a class="btn btn-primary" href="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>login.html">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>

    

    <!-- Bootstrap core JavaScript-->
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>vendor/chart.js/Chart.min.js"></script>

    <!-- Page level custom scripts -->
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>js/demo/chart-area-demo.js"></script>
    <script src="<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>js/demo/chart-pie-demo.js"></script>

</body>
<script>
    // Verificar si hay un mensaje en la sesión
    fetch('<?= isset($BASE_PATH) ? $BASE_PATH : '' ?>auth/session_handler.php')
        .then(response => response.json())
        .then(data => {
            console.log('Sesión:', data); // Agregar esta línea para depurar
            if (data && data.message) {
                const toastContainer = document.getElementById('toast-container') || createToastContainer();
                const toast = document.createElement('div');
                toast.className = 'toast-message';
                toast.innerText = data.message;

                // Aplicar clases según el tipo de mensaje
                if (data.type === 'success') {
                    toast.style.backgroundColor = 'green';
                    toast.style.color = 'white';
                } else if (data.type === 'error') {
                    toast.style.backgroundColor = 'red';
                    toast.style.color = 'white';
                    console.log("llega aca?");
                }

                toastContainer.appendChild(toast);

                // Eliminar el mensaje flotante después de 4 segundos
                setTimeout(() => {
                    toast.remove();
                }, 4000);
            }
        });

    // Crear el contenedor de mensajes si no existe
    function createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
        return container;
    }
</script>

</html>