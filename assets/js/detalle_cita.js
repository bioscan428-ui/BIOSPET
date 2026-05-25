let carritoProductos = [];
let citaIdActual = 0;

let totalAPagar = 0;  // Agrega esta variable global al inicio del archivo

function abrirModalPago(citaId, totalGeneral) {
    // Guardar el total
    totalAPagar = parseFloat(totalGeneral);
    
    // Establecer valores básicos
    document.getElementById('pago_cita_id').value = citaId;
    document.getElementById('monto_total_pago').value = '$' + totalAPagar.toFixed(2);
    
    // Resetear campos del nuevo modal (los que agregaste)
    const metodoPagoSelect = document.getElementById('metodo_pago_pago');
    const vueltoSection = document.getElementById('vueltoSection');
    const montoRecibidoInput = document.getElementById('montoRecibido');
    const vueltoInfoDiv = document.getElementById('vueltoInfo');
    const referenciaInput = document.getElementById('referencia_pago');
    
    if (metodoPagoSelect) metodoPagoSelect.value = '';
    if (vueltoSection) vueltoSection.style.display = 'none';
    if (montoRecibidoInput) montoRecibidoInput.value = '';
    if (vueltoInfoDiv) vueltoInfoDiv.innerHTML = '';
    if (referenciaInput) referenciaInput.value = '';
    
    // Mostrar modal
    document.getElementById('modalPago').style.display = 'block';
}
//---------------DEPURACION
function abrirModalPago(citaId, totalGeneral) {
    console.log('=== abrirModalPago ejecutada ===');
    console.log('Cita ID:', citaId);
    console.log('Total:', totalGeneral);
    
    // Verificar que los elementos existen
    const pagoCitaId = document.getElementById('pago_cita_id');
    const montoTotal = document.getElementById('monto_total_pago');
    const modal = document.getElementById('modalPago');
    
    console.log('Elemento pago_cita_id:', pagoCitaId);
    console.log('Elemento monto_total_pago:', montoTotal);
    console.log('Elemento modalPago:', modal);
    
    if (!pagoCitaId || !montoTotal || !modal) {
        console.error('ERROR: No se encontraron los elementos del modal');
        return;
    }
    
    // Guardar el total
    totalAPagar = parseFloat(totalGeneral);
    
    // Establecer valores básicos
    pagoCitaId.value = citaId;
    montoTotal.value = '$' + totalAPagar.toFixed(2);
    
    // Resetear campos del nuevo modal
    const metodoPagoSelect = document.getElementById('metodo_pago_pago');
    const vueltoSection = document.getElementById('vueltoSection');
    const montoRecibidoInput = document.getElementById('montoRecibido');
    const vueltoInfoDiv = document.getElementById('vueltoInfo');
    const referenciaInput = document.getElementById('referencia_pago');
    
    if (metodoPagoSelect) metodoPagoSelect.value = '';
    if (vueltoSection) vueltoSection.style.display = 'none';
    if (montoRecibidoInput) montoRecibidoInput.value = '';
    if (vueltoInfoDiv) vueltoInfoDiv.innerHTML = '';
    if (referenciaInput) referenciaInput.value = '';
    
    // Mostrar modal
    modal.style.display = 'block';
    console.log('Modal abierto');
}
//---------------FIN DEPURACION

function abrirModalProductos(citaId) {
    console.log('Abriendo modal para cita:', citaId);
    citaIdActual = citaId;
    carritoProductos = [];
    actualizarListaProductos();
    document.getElementById('modalProductos').style.display = 'block';
    // Conectar botones al abrir el modal
    conectarBotonesAgregar();
}

function agregarProductoCarrito(id, nombre, precio) {
    console.log('Agregando producto:', id, nombre, precio);
    
    const cantidadInput = document.getElementById('cantidad_' + id);
    let cantidad = parseInt(cantidadInput.value);
    const stockMaximo = parseInt(cantidadInput.max) || 0;
    
    console.log('Cantidad seleccionada:', cantidad, 'Stock maximo:', stockMaximo);
    
    if (isNaN(cantidad) || cantidad < 1) cantidad = 1;
    
    if (cantidad > stockMaximo) {
        alert('Stock insuficiente. Solo hay ' + stockMaximo + ' unidades disponibles.');
        return;
    }
    
    const existe = carritoProductos.find(p => p.id === id);
    if (existe) {
        const nuevaCantidad = existe.cantidad + cantidad;
        if (nuevaCantidad > stockMaximo) {
            alert('No puedes agregar mas. Maximo ' + stockMaximo + ' unidades.');
            return;
        }
        existe.cantidad = nuevaCantidad;
    } else {
        carritoProductos.push({ id: id, nombre: nombre, precio: precio, cantidad: cantidad });
    }
    
    cantidadInput.value = 1;
    actualizarListaProductos();
    console.log('Carrito actual:', carritoProductos);
}

function eliminarProductoCarrito(index) {
    carritoProductos.splice(index, 1);
    actualizarListaProductos();
}

function actualizarListaProductos() {
    const listaDiv = document.getElementById('listaProductos');
    const totalSpan = document.getElementById('totalProductos');
    let total = 0;
    
    if (carritoProductos.length === 0) {
        listaDiv.innerHTML = '<p>No hay productos seleccionados</p>';
        totalSpan.innerHTML = 'Total: $0.00';
        return;
    }
    
    let html = '';
    carritoProductos.forEach((item, index) => {
        const subtotal = item.precio * item.cantidad;
        total += subtotal;
        html += '<div class="producto-seleccionado">';
        html += '<div><strong>' + item.nombre + '</strong><br>';
        html += item.cantidad + ' x $' + item.precio.toFixed(2) + ' = <strong>$' + subtotal.toFixed(2) + '</strong></div>';
        html += '<button onclick="eliminarProductoCarrito(' + index + ')">Eliminar</button>';
        html += '</div>';
    });
    
    listaDiv.innerHTML = html;
    totalSpan.innerHTML = 'Total: $' + total.toFixed(2);
}

function confirmarAgregarProductos() {
    console.log('=== confirmarAgregarProductos EJECUTADA ===');
    console.log('Cita ID:', citaIdActual);
    console.log('Productos en carrito:', carritoProductos);
    
    if (carritoProductos.length === 0) {
        alert('No hay productos para agregar');
        return;
    }
    
    const productosJSON = carritoProductos.map(function(item) {
        return {
            id_producto: item.id,
            cantidad: item.cantidad,
            descuento: 0
        };
    });
    
    console.log('Enviando peticion a agregar_productos_cita.php');
    console.log('Datos:', {
        id_cita: citaIdActual,
        productos_json: JSON.stringify(productosJSON)
    });
    
    fetch('agregar_productos_cita.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'id_cita=' + citaIdActual + '&productos_json=' + JSON.stringify(productosJSON)
    })
    .then(function(response) {
        console.log('Respuesta recibida, status:', response.status);
        return response.json();
    })
    .then(function(data) {
        console.log('Respuesta del servidor:', data);
        if (data.success) {
            alert('Productos agregados correctamente');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    })
    .catch(function(error) {
        console.error('Error en fetch:', error);
        alert('Error al conectar con el servidor: ' + error);
    });
}

function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// ========== CONEXIÓN DE BOTONES ==========

// Conectar botones "+ Agregar"
function conectarBotonesAgregar() {
    const botones = document.querySelectorAll('.btn-agregar-producto');
    console.log('Conectando botones agregar, encontrados:', botones.length);
    
    botones.forEach(function(btn) {
        if (btn.hasAttribute('data-conectado')) return;
        
        const id = btn.getAttribute('data-id');
        const nombre = btn.getAttribute('data-nombre');
        const precio = parseFloat(btn.getAttribute('data-precio'));
        
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Botón + Agregar clickeado para producto:', id, nombre);
            agregarProductoCarrito(parseInt(id), nombre, precio);
        });
        
        btn.setAttribute('data-conectado', 'true');
    });
}

// Conectar botón "Agregar a la cita"
function conectarBotonConfirmar() {
    const btnConfirmar = document.getElementById('btnAgregarCita');
    if (btnConfirmar && !btnConfirmar.hasAttribute('data-conectado')) {
        btnConfirmar.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Botón Agregar a la cita clickeado');
            confirmarAgregarProductos();
        });
        btnConfirmar.setAttribute('data-conectado', 'true');
        console.log('✅ Botón confirmar conectado');
    }
}

// Observar cuando el modal se abre
const observerModal = new MutationObserver(function() {
    const modal = document.getElementById('modalProductos');
    if (modal && modal.style.display === 'block') {
        conectarBotonesAgregar();
        conectarBotonConfirmar();
    }
});
observerModal.observe(document.body, { attributes: true, subtree: true, attributeFilter: ['style'] });

// Conectar al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    conectarBotonesAgregar();
    conectarBotonConfirmar();
});

window.onclick = function(event) {
    const modalPago = document.getElementById('modalPago');
    const modalProductos = document.getElementById('modalProductos');
    if (event.target == modalPago) modalPago.style.display = 'none';
    if (event.target == modalProductos) modalProductos.style.display = 'none';
}

// Eliminar producto de la cita (desde la tabla de productos ya agregados)
function eliminarProductoDeCita(idCita, productoId, productoNombre) {
    if (confirm(`¿Eliminar "${productoNombre}" de esta cita?`)) {
        fetch('eliminar_producto_cita.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id_cita=' + idCita + '&id_producto=' + productoId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Producto eliminado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error al eliminar el producto');
        });
    }
}

//--------------------------------------
// Quitar una unidad de un producto de la cita
function quitarUnidadProducto(idCita, productoId, productoNombre, cantidadActual) {
    if (cantidadActual <= 1) {
        // Si solo hay 1, preguntar si quiere eliminar completamente
        if (confirm(`¿Eliminar completamente "${productoNombre}" de la cita?`)) {
            eliminarProductoDeCita(idCita, productoId, productoNombre);
        }
        return;
    }
    
    if (confirm(`¿Quitar 1 unidad de "${productoNombre}"?`)) {
        fetch('quitar_unidad_producto_cita.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id_cita=' + idCita + '&id_producto=' + productoId
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                alert('Unidad eliminada correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('Error al quitar la unidad');
        });
    }
}


// ========== BÚSQUEDA DE PRODUCTOS CON AJAX ==========
function buscarProductosAJAX() {
    const inputBuscar = document.getElementById('buscador_producto_input');
    const criterio = inputBuscar ? inputBuscar.value.trim() : '';
    
    console.log('Buscando productos:', criterio);
    
    // Mostrar indicador de carga
    const productosLista = document.getElementById('productosLista');
    if (productosLista) {
        productosLista.innerHTML = '<div style="text-align: center; padding: 20px;">🔍 Buscando...</div>';
    }
    
    // Enviar petición AJAX
    fetch('buscar_productos_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'buscar_producto=' + encodeURIComponent(criterio)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        console.log('Respuesta recibida:', data);
        if (productosLista) {
            if (data.success && data.html) {
                productosLista.innerHTML = data.html;
                // Reconectar botones después de cargar nuevos productos
                conectarBotonesAgregar();
                
                // Actualizar mensaje de resultados
                const resultadoInfo = document.getElementById('resultado_busqueda_info');
                if (resultadoInfo) {
                    if (criterio !== '') {
                        resultadoInfo.innerHTML = 'Resultados para: <strong>' + criterio + '</strong>';
                        resultadoInfo.style.display = 'block';
                    } else {
                        resultadoInfo.style.display = 'none';
                    }
                }
            } else {
                productosLista.innerHTML = '<p style="text-align: center; padding: 20px; color: #999;">' + (data.error || 'No hay productos disponibles') + '</p>';
            }
        }
    })
    .catch(function(error) {
        console.error('Error en búsqueda:', error);
        if (productosLista) {
            productosLista.innerHTML = '<p style="text-align: center; padding: 20px; color: #f44336;">Error al buscar productos</p>';
        }
    });
}

// Función para limpiar búsqueda
function limpiarBusquedaProductos() {
    const inputBuscar = document.getElementById('buscador_producto_input');
    if (inputBuscar) {
        inputBuscar.value = '';
    }
    buscarProductosAJAX();
}

// Configurar event listeners para el buscador
function configurarBuscadorProductos() {
    const btnBuscar = document.getElementById('btnBuscarProductos');
    const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
    const inputBuscar = document.getElementById('buscador_producto_input');
    
    if (btnBuscar) {
        btnBuscar.addEventListener('click', function(e) {
            e.preventDefault();
            buscarProductosAJAX();
        });
    }
    
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function(e) {
            e.preventDefault();
            limpiarBusquedaProductos();
        });
    }
    
    if (inputBuscar) {
        inputBuscar.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarProductosAJAX();
            }
        });
    }
}

// Modificar abrirModalProductos para configurar el buscador
const originalAbrirModalProductos = abrirModalProductos;
window.abrirModalProductos = function(citaId) {
    originalAbrirModalProductos(citaId);
    configurarBuscadorProductos();
    
    // Enfocar el buscador
    setTimeout(function() {
        const inputBuscar = document.getElementById('buscador_producto_input');
        if (inputBuscar) {
            inputBuscar.focus();
        }
    }, 100);
};


// ========== PROCESAR PAGO DE CITA CON TICKET ==========
// Configurar evento para mostrar/ocultar sección de vuelto
function configurarEventoMetodoPago() {
    const metodoPagoSelect = document.getElementById('metodo_pago_pago');
    if (metodoPagoSelect) {
        metodoPagoSelect.addEventListener('change', function() {
            const vueltoSection = document.getElementById('vueltoSection');
            if (this.value === 'efectivo') {
                if (vueltoSection) vueltoSection.style.display = 'block';
                const montoRecibido = document.getElementById('montoRecibido');
                if (montoRecibido) montoRecibido.focus();
            } else {
                if (vueltoSection) vueltoSection.style.display = 'none';
                const montoRecibido = document.getElementById('montoRecibido');
                if (montoRecibido) montoRecibido.value = '';
                const vueltoInfo = document.getElementById('vueltoInfo');
                if (vueltoInfo) vueltoInfo.innerHTML = '';
            }
        });
    }
}

// Calcular vuelto en tiempo real
function configurarCalculoVuelto() {
    const montoRecibido = document.getElementById('montoRecibido');
    if (montoRecibido) {
        montoRecibido.addEventListener('input', function() {
            const recibido = parseFloat(this.value);
            const total = totalAPagar;
            const vueltoInfo = document.getElementById('vueltoInfo');
            
            if (!isNaN(recibido) && recibido >= total) {
                const vuelto = recibido - total;
                if (vueltoInfo) {
                    vueltoInfo.innerHTML = `<strong style="color: #28a745;">💵 Vuelto: $${vuelto.toFixed(2)}</strong>`;
                }
            } else if (!isNaN(recibido) && recibido < total) {
                const faltante = total - recibido;
                if (vueltoInfo) {
                    vueltoInfo.innerHTML = `<strong style="color: #dc3545;">⚠️ Faltante: $${faltante.toFixed(2)}</strong>`;
                }
            } else {
                if (vueltoInfo) vueltoInfo.innerHTML = '';
            }
        });
    }
}

// Confirmar pago y generar ticket
function configurarBotonConfirmarPago() {
    const btnConfirmar = document.getElementById('btnConfirmarPago');
    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', async function() {
            const id_cita = parseInt(document.getElementById('pago_cita_id').value);
            const metodo_pago = document.getElementById('metodo_pago_pago').value;
            const referencia = document.getElementById('referencia_pago').value;
            const montoRecibido = parseFloat(document.getElementById('montoRecibido').value);
            const total = totalAPagar;
            
            if (!metodo_pago) {
                alert('Seleccione un método de pago');
                return;
            }
            
            // Validar si es efectivo y el monto recibido es suficiente
            let vuelto = null;
            if (metodo_pago === 'efectivo') {
                if (isNaN(montoRecibido) || montoRecibido < total) {
                    alert(`El monto recibido (${isNaN(montoRecibido) ? '0' : montoRecibido.toFixed(2)}) es insuficiente. Total a pagar: $${total.toFixed(2)}`);
                    return;
                }
                vuelto = montoRecibido - total;
            }
            
            const btn = this;
            const textoOriginal = btn.textContent;
            btn.textContent = '⏳ Procesando...';
            btn.disabled = true;
            
            try {
                const formData = new FormData();
                formData.append('id_cita', id_cita);
                formData.append('metodo_pago', metodo_pago);
                if (referencia) formData.append('referencia', referencia);
                if (vuelto !== null) formData.append('recibido', montoRecibido);
                if (vuelto !== null) formData.append('vuelto', vuelto);
                
                const response = await fetch('actualizar_pago_cita.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(`✅ Pago registrado exitosamente!\nTotal: $${data.total.toFixed(2)}`);
                    
                    // Cerrar modal
                    cerrarModal('modalPago');
                    
                    // Abrir ticket en nueva ventana
                    let ticketUrl = `ticket_cita.php?id=${data.id_venta}`;
                    if (vuelto !== null) {
                        ticketUrl += `&recibido=${montoRecibido}&vuelto=${vuelto}`;
                    }
                    window.open(ticketUrl, '_blank', 'width=380,height=600,toolbar=no,menubar=no,scrollbars=yes');
                    
                    // Recargar la página
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error al procesar el pago');
            } finally {
                btn.textContent = textoOriginal;
                btn.disabled = false;
            }
        });
    }
}

// Inicializar configuración del modal de pago
function inicializarPago() {
    configurarEventoMetodoPago();
    configurarCalculoVuelto();
    configurarBotonConfirmarPago();
}

// Llamar a la inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    inicializarPago();
});
// ========== FIN DE PROCESAR PAGO DE CITA CON TICKET ==========