/* ===================================================================
   ChequeGestor - Script Principal v4.3 (Completo y Verificado)
   =================================================================== */

/**
 * Función de ayuda para mostrar notificaciones estandarizadas
 * utilizando SweetAlert2 en toda la aplicación.
 * @param {string} tipo - El tipo de alerta ('success', 'error', 'warning', 'info').
 * @param {string} titulo - El título de la notificación.
 * @param {string} mensaje - El cuerpo del mensaje de la notificación.
 */
function mostrarNotificacion(tipo, titulo, mensaje) {
    Swal.fire({
      icon: tipo,
      title: titulo,
      text: mensaje,
      timer: 2500, // La notificación se cierra automáticamente después de 2.5 segundos
      showConfirmButton: false,
      timerProgressBar: true,
      toast: true, // Lo muestra como una notificación discreta
      position: "top-end", // En la esquina superior derecha
    });
  }
  
  // Se ejecuta cuando todo el documento HTML ha sido cargado y parseado.
  $(document).ready(function () {
  
    // --- SECCIÓN 1: LÓGICA DEL SIDEBAR INTELIGENTE (VERSIÓN FINAL CON ANIMACIÓN CORREGIDA) ---
    const wrapper = $("#wrapper");
    const sidebarToggleCollapse = $("#sidebarToggleCollapse");
    const sidebarToggleExpand = $("#sidebarToggleExpand");
    const body = $("body");
  
    function applySidebarState(isToggled) {
      if (isToggled) {
        wrapper.addClass("toggled");
      } else {
        wrapper.removeClass("toggled");
      }
      manageTooltips(isToggled);
    }
  
    function manageTooltips(isToggled) {
      const tooltips = $('[data-bs-toggle="tooltip"]');
      tooltips.tooltip("dispose");
      if (isToggled) {
        tooltips.tooltip({ trigger: "hover" });
        // El tooltip del botón expandir es manejado por su propio `title` en el HTML
      }
    }
  
    const savedState = localStorage.getItem("sidebarToggled") === "true";
    applySidebarState(savedState);
  
    setTimeout(function () {
      body.addClass("transitions-active");
    }, 100);
  
    function toggleSidebar() {
      const newState = !wrapper.hasClass("toggled");
      applySidebarState(newState);
      localStorage.setItem("sidebarToggled", newState);
    }
  
    sidebarToggleCollapse.on("click", function (e) {
      e.preventDefault();
      toggleSidebar();
    });
    sidebarToggleExpand.on("click", function (e) {
      e.preventDefault();
      toggleSidebar();
    });
  
  
    // --- SECCIÓN 3: MANEJO DEL FORMULARIO DE CHEQUES ---
    $("#form-cheque").on("submit", function (event) {
      event.preventDefault();
      event.stopPropagation();
      var form = $(this);
      var submitButton = form.find('button[type="submit"]');
  
      if (form[0].checkValidity() === false) {
        form.addClass("was-validated");
        return;
      }
  
      var originalButtonText = submitButton.html();
      submitButton
        .prop("disabled", true)
        .html(
          '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...'
        );
  
      $.ajax({
        type: "POST",
        url: "scripts/handle_cheque.php",
        data: form.serialize(),
        dataType: "json",
        success: function (response) {
          if (response.status === "success") {
            mostrarNotificacion("success", "¡Éxito!", response.message);
            form.removeClass("was-validated")[0].reset();
            setTimeout(function () {
              window.location.href = "solicitudes.php";
            }, 1500);
          } else {
            mostrarNotificacion("error", "Error", response.message);
          }
        },
        error: function () {
          mostrarNotificacion(
            "error",
            "Error de Comunicación",
            "No se pudo contactar con el servidor."
          );
        },
        complete: function () {
          submitButton.prop("disabled", false).html(originalButtonText);
        },
      });
    });
  
    // --- SECCIÓN 4: MANEJO DE ACCIONES DE APROBACIÓN/RECHAZO (CON MOTIVO) ---
    $(document).on("click", ".btn-accion-estado", function () {
        var button = $(this);
        var solicitudId = button.data("id");
        var nuevoEstado = button.data("estado");

        if (nuevoEstado === 'Rechazado') {
            // --- FLUJO DE RECHAZO CON MOTIVO ---
            Swal.fire({
                title: `Rechazar Solicitud #${solicitudId}`,
                input: 'textarea',
                inputLabel: 'Motivo del Rechazo',
                inputPlaceholder: 'Escribe el motivo del rechazo aquí...',
                inputAttributes: { 'aria-label': 'Escribe el motivo del rechazo aquí' },
                showCancelButton: true,
                confirmButtonText: 'Confirmar Rechazo',
                confirmButtonColor: '#dc3545',
                cancelButtonText: 'Cancelar',
                inputValidator: (value) => {
                    if (!value) {
                        return '¡Necesitas escribir un motivo para el rechazo!'
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarActualizacionEstado(solicitudId, nuevoEstado, result.value);
                }
            });
        } else {
            // --- FLUJO DE APROBACIÓN (SIN CAMBIOS) ---
            Swal.fire({
                title: `¿Estás seguro?`,
                text: `Estás a punto de aprobar la solicitud #${solicitudId}.`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: "#6c757d",
                confirmButtonText: 'Sí, ¡aprobar!',
                cancelButtonText: "Cancelar"
            }).then((result) => {
                if (result.isConfirmed) {
                    enviarActualizacionEstado(solicitudId, nuevoEstado);
                }
            });
        }
    });

    // Nueva función reutilizable para enviar la petición AJAX
    function enviarActualizacionEstado(solicitudId, nuevoEstado, motivo = null) {
        let postData = { solicitud_id: solicitudId, nuevo_estado: nuevoEstado };
        if (motivo) {
            postData.motivo = motivo;
        }

        $.ajax({
            type: "POST",
            url: "scripts/actualizar_estado.php",
            data: postData,
            dataType: "json",
            success: function (response) {
                if (response.status === "success") {
                    mostrarNotificacion("success", "¡Actualizado!", response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    mostrarNotificacion("error", "Error", response.message);
                }
            },
            error: function () {
                mostrarNotificacion("error", "Error", "No se pudo conectar con el servidor.");
            }
        });
    }
  
    // --- SECCIÓN 5: INICIALIZACIÓN DEL GRÁFICO DEL DASHBOARD ---
    const ctx = document.getElementById("solicitudesChart");
    if (ctx) {
      const chartLabels = JSON.parse(ctx.dataset.labels || "[]");
      const chartData = JSON.parse(ctx.dataset.values || "[]");
      const noDataMessage = document.querySelector(".no-data-message");
  
      if (chartData.length === 0) {
        if (noDataMessage) noDataMessage.style.display = "block";
        ctx.style.display = "none";
      } else {
        if (noDataMessage) noDataMessage.style.display = "none";
        ctx.style.display = "block";
        const canvasCtx = ctx.getContext("2d");
        const gradient = canvasCtx.createLinearGradient(0, 0, 0, ctx.offsetHeight);
        gradient.addColorStop(0, "rgba(13, 110, 253, 0.5)");
        gradient.addColorStop(1, "rgba(13, 110, 253, 0)");
        const getOrCreateTooltip = (chart) => {
          let tooltipEl = chart.canvas.parentNode.querySelector("div.chartjs-tooltip");
          if (!tooltipEl) {
            tooltipEl = document.createElement("div");
            tooltipEl.classList.add("chartjs-tooltip");
            tooltipEl.innerHTML = "<table></table>";
            chart.canvas.parentNode.appendChild(tooltipEl);
          }
          return tooltipEl;
        };
        const externalTooltipHandler = (context) => {
          const { chart, tooltip } = context;
          const tooltipEl = getOrCreateTooltip(chart);
          if (tooltip.opacity === 0) {
            tooltipEl.style.opacity = 0;
            return;
          }
          const tableRoot = tooltipEl.querySelector("table");
          if (!tableRoot) return;
          if (tooltip.body) {
            const titleLines = tooltip.title || [];
            const bodyLines = tooltip.body.map((b) => b.lines);
            tableRoot.innerHTML = "";
            const tableHead = document.createElement("thead");
            titleLines.forEach((title) => {
              const tr = document.createElement("tr");
              const th = document.createElement("th");
              th.style.borderWidth = 0;
              th.innerText = "Mes: " + title;
              tr.appendChild(th);
              tableHead.appendChild(tr);
            });
            const tableBody = document.createElement("tbody");
            bodyLines.forEach((body, i) => {
              const colors = tooltip.labelColors[i];
              const span = `<span class="tooltip-color-box" style="background:${colors.backgroundColor}; border-color:${colors.borderColor};"></span>`;
              const tr = document.createElement("tr");
              const td = document.createElement("td");
              td.style.borderWidth = 0;
              td.innerHTML = span + "Solicitudes: " + body;
              tr.appendChild(td);
              tableBody.appendChild(tr);
            });
            tableRoot.appendChild(tableHead);
            tableRoot.appendChild(tableBody);
          }
          const { offsetLeft: positionX, offsetTop: positionY } = chart.canvas;
          tooltipEl.style.opacity = 1;
          tooltipEl.style.left = positionX + tooltip.caretX + "px";
          tooltipEl.style.top = positionY + tooltip.caretY + "px";
        };
        new Chart(ctx, {
          type: "line",
          data: {
            labels: chartLabels,
            datasets: [
              {
                label: "Solicitudes",
                data: chartData,
                borderColor: "rgba(13, 110, 253, 1)",
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: "rgba(13, 110, 253, 1)",
                pointRadius: 4,
                pointHoverRadius: 8,
                pointHoverBorderWidth: 3,
                pointHoverBackgroundColor: "#fff",
              },
            ],
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
              y: {
                grid: { color: "rgba(255, 255, 255, 0.1)" },
                ticks: {
                  color: getComputedStyle(document.body).getPropertyValue("--chart-tick-color"),
                  precision: 0,
                  beginAtZero: true,
                },
              },
              x: {
                grid: { display: false },
                ticks: {
                  color: getComputedStyle(document.body).getPropertyValue("--chart-tick-color"),
                },
              },
            },
            plugins: {
              legend: { display: false },
              tooltip: {
                enabled: false,
                external: externalTooltipHandler,
              },
            },
          },
        });
      }
    }
  
    // --- SECCIÓN 6: MANEJO DEL MODAL DE "VER DETALLES" ---
    $(document).on("click", ".btn-ver-detalles", function () {
      var solicitudId = $(this).data("id");
      var modal = new bootstrap.Modal(
        document.getElementById("modalVerDetalles")
      );
      var modalBody = $("#detalles-content");
      var modalTitle = $("#modalVerDetalles .modal-title");
      var btnImprimir = $("#btn-imprimir-modal");
  
      modalTitle.text("Detalles de la Solicitud #" + solicitudId);
      modalBody.html(
        '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>'
      );
      btnImprimir.attr("href", "generar_cheque.php?id=" + solicitudId);
      modal.show();
  
      $.ajax({
        url: "scripts/obtener_detalles.php",
        type: "GET",
        data: { id: solicitudId },
        success: function (response) {
          modalBody.html(response);
        },
        error: function () {
          modalBody.html(
            '<div class="alert alert-danger">Error al cargar los detalles. Intente de nuevo.</div>'
          );
        },
      });
    });
  
    // --- SECCIÓN 7: GESTIÓN DE USUARIOS ---
    $(document).on("click", ".btn-editar-usuario", function () {
      var modal = new bootstrap.Modal(
        document.getElementById("modalEditarUsuario")
      );
      $("#usuario_id").val($(this).data("id"));
      $("#nombre_usuario").val($(this).data("nombre"));
      $("#email").val($(this).data("email"));
      $("#rol").val($(this).data("rol"));
      $("#jefe_id").val($(this).data("jefe-id"));
      modal.show();
    });
  
    $("#form-editar-usuario").on("submit", function (e) {
      e.preventDefault();
      var form = $(this);
      var submitButton = form.find('button[type="submit"]');
  
      Swal.fire({
        title: "¿Guardar los cambios?",
        text: "Se actualizará el rol y/o el supervisor del usuario.",
        icon: "info",
        showCancelButton: true,
        confirmButtonColor: "#0d6efd",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, guardar",
        cancelButtonText: "Cancelar",
      }).then((result) => {
        if (result.isConfirmed) {
          submitButton
            .prop("disabled", true)
            .html(
              '<span class="spinner-border spinner-border-sm"></span> Guardando...'
            );
          $.ajax({
            type: "POST",
            url: "scripts/actualizar_usuario.php",
            data: form.serialize(),
            dataType: "json",
            success: function (response) {
              if (response.status === "success") {
                mostrarNotificacion("success", "¡Éxito!", response.message);
                setTimeout(() => location.reload(), 1500);
              } else {
                mostrarNotificacion("error", "Error", response.message);
              }
            },
            error: function () {
              mostrarNotificacion(
                "error",
                "Error",
                "No se pudo conectar con el servidor."
              );
            },
          });
        }
      });
    });
  
    // --- SECCIÓN 8: CREAR NUEVO USUARIO ---
    $("#form-crear-usuario").on("submit", function (e) {
      e.preventDefault();
      var form = $(this);
      var submitButton = form.find('button[type="submit"]');
  
      submitButton
        .prop("disabled", true)
        .html('<span class="spinner-border spinner-border-sm"></span> Creando...');
  
      $.ajax({
        type: "POST",
        url: "scripts/handle_register.php",
        data: form.serialize(),
        dataType: "json",
        success: function (response) {
          if (response.status === "success") {
            mostrarNotificacion("success", "¡Éxito!", response.message);
            setTimeout(() => location.reload(), 1500);
          } else {
            mostrarNotificacion("error", "Error", response.message);
          }
        },
        error: function () {
          mostrarNotificacion("error", "Error", "No se pudo crear el usuario.");
        },
        complete: function () {
          submitButton.prop("disabled", false).html("Crear Usuario");
        },
      });
    });
  
    // --- SECCIÓN 9: MOSTRAR/OCULTAR CONTRASEÑA ---
    function togglePasswordVisibility(passwordInput, toggleButton) {
      const type =
        passwordInput.attr("type") === "password" ? "text" : "password";
      passwordInput.attr("type", type);
      toggleButton.find("i").toggleClass("bi-eye-slash bi-eye");
    }
    $("#togglePassword").on("click", function () {
      togglePasswordVisibility($("#password"), $(this));
    });
    $("#toggleConfirmPassword").on("click", function () {
      togglePasswordVisibility($("#confirm_password"), $(this));
    });
    $("#toggleCreatePassword").on("click", function () {
      togglePasswordVisibility($("#create_password"), $(this));
    });
    $("#toggleCreateConfirmPassword").on("click", function () {
      togglePasswordVisibility($("#create_confirm_password"), $(this));
    });
  
    // --- SECCIÓN 10: MARCAR SOLICITUD COMO PAGADA ---
    $(document).on("click", ".btn-marcar-pagado", function () {
      var button = $(this);
      var solicitudId = button.data("id");
      var cardToRemove = button.closest(".approval-card");
  
      Swal.fire({
        title: "¿Confirmar Pago?",
        text: `Estás a punto de marcar la solicitud #${solicitudId} como pagada. Esta acción es definitiva.`,
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#198754",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, ¡marcar como pagada!",
        cancelButtonText: "Cancelar",
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            type: "POST",
            url: "scripts/marcar_pagado.php",
            data: { solicitud_id: solicitudId },
            dataType: "json",
            success: function (response) {
              if (response.status === "success") {
                mostrarNotificacion("success", "¡Éxito!", response.message);
                cardToRemove.fadeOut(500, function () {
                  cardToRemove.remove();
                });
              } else {
                mostrarNotificacion("error", "Error", response.message);
              }
            },
            error: function () {
              mostrarNotificacion(
                "error",
                "Error",
                "No se pudo conectar con el servidor."
              );
            },
          });
        }
      });
    });
  
    // --- SECCIÓN 11: VALIDACIÓN DE FECHAS DINÁMICA ---
    const fechaSolicitudInput = $("#fecha_solicitud");
    const fechaUtilizarseInput = $("#fecha_utilizarse");
    if (fechaSolicitudInput.length) {
      if (fechaSolicitudInput.val()) {
        fechaUtilizarseInput.attr("min", fechaSolicitudInput.val());
      }
      fechaSolicitudInput.on("change", function () {
        const nuevaFechaMinima = $(this).val();
        fechaUtilizarseInput.attr("min", nuevaFechaMinima);
        if (fechaUtilizarseInput.val() < nuevaFechaMinima) {
          fechaUtilizarseInput.val("");
        }
      });
    }
  
    // --- SECCIÓN 12: FILTRO EN TIEMPO REAL PARA USUARIOS ---
    $("#filtro-usuarios").on("keyup", function () {
      var searchTerm = $(this).val().toLowerCase();
      $(".user-list-item").each(function () {
        var userCard = $(this);
        var cardText = userCard.text().toLowerCase();
        if (cardText.indexOf(searchTerm) > -1) {
          userCard.show();
        } else {
          userCard.hide();
        }
      });
    });
  
    // --- SECCIÓN 13: EVITAR CIERRE DEL DROPDOWN DE TEMA ---
    $(".dropdown-item-interactive").on("click", function (e) {
      e.stopPropagation();
    });
  
    // --- SECCIÓN 14: LÓGICA SIMPLIFICADA PARA CENTROS DE COSTO DE SAP ---
    const empresaSapSelect = $("#empresa_sap");
    if (empresaSapSelect.length) {
      function cargarOpcionesSAP(selector, dimension) {
        const select = $(selector);
        const company = empresaSapSelect.val();
        select.prop("disabled", true).html("<option>Cargando...</option>");
        $.ajax({
          url: "scripts/obtener_centros_costo.php",
          type: "GET",
          data: { company: company, dimension: dimension },
          dataType: "json",
          success: function (data) {
            if (data.error) {
              select.html(`<option>Error: ${data.message}</option>`);
              return;
            }
            select.html('<option value="">Seleccionar...</option>');
            if (data.length > 0) {
              $.each(data, function (key, value) {
                select.append(
                  $("<option>", {
                    value: value.OcrCode,
                    text: `${value.OcrCode} - ${value.OcrName}`,
                  })
                );
              });
              select.prop("disabled", false);
            } else {
              select.html('<option value="">N/A</option>');
            }
          },
          error: function () {
            select.html("<option>Error al cargar</option>");
          },
        });
      }
      function cargarTodosLosNiveles() {
        cargarOpcionesSAP("#cc_nivel1", 1);
        cargarOpcionesSAP("#cc_nivel2", 2);
        cargarOpcionesSAP("#cc_nivel3", 3);
        cargarOpcionesSAP("#cc_nivel4", 4);
      }
      function actualizarCentroCostoFinal() {
        const valores = [];
        $(".cc-selector").each(function () {
          valores.push($(this).val() || "");
        });
        $("#centro_costo_final").val(valores.join("-"));
      }
      cargarTodosLosNiveles();
      empresaSapSelect.on("change", function () {
        cargarTodosLosNiveles();
        actualizarCentroCostoFinal();
      });
      $(".cc-selector").on("change", function () {
        actualizarCentroCostoFinal();
      });
    }

    // --- SECCIÓN FINAL: MANEJO DEL FORMULARIO DE EDICIÓN DE CHEQUES ---
    $('#form-editar-cheque').on('submit', function(event) {
        event.preventDefault();
        var form = $(this);
        var submitButton = form.find('button[type="submit"]');

        if (form[0].checkValidity() === false) {
            form.addClass('was-validated');
            return;
        }

        Swal.fire({
            title: '¿Actualizar Solicitud?',
            text: "La solicitud se reenviará al flujo de aprobación.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, actualizar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var originalButtonText = submitButton.html();
                submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Actualizando...');

                $.ajax({
                    type: 'POST',
                    url: form.attr('action'), // Toma la URL del action del formulario
                    data: form.serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            mostrarNotificacion('success', '¡Éxito!', response.message);
                            setTimeout(function() {
                                window.location.href = 'solicitudes.php';
                            }, 1500);
                        } else {
                            mostrarNotificacion('error', 'Error', response.message);
                        }
                    },
                    error: function() {
                        mostrarNotificacion('error', 'Error de Comunicación', 'No se pudo contactar al servidor.');
                    },
                    complete: function() {
                        submitButton.prop('disabled', false).html(originalButtonText);
                    }
                });
            }
        });
    });
  });
  
  
  // --- LÓGICA DEL SELECTOR DE TEMA ---
  const themeSwitch = document.getElementById("themeSwitch");
  const htmlEl = document.documentElement;
  const setLightMode = (isLight) => {
    if (isLight) {
      htmlEl.setAttribute("data-bs-theme", "light");
      localStorage.setItem("theme", "light");
      if (themeSwitch) themeSwitch.checked = true;
    } else {
      htmlEl.setAttribute("data-bs-theme", "dark");
      localStorage.setItem("theme", "dark");
      if (themeSwitch) themeSwitch.checked = false;
    }
  };
  const savedTheme = localStorage.getItem("theme") || "dark";
  setLightMode(savedTheme === "light");
  if (themeSwitch) {
    themeSwitch.addEventListener("change", () => {
      setLightMode(themeSwitch.checked);
    });
  }