<?php
// Nombre del archivo: templates/layouts/footer.php
if (isset($_SESSION['usuario_id'])): ?>
                </main>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================== -->
<!-- COMPONENTES REUTILIZABLES (MODALS Y TOASTS) -->
<!-- ========================================================== -->

<!-- Contenedor para las Alertas Toast -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1150"></div>

<!-- Modal para Confirmar Rechazo con Motivo -->
<div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title" id="rejectionModalLabel">Confirmar Rechazo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <form id="rejectionForm">
          <input type="hidden" id="rejectionSolicitudId" name="solicitud_id">
          <div class="mb-3">
            <label for="motivoRechazo" class="form-label">Motivo (obligatorio):</label>
            <textarea class="form-control" id="motivoRechazo" name="motivo" rows="4" required placeholder="El solicitante verá este mensaje."></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmRejectionBtn">Confirmar Rechazo</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal para Ver Detalles de la Solicitud -->
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detailsModalLabel">Detalles de la Solicitud</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailsModalContent">
        <div class="text-center p-5"><div class="spinner-border" role="status"><span class="visually-hidden">Cargando...</span></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- LIBRERÍAS JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="assets/js/main.js"></script>

<!-- JAVASCRIPT PERSONALIZADO Y CENTRALIZADO -->
<script>
    // --- FUNCIÓN GLOBAL PARA MOSTRAR ALERTAS TIPO "TOAST" ---
    function showToast(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) return;
        const toastId = 'toast-' + Date.now();
        const toastClasses = { success: { bg: 'bg-success', icon: 'bi-check-circle-fill' }, error: { bg: 'bg-danger', icon: 'bi-x-circle-fill' }, info: { bg: 'bg-info', icon: 'bi-info-circle-fill' } };
        const selectedType = toastClasses[type] || toastClasses.info;
        const toastHTML = `<div id="${toastId}" class="toast align-items-center text-white ${selectedType.bg} border-0" role="alert"><div class="d-flex"><div class="toast-body"><i class="bi ${selectedType.icon} me-2"></i>${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>`;
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toast = new bootstrap.Toast(document.getElementById(toastId), { delay: 5000 });
        toast.show();
        document.getElementById(toastId).addEventListener('hidden.bs.toast', e => e.target.remove());
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar los modals de Bootstrap para poder controlarlos desde JS
        const rejectionModal = document.getElementById('rejectionModal') ? new bootstrap.Modal(document.getElementById('rejectionModal')) : null;
        const detailsModal = document.getElementById('detailsModal') ? new bootstrap.Modal(document.getElementById('detailsModal')) : null;

        // --- MANEJADOR DE EVENTOS GLOBAL PARA TODA LA APLICACIÓN ---
        document.body.addEventListener('click', function(e) {
            const approveButton = e.target.closest('.btn-accion-aprobar');
            const rejectButton = e.target.closest('.btn-accion-rechazar, .btn-rechazar-finanzas');
            const sendToSapButton = e.target.closest('.btn-enviar-sap');
            const detailsButton = e.target.closest('.btn-ver-detalles');

            // 1. APROBAR (Bandeja Jefes)
            if (approveButton) {
                e.preventDefault();
                const solicitudId = approveButton.dataset.id;
                Swal.fire({
                    title: '¿Estás seguro?', text: "Vas a APROBAR esta solicitud.", icon: 'question',
                    showCancelButton: true, confirmButtonColor: '#198754', cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, ¡aprobar!', cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        approveButton.disabled = true;
                        const formData = new FormData();
                        formData.append('solicitud_id', solicitudId);
                        formData.append('accion', 'Aprobado');
                        fetch('scripts/handle_aprobacion_sap.php', { method: 'POST', body: formData })
                        .then(res => res.json()).then(data => {
                            if (data.status === 'success') {
                                showToast(data.message, 'success');
                                document.getElementById('solicitud-' + solicitudId)?.remove();
                            } else { throw new Error(data.message); }
                        }).catch(err => {
                            showToast(err.message, 'error');
                            approveButton.disabled = false;
                        });
                    }
                });
                return;
            }

            // 2. ABRIR MODAL DE RECHAZO (Ambas Bandejas)
            if (rejectButton) {
                e.preventDefault();
                if (rejectionModal) {
                    document.getElementById('rejectionSolicitudId').value = rejectButton.dataset.id;
                    rejectionModal.show();
                }
                return;
            }

            // 3. ENVIAR A SAP (Bandeja Finanzas)
            if (sendToSapButton) {
                e.preventDefault();
                const pagoId = sendToSapButton.dataset.id;
                Swal.fire({
                    title: '¿Enviar a SAP?', text: "Esta acción es irreversible.", icon: 'warning',
                    showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, ¡enviar a SAP!', cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        sendToSapButton.disabled = true;
                        sendToSapButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Enviando...';
                        const formData = new FormData();
                        formData.append('pago_id', pagoId);
                        fetch('scripts/handle_envio_sap.php', { method: 'POST', body: formData })
                        .then(res => res.json()).then(data => {
                            if (data.status === 'success') {
                                showToast(data.message, 'success');
                                document.getElementById('solicitud-finanzas-' + pagoId)?.remove();
                            } else { throw new Error(data.message); }
                        }).catch(err => {
                            showToast(err.message, 'error');
                            sendToSapButton.disabled = false;
                            sendToSapButton.innerHTML = '<i class="bi bi-send-fill"></i> Enviar a SAP';
                        });
                    }
                });
                return;
            }
            
            // 4. ABRIR MODAL DE DETALLES
            if (detailsButton) {
                e.preventDefault();
                if (detailsModal) {
                    const solicitudId = detailsButton.dataset.id;
                    const modalContent = document.getElementById('detailsModalContent');
                    modalContent.innerHTML = '<div class="text-center p-5"><div class="spinner-border" role="status"></div></div>';
                    detailsModal.show();
                    fetch(`ajax/get_solicitud_details.php?id=${solicitudId}`)
                    .then(response => response.text())
                    .then(html => modalContent.innerHTML = html)
                    .catch(error => {
                        modalContent.innerHTML = '<div class="alert alert-danger">No se pudieron cargar los detalles.</div>';
                    });
                }
            }
        });

        // --- LÓGICA PARA CONFIRMAR EL RECHAZO DENTRO DEL MODAL ---
        const confirmRejectionBtn = document.getElementById('confirmRejectionBtn');
        if (confirmRejectionBtn) {
            confirmRejectionBtn.addEventListener('click', function() {
                const rejectionForm = document.getElementById('rejectionForm');
                if (!rejectionForm.checkValidity()) { return rejectionForm.reportValidity(); }
                const formData = new FormData(rejectionForm);
                formData.append('accion', 'Rechazado');
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Rechazando...';
                fetch('scripts/handle_aprobacion_sap.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast(data.message || 'Solicitud rechazada.', 'info');
                        const solicitudId = document.getElementById('rejectionSolicitudId').value;
                        (document.getElementById('solicitud-' + solicitudId) || document.getElementById('solicitud-finanzas-' + solicitudId))?.remove();
                        if (rejectionModal) rejectionModal.hide();
                    } else { throw new Error(data.message); }
                })
                .catch(err => showToast(err.message, 'error'))
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = 'Confirmar Rechazo';
                    rejectionForm.reset();
                });
            });
        }
    });
</script>
</body>
</html>