// Carrito en memoria
let carrito = [];
let ventaPendiente = null;

console.log('=== punto_venta.js CARGADO ===');

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
// FUNCIONES DEL CARRITO
// ============================================

function agregarProducto(id, nombre, precio, stock, manejaStock) {
    console.log('Agregando producto:', nombre, 'Precio:', precio, 'Stock:', stock, 'ManejaStock:', manejaStock);
    
    const existente = carrito.find(item => item.id === id);
    
    // Verificar si el producto maneja stock (servicios tienen manejaStock = 0 o stock = 0)
    const esServicio = (manejaStock === 0 || stock === 0);
    
    if (esServicio) {
        // Es un servicio - permitir agregar sin límite de stock
        if (existente) {
            existente.cantidad++;
            existente.subtotal = existente.cantidad * existente.precio;
        } else {
            carrito.push({
                id: id,
                nombre: nombre,
                precio: precio,
                cantidad: 1,
                subtotal: precio
            });
        }
    } else {
        // Es producto físico - validar stock
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
    }
    
    actualizarCarrito();
    actualizarEstadoBoton();
}

function actualizarCarrito() {
    console.log('Actualizando carrito, cantidad de items:', carrito.length);
    
    if (carrito.length === 0) {
        carritoItems.innerHTML = '<div style="text-align: center; color: #999; padding: 40px;">No hay productos agregados</div>';
        subtotalSpan.innerText = '$0.00';
        btnVaciar.style.display = 'none';
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
}

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
    actualizarEstadoBoton();
}

function eliminarProducto(id) {
    carrito = carrito.filter(item => item.id !== id);
    actualizarCarrito();
    actualizarEstadoBoton();
}

function getStockProducto(id) {
    const card = document.querySelector(`.producto-card[data-id="${id}"]`);
    return card ? parseInt(card.dataset.stock) : 0;
}

function vaciarCarrito() {
    if (confirm('¿Vaciar todo el carrito?')) {
        carrito = [];
        actualizarCarrito();
        actualizarEstadoBoton();
    }
}

function actualizarEstadoBoton() {
    const clienteOk = idClienteSelect.value !== '';
    const pagoOk = metodoPagoSelect.value !== '';
    btnFinalizar.disabled = !(clienteOk && pagoOk && carrito.length > 0);
    console.log('Botón habilitado:', !btnFinalizar.disabled);
}

// ============================================
// FINALIZAR VENTA
// ============================================

async function finalizarVenta() {
    if (carrito.length === 0) {
        alert('Agrega productos al carrito');
        return;
    }
    
    const id_cliente = idClienteSelect.value;
    const metodo_pago = metodoPagoSelect.value;
    
    if (!id_cliente || !metodo_pago) {
        alert('Seleccione cliente y método de pago');
        return;
    }
    
    const total = carrito.reduce((sum, item) => sum + item.subtotal, 0);
    
    if (!confirm(`Total: $${total.toFixed(2)}\n¿Confirmar venta?`)) {
        return;
    }
    
    btnFinalizar.disabled = true;
    btnFinalizar.textContent = '⏳ Procesando...';
    
    try {
        const response = await fetch('procesar_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_cliente: parseInt(id_cliente),
                metodo_pago: metodo_pago,
                productos: carrito.map(item => ({
                    id: item.id,
                    cantidad: item.cantidad,
                    precio: item.precio
                })),
                total: total
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // 1. Guardar datos para reimpresión
            ultimaVentaId = result.id_venta;
            if (metodo_pago === 'efectivo') {
                ultimoRecibido = null; // Si no usaste el modal de vuelto
                ultimoVuelto = null;
            }
            
            // 2. Mostrar alerta de éxito
            alert(`✅ Venta exitosa!\nFolio: #${result.id_venta}\nTotal: $${result.total.toFixed(2)}`);
            
            // 3. ABRIR TICKET EN NUEVA VENTANA
            let ticketUrl = `ticket.php?id=${result.id_venta}`;
            window.open(ticketUrl, '_blank', 'width=350,height=600,toolbar=no,menubar=no,scrollbars=yes');
            
            // 4. Limpiar carrito
            carrito = [];
            actualizarCarrito();
            actualizarEstadoBoton();
            
            // 5. Mostrar botón de reimprimir si existe
            const btnReimprimir = document.getElementById('btnReimprimir');
            if (btnReimprimir) {
                btnReimprimir.style.display = 'block';
            }
            
            // 6. Recargar la página para actualizar stocks
            setTimeout(() => {
                location.reload();
            }, 500);
            
        } else {
            alert('Error: ' + result.message);
            btnFinalizar.disabled = false;
            btnFinalizar.textContent = '💰 Finalizar venta';
        }
    } catch (error) {
        alert('Error: ' + error.message);
        btnFinalizar.disabled = false;
        btnFinalizar.textContent = '💰 Finalizar venta';
    }
}

// ============================================
// EVENTOS
// ============================================

// Asignar eventos a productos
function asignarEventosProductos() {
    console.log('Asignando eventos a productos...');
    const productos = document.querySelectorAll('.producto-card');
    console.log('Productos encontrados:', productos.length);
    
    productos.forEach(card => {
        card.onclick = (e) => {
            e.stopPropagation();
            const id = parseInt(card.dataset.id);
            const nombre = card.dataset.nombre;
            const precio = parseFloat(card.dataset.precio);
            const stock = parseInt(card.dataset.stock);
            const manejaStock = parseInt(card.dataset.manejaStock);
            agregarProducto(id, nombre, precio, stock, manejaStock);
        };
    });
}

// Buscador de productos
buscador.addEventListener('input', function() {
    const termino = this.value.toLowerCase();
    document.querySelectorAll('.producto-card').forEach(card => {
        const nombre = card.dataset.nombre.toLowerCase();
        card.style.display = nombre.includes(termino) ? '' : 'none';
    });
});

// Botón vaciar carrito
btnVaciar.onclick = vaciarCarrito;

// Botón finalizar venta
btnFinalizar.onclick = finalizarVenta;

// Habilitar botón al cambiar selects
idClienteSelect.onchange = actualizarEstadoBoton;
metodoPagoSelect.onchange = actualizarEstadoBoton;

// Inicializar
asignarEventosProductos();
actualizarEstadoBoton();

// Código de barras (opcional)
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

async function buscarProductoPorCodigo(codigo) {
    try {
        const response = await fetch(`buscar_producto_por_codigo.php?codigo=${codigo}`);
        const producto = await response.json();
        if (producto && producto.id && producto.stock_actual > 0) {
            agregarProducto(producto.id, producto.nombre, producto.precio_venta, producto.stock_actual);
        } else {
            alert('Producto no encontrado');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

console.log('=== script listo ===');