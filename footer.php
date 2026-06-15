<?php
// Footer común para todos los módulos
// Incluye scripts comunes y cierra el HTML

// Detectar ruta base si no está definida
if (!isset($BASE_PATH)) {
    $currentFile = $_SERVER['PHP_SELF'];
    if (strpos($currentFile, '/modules/') !== false) {
        $BASE_PATH = '../../';
    } else {
        $BASE_PATH = '';
    }
}
?>

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Emmanuels - Lucas Medina - <?= date('Y') ?></span>
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
                    <a class="btn btn-primary" href="<?= $BASE_PATH ?>login.html">Cerrar sesión</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="<?= $BASE_PATH ?>vendor/jquery/jquery.min.js"></script>
    <script src="<?= $BASE_PATH ?>vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="<?= $BASE_PATH ?>vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="<?= $BASE_PATH ?>js/sb-admin-2.min.js"></script>
    
    <!-- Initialize Bootstrap collapse functionality -->
    <script>
        $(document).ready(function() {
            // Asegurar que Bootstrap collapse esté inicializado
            $('[data-toggle="collapse"]').on('click', function(e) {
                e.preventDefault();
                var target = $(this).data('target');
                $(target).collapse('toggle');
            });
        });
    </script>

    <!-- Page level plugins -->
    <?php if (isset($extra_js_plugins)): ?>
        <?php foreach ($extra_js_plugins as $plugin): ?>
            <script src="<?= $BASE_PATH ?><?= $plugin ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Page level custom scripts -->
    <?php if (isset($extra_js)): ?>
        <?php foreach ($extra_js as $script): ?>
            <script src="<?= $BASE_PATH ?><?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Inline scripts -->
    <?php if (isset($inline_js)): ?>
        <script>
            <?= $inline_js ?>
        </script>
    <?php endif; ?>

    <!-- Session handler script -->
    <script>
        // Verificar si hay un mensaje en la sesión
        fetch('<?= $BASE_PATH ?>auth/session_handler.php')
            .then(response => response.json())
            .then(data => {
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

</body>
</html>

