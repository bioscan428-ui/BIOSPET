// Carrito en memoria
let carrito = [];
let ventaPendiente = null;

// Referencias DOM
const productosGrid = document.getElementById('productosGrid');
const carritoItems = document.getElementById('carritoItems');
const subtotalSpan = document.getElementById('subtotal');
const btnFinalizar = document.getElementById('btnFinalizar');
const btnVaciar = document.getElementById('btnVaciar');
const buscador = document.getElementById('buscador');
const idClienteSelect = document.getElementById('id_cliente');
const metodoPagoSelect = document.getElementById('metodo_pago');

// ============================================
// CÓDIGO DE BARRAS - LECTOR
// ============================================
let codigoBarrasBuffer = '';
let ultimaTeclaTime = 0;

document.addEventListener('keydown', function(e) {
    const tecla = e.key;
    const ahora = Date.now();
    
    if (ahora - ultimaTeclaTime > 50) {
        codigoBarrasBuffer = '';
    }
    ultimaTeclaTime = ahora;
    
    if (tecla === 'Enter' && codigoBarrasBuffer.length > 3) {
        e.preventDefault();
        buscarProductoPorCodigo(codigoBarrasBuffer);
        codigoBarrasBuffer = '';
    }
    else if (tecla >= '0' && tecla <= '9') {
        codigoBarrasBuffer += tecla;
    }
    else if (tecla === 'Backspace') {
        codigoBarrasBuffer = '';
    }
});

// Buscar producto por código de barras
async function buscarProductoPorCodigo(codigo) {
    try {
        const response = await fetch(`buscar_producto_por_codigo.php?codigo=${codigo}`);
        const producto = await response.json();
        
        if (producto && producto.id && producto.stock_actual > 0) {
            agregarProducto(producto.id, producto.nombre, producto.precio_venta, producto.stock_actual);
            
            const inputBuscador = document.getElementById('buscadorCodigo');
            if (inputBuscador) {
                inputBuscador.value = '';
                inputBuscador.style.borderColor = '#4caf50';
                setTimeout(() => {
                    inputBuscador.style.borderColor = '#ddd';
                }, 500);
            }
        } else if (producto && producto.stock_actual === 0) {
            alert(`Producto "${producto.nombre}" sin stock disponible`);
        } else {
            alert(`Producto no encontrado (Código: ${codigo})`);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error al buscar el producto');
    }
}

// Agregar producto al carrito
function agregarProducto(id, nombre, precio, stock) {
    const existente = carrito.find(item => item.id === id);
    
    if (existente) {
        if (existente.cantidad + 1 > stock) {
            alert(`Stock insuficiente. Solo hay ${stock} unidades disponibles.`);
            return;
        }
        existente.cantidad++;
        existente.subtotal = existente.cantidad * existente.precio;
    } else {
        if (1 > stock) {
            alert(`Producto sin stock disponible.`);
            return;
        }
        carrito.push({
            id: id,
            nombre: nombre,
            precio: precio,
            cantidad: 1,
            subtotal: precio
        });
    }
    actualizarCarrito();
}

// Actualizar cantidad
function actualizarCantidad(id, nuevaCantidad) {
    const item = carrito.find(i => i.id === id);
    const stock = getStockProducto(id);
    
    if (nuevaCantidad < 1) {
        eliminarProducto(id);
        return;
    }
    if (nuevaCantidad > stock) {
        alert(`Stock insuficiente. Solo hay ${stock} unidades disponibles.`);
        return;
    }
    
    item.cantidad = nuevaCantidad;
    item.subtotal = item.cantidad * item.precio;
    actualizarCarrito();
}

// Eliminar producto
function eliminarProducto(id) {
    carrito = carrito.filter(item => item.id !== id);
    actualizarCarrito();
}

// Obtener stock de producto
function getStockProducto(id) {
    const card = document.querySelector(`.producto-card[data-id="${id}"]`);
    return card ? parseInt(card.dataset.stock) : 0;
}

// Actualizar interfaz del carrito
function actualizarCarrito() {
    if (carrito.length === 0) {
        carritoItems.innerHTML = '<div style="text-align: center; color: #999; padding: 40px;">No hay productos agregados</div>';
        subtotalSpan.innerText = '$0.00';
        btnVaciar.style.display = 'none';
        btnFinalizar.disabled = true;
        return;
    }
    
    let html = '';
    let subtotal = 0;
    
    carrito.forEach(item => {
        subtotal += item.subtotal;
        html += `
            <div class="carrito-item">
                <div class="info">
                    <div class="nombre">${item.nombre}</div>
                    <div class="precio">$${item.precio.toFixed(2)} c/u</div>
                </div>
                <div class="cantidad">
                    <input type="number" value="${item.cantidad}" min="1" 
                           onchange="actualizarCantidad(${item.id}, parseInt(this.value))">
                </div>
                <div class="subtotal">$${item.subtotal.toFixed(2)}</div>
                <button class="btn-eliminar" onclick="eliminarProducto(${item.id})">✖</button>
            </div>
        `;
    });
    
    carritoItems.innerHTML = html;
    subtotalSpan.innerText = `$${subtotal.toFixed(2)}`;
    btnVaciar.style.display = 'block';
    
    const clienteOk = idClienteSelect.value !== '';
    const pagoOk = metodoPagoSelect.value !== '';
    btnFinalizar.disabled = !(clienteOk && pagoOk);
}

// Vaciar carrito
btnVaciar.onclick = function() {
    if (confirm('¿Vaciar todo el carrito?')) {
        carrito = [];
        actualizarCarrito();
    }
};

// ============================================
// FUNCIONES PARA EL MODAL DE PAGO Y VUELTO
// ============================================

// Mostrar modal de confirmación de pago
function mostrarModalPago(total) {
    ventaPendiente = total;
    document.getElementById('totalAPagar').innerHTML = `$${total.toFixed(2)}`;
    document.getElementById('modalExito').style.display = 'block';
    
    const metodoPago = metodoPagoSelect.value;
    const vueltoSection = document.getElementById('vueltoSection');
    
    if (metodoPago === 'efectivo') {
        vueltoSection.style.display = 'block';
        document.getElementById('montoRecibido').value = '';
        document.getElementById('vueltoInfo').innerHTML = '';
        document.getElementById('vueltoInfo').className = '';
    } else {
        vueltoSection.style.display = 'none';
    }
}

// Calcular vuelto en tiempo real
function calcularVuelto() {
    const total = ventaPendiente;
    const recibido = parseFloat(document.getElementById('montoRecibido').value);
    const vueltoInfo = document.getElementById('vueltoInfo');
    
    if (isNaN(recibido) || recibido === 0) {
        vueltoInfo.innerHTML = '';
        vueltoInfo.className = '';
        return;
    }
    
    if (recibido < total) {
        const faltante = total - recibido;
        vueltoInfo.innerHTML = `<span style="color: #f44336;">⚠️ Faltan: $${faltante.toFixed(2)}</span>`;
        vueltoInfo.className = 'vuelto-error';
    } else {
        const vuelto = recibido - total;
        vueltoInfo.innerHTML = `<span style="color: #4caf50; font-size: 20px; font-weight: bold;">💵 Vuelto: $${vuelto.toFixed(2)}</span>`;
        vueltoInfo.className = 'vuelto-success';
    }
}

// Confirmar venta (después de ver vuelto)
async function confirmarVenta() {
    const metodoPago = metodoPagoSelect.value;
    let recibido = null;
    
    if (metodoPago === 'efectivo') {
        recibido = parseFloat(document.getElementById('montoRecibido').value);
        const total = ventaPendiente;
        
        if (isNaN(recibido) || recibido < total) {
            alert(`Monto insuficiente. Total: $${total.toFixed(2)}`);
            return;
        }
    }
    
    await finalizarVenta(recibido);
}

// Finalizar venta (modificada para recibir el monto)
async function finalizarVenta(recibido = null) {
    if (carrito.length === 0) {
        alert('Agrega productos al carrito');
        cerrarModal();
        return;
    }
    
    const id_cliente = idClienteSelect.value;
    const metodo_pago = metodoPagoSelect.value;
    
    if (!id_cliente) {
        alert('Seleccione un cliente');
        return;
    }
    if (!metodo_pago) {
        alert('Seleccione un método de pago');
        return;
    }
    
    const total = carrito.reduce((sum, item) => sum + item.subtotal, 0);
    
    btnFinalizar.disabled = true;
    btnFinalizar.textContent = '⏳ Procesando...';
    
    try {
        const body = {
            id_cliente: parseInt(id_cliente),
            metodo_pago: metodo_pago,
            productos: carrito.map(item => ({
                id: item.id,
                cantidad: item.cantidad,
                precio: item.precio
            })),
            total: total
        };
        
        if (metodo_pago === 'efectivo' && recibido !== null) {
            body.recibido = recibido;
        }
        
        const response = await fetch('procesar_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        
        const result = await response.json();
        
        if (result.success) {
            let mensajeFinal = `
                <p>✅ ${result.message}</p>
                <p><strong>Folio:</strong> #${result.id_venta}</p>
                <p><strong>Total:</strong> $${result.total.toFixed(2)}</p>
            `;
            
            if (metodo_pago === 'efectivo' && recibido !== null) {
                mensajeFinal += `<p><strong>Recibido:</strong> $${recibido.toFixed(2)}</p>`;
                mensajeFinal += `<p><strong>Vuelto:</strong> $${result.vuelto?.toFixed(2) || (recibido - result.total).toFixed(2)}</p>`;
            }
            
            document.getElementById('modalBody').innerHTML = `
                ${mensajeFinal}
                <button onclick="cerrarModalYRecargar()" style="margin-top: 15px; padding: 10px; background: var(--primary); color: white; border: none; border-radius: 5px; cursor: pointer;">Cerrar</button>
            `;
            
            carrito = [];
            actualizarCarrito();
            btnFinalizar.disabled = false;
            btnFinalizar.textContent = '💰 Finalizar venta';
            ventaPendiente = null;
        } else {
            alert('Error: ' + result.message);
            btnFinalizar.disabled = false;
            btnFinalizar.textContent = '💰 Finalizar venta';
            cerrarModal();
        }
    } catch (error) {
        alert('Error al procesar la venta: ' + error.message);
        btnFinalizar.disabled = false;
        btnFinalizar.textContent = '💰 Finalizar venta';
        cerrarModal();
    }
}

// ============================================
// EVENTOS PRINCIPALES
// ============================================

// Evento para el botón Finalizar venta - ABRE EL MODAL
btnFinalizar.onclick = function() {
    if (carrito.length === 0) {
        alert('Agrega productos al carrito');
        return;
    }
    
    const id_cliente = idClienteSelect.value;
    const metodo_pago = metodoPagoSelect.value;
    
    if (!id_cliente) {
        alert('Seleccione un cliente');
        return;
    }
    if (!metodo_pago) {
        alert('Seleccione un método de pago');
        return;
    }
    
    const total = carrito.reduce((sum, item) => sum + item.subtotal, 0);
    mostrarModalPago(total);
};

// Evento para el input de vuelto
const montoRecibido = document.getElementById('montoRecibido');
if (montoRecibido) {
    montoRecibido.addEventListener('input', calcularVuelto);
}

// Cuando cambia el método de pago, actualizar el modal si está abierto
metodoPagoSelect.addEventListener('change', function() {
    if (ventaPendiente !== null) {
        mostrarModalPago(ventaPendiente);
    }
});

// Agregar eventos a productos
document.querySelectorAll('.producto-card').forEach(card => {
    card.onclick = (e) => {
        e.stopPropagation();
        const id = parseInt(card.dataset.id);
        const nombre = card.dataset.nombre;
        const precio = parseFloat(card.dataset.precio);
        const stock = parseInt(card.dataset.stock);
        agregarProducto(id, nombre, precio, stock);
    };
});

// Buscador de productos por nombre
buscador.addEventListener('input', function() {
    const termino = this.value.toLowerCase();
    document.querySelectorAll('.producto-card').forEach(card => {
        const nombre = card.dataset.nombre.toLowerCase();
        card.style.display = nombre.includes(termino) ? '' : 'none';
    });
});

// Búsqueda por código de barras manual
const buscadorCodigo = document.getElementById('buscadorCodigo');
if (buscadorCodigo) {
    buscadorCodigo.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            const codigo = this.value.trim();
            if (codigo) {
                buscarProductoPorCodigo(codigo);
                this.value = '';
            }
        }
    });
}

// Validar botón finalizar al cambiar cliente o método
idClienteSelect.onchange = () => actualizarCarrito();
metodoPagoSelect.onchange = () => actualizarCarrito();

// ============================================
// FUNCIONES DE MODAL
// ============================================

function cerrarModal() {
    document.getElementById('modalExito').style.display = 'none';
    ventaPendiente = null;
}

function cerrarModalYRecargar() {
    document.getElementById('modalExito').style.display = 'none';
    location.reload();
}

window.onclick = function(event) {
    const modal = document.getElementById('modalExito');
    if (event.target === modal) {
        cerrarModal();
    }
}