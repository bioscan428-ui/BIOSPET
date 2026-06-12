// Buscador de productos
    const buscador = document.getElementById('buscadorProductos');
    if (buscador) {
        buscador.addEventListener('input', function() {
            const termino = this.value.toLowerCase();
            const productos = document.querySelectorAll('#productosLista .producto-card');
            productos.forEach(card => {
                const nombre = card.getAttribute('data-nombre');
                if (nombre && nombre.includes(termino)) {
                    card.style.display = '';
                } else if (!nombre && termino === '') {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Modal de stock
    const modalStock = document.getElementById('modalStock');
    
    function abrirModalStock(id, nombre, stockActual) {
        document.getElementById('modal_id_producto').value = id;
        document.getElementById('modal_producto_nombre').value = nombre;
        document.getElementById('modal_stock_actual').value = stockActual + ' unidades';
        document.getElementById('modal_nuevo_stock').value = stockActual;
        modalStock.classList.add('active');
    }
    
    function cerrarModalStock() {
        modalStock.classList.remove('active');
    }
    
    modalStock.addEventListener('click', function(e) {
        if (e.target === modalStock) {
            cerrarModalStock();
        }
    });

    // Modal de Historial de Mascotas
    const modalMascotas = document.getElementById('modalMascotas');
    const closeMascotas = document.getElementsByClassName('close-mascotas')[0];
    
    function abrirModalMascotas() {
        modalMascotas.style.display = 'flex';
        document.getElementById('modalMascotasBody').innerHTML = '<div style="text-align: center; padding: 40px;">Cargando...</div>';
        
        fetch('get_historial_mascotas.php')
            .then(response => response.text())
            .then(html => {
                document.getElementById('modalMascotasBody').innerHTML = html;
            })
            .catch(error => {
                document.getElementById('modalMascotasBody').innerHTML = '<div style="color: red; text-align: center; padding: 40px;">Error al cargar los datos</div>';
            });
    }
    
    if (closeMascotas) {
        closeMascotas.onclick = function() {
            modalMascotas.style.display = 'none';
        }
    }
    
    // Modal de Formatos de Cita
    const modalFormatosCita = document.createElement('div');
    modalFormatosCita.id = 'modalFormatosCita';
    modalFormatosCita.className = 'modal';
    modalFormatosCita.innerHTML = `
        <div class="modal-content modal-grande" style="max-width: 600px; width: 90%;">
            <div class="modal-header" style="background: #9c27b0; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="color: white; margin: 0;">📋 Seleccionar Formato</h2>
                <span class="close-formatos-cita" style="color: white; font-size: 28px; cursor: pointer;">&times;</span>
            </div>
            <div class="modal-body" id="modalFormatosCitaBody" style="padding: 20px; max-height: 500px; overflow-y: auto;">
                <div style="text-align: center; padding: 40px;">
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <button onclick="cargarFormatoPorTipo('consentimiento_informado')" style="background: #2196f3; color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; font-size: 16px;">
                            📝 Consentimiento Informado
                        </button>
                        <button onclick="cargarFormatoPorTipo('ingreso_estetica')" style="background: #4caf50; color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; font-size: 16px;">
                            🐕 Formato de Ingreso a estética/baño
                        </button>
                        <button onclick="cargarFormatoPorTipo('acta_compromiso')" style="background: #ff9800; color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; font-size: 16px;">
                            📄 Acta Compromiso veterinaria
                        </button>
                        <button onclick="cargarFormatoPorTipo('desparacitacion')" style="background: #9c27b0; color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; font-size: 16px;">
                            💊 Desparacitación
                        </button>
                        <button onclick="cargarFormatoPorTipo('autorizacion_estetica')" style="background: #f44336; color: white; border: none; padding: 15px; border-radius: 10px; cursor: pointer; font-size: 16px;">
                            ✍️ Formato de autorización
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(modalFormatosCita);
    
    const closeFormatosCita = modalFormatosCita.querySelector('.close-formatos-cita');
    
    let citaActualId = null;
    
    function verFormatosCita(citaId, clienteId) {
        citaActualId = citaId;
        modalFormatosCita.style.display = 'flex';
    }
    
    function cargarFormatoPorTipo(tipoFormato) {
    if (!citaActualId) {
        alert('Error: No se ha seleccionado una cita');
        return;
    }
    
    const rutasFormatos = {
        'consentimiento_informado': 'imprimir_consentimiento_formato.php',
        'ingreso_estetica': 'formatos_digitales/formato_ingreso_estetica.php',
        'acta_compromiso': 'formatos_digitales/acta_compromiso.php',
        'desparacitacion': 'formatos_digitales/desparacitacion.php',
        'autorizacion_estetica': 'formatos_digitales/autorizacion_estetica.php'
    };
    
    const nombresFormatos = {
        'consentimiento_informado': 'Consentimiento Informado',
        'ingreso_estetica': 'Ingreso a estética/baño',
        'acta_compromiso': 'Acta Compromiso veterinaria',
        'desparacitacion': 'Desparacitación',
        'autorizacion_estetica': 'Autorización para estética'
    };
    
    const ruta = rutasFormatos[tipoFormato];
    const nombreFormato = nombresFormatos[tipoFormato];
    
    if (!ruta) {
        alert('Ruta de formato no encontrada');
        return;
    }
    
    const modalBody = document.getElementById('modalFormatosCitaBody');
    modalBody.innerHTML = '<div style="text-align: center; padding: 40px;">Cargando datos del formato...</div>';
    
    fetch(`get_cita_cliente.php?id_cita=${citaActualId}`)
        .then(response => response.json())
        .then(citaData => {
            if (!citaData.success) {
                throw new Error('No se pudo obtener la cita');
            }
            const clienteId = citaData.cliente_id;
            const mascotaId = citaData.mascota_id;
            return fetch(`get_ultimo_formato.php?id_cliente=${clienteId}&tipo=${tipoFormato}`);
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.formato) {
                return cargarDatosFormatoACita(citaActualId, data.formato, tipoFormato);
            } else {
                if (confirm(`No se encontró un formato "${nombreFormato}" previo para este cliente.\n\n¿Deseas abrir el formulario para llenarlo ahora?`)) {
                    // Obtener los datos de la cita nuevamente para pasar los parámetros
                    fetch(`get_cita_cliente.php?id_cita=${citaActualId}`)
                        .then(response => response.json())
                        .then(citaInfo => {
                            if (citaInfo.success) {
                                let url = ruta;
                                const params = [];
                                if (citaInfo.cliente_id) {
                                    params.push(`id_cliente=${citaInfo.cliente_id}`);
                                }
                                if (citaInfo.mascota_id) {
                                    params.push(`id_mascota=${citaInfo.mascota_id}`);
                                }
                                if (params.length > 0) {
                                    url += '?' + params.join('&');
                                }
                                window.location.href = url;
                            } else {
                                window.location.href = ruta;
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            window.location.href = ruta;
                        });
                } else {
                    modalFormatosCita.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar el formato: ' + error.message);
            modalFormatosCita.style.display = 'none';
        });
}
    
    function cargarDatosFormatoACita(citaId, formato, tipoFormato) {
        const datos = formato.datos ? JSON.parse(formato.datos) : {};
        
        let mensaje = `📋 Cargando datos del formato: ${tipoFormato.replace(/_/g, ' ').toUpperCase()}\n\n`;
        mensaje += `Mascota: ${datos.mascota_nombre || formato.nombre_mascota || 'No especificado'}\n`;
        mensaje += `Especie: ${datos.mascota_especie || 'N/A'}\n`;
        mensaje += `Raza: ${datos.mascota_raza || 'N/A'}\n`;
        mensaje += `Peso: ${datos.mascota_peso || 'N/A'} kg\n\n`;
        mensaje += `¿Deseas cargar estos datos en la cita?`;
        
        if (!confirm(mensaje)) {
            modalFormatosCita.style.display = 'none';
            return;
        }
        
        fetch('cargar_datos_formato_cita.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_cita: citaId,
                id_formato: formato.id,
                tipo_formato: tipoFormato,
                datos: datos
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert('✅ Datos cargados correctamente en la cita');
                modalFormatosCita.style.display = 'none';
                location.reload();
            } else {
                alert('❌ Error al cargar datos: ' + result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos');
        });
    }
    
    if (closeFormatosCita) {
        closeFormatosCita.onclick = function() {
            modalFormatosCita.style.display = 'none';
        }
    }

    window.onclick = function(event) {
        if (event.target == modalFormatosCita) {
            modalFormatosCita.style.display = 'none';
        }
        if (event.target == modalMascotas) {
            modalMascotas.style.display = 'none';
        }
    }

    function eliminarCitasCanceladas() {
        if (confirm('⚠️ ¿Estás seguro de eliminar TODAS las citas canceladas?\n\nEsta acción NO se puede deshacer.\n\n¿Deseas continuar?')) {
            fetch('eliminar_citas_canceladas.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'confirmar=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Error al conectar con el servidor');
            });
        }
    }