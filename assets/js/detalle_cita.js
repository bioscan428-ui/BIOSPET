let carritoProductos = [];
let citaIdActual = 0;

function abrirModalPago(citaId, totalGeneral) {
    document.getElementById('pago_cita_id').value = citaId;
    document.getElementById('monto_total_pago').value = '$' + totalGeneral.toFixed(2);
    document.getElementById('modalPago').style.display = 'block';
}

function abrirModalProductos(citaId) {
    citaIdActual = citaId;
    carritoProductos = [];
    actualizarListaProductos();
    document.getElementById('modalProductos').style.display = 'block';
}

function agregarProductoCarrito(id, nombre, precio) {
    const cantidadInput = document.getElementById('cantidad_' + id);
    let cantidad = parseInt(cantidadInput.value);
    const stockMaximo = parseInt(cantidadInput.max) || 0;
    
    if (isNaN(cantidad) || cantidad < 1) cantidad = 1;
    
    if (cantidad > stockMaximo) {
        alert(`Stock insuficiente. Solo hay ${stockMaximo} unidades disponibles.`);
        return;
    }
    
    const existe = carritoProductos.find(p => p.id === id);
    if (existe) {
        const nuevaCantidad = existe.cantidad + cantidad;
        if (nuevaCantidad > stockMaximo) {
            alert(`No puedes agregar más. Máximo ${stockMaximo} unidades.`);
            return;
        }
        existe.cantidad = nuevaCantidad;
    } else {
        carritoProductos.push({ id, nombre, precio, cantidad });
    }
    
    cantidadInput.value = 1;
    actualizarListaProductos();
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
        html += `
            <div class="producto-seleccionado">
                <div>
                    <strong>${item.nombre}</strong><br>
                    ${item.cantidad} x $${item.precio.toFixed(2)} = <strong>$${subtotal.toFixed(2)}</strong>
                </div>
                <button onclick="eliminarProductoCarrito(${index})">🗑️</button>
            </div>
        `;
    });
    
    listaDiv.innerHTML = html;
    totalSpan.innerHTML = `Total: $${total.toFixed(2)}`;
}

function confirmarAgregarProductos() {
    if (carritoProductos.length === 0) {
        alert('No hay productos para agregar');
        return;
    }
    
    const productosJSON = carritoProductos.map(item => ({
        id_producto: item.id,
        cantidad: item.cantidad,
        descuento: 0
    }));
    
    fetch('agregar_productos_cita.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id_cita=${citaIdActual}&productos_json=${JSON.stringify(productosJSON)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Productos agregados correctamente');
            location.reload();
        } else {
            alert('Error: ' + data.error);
        }
    });
}

function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    const modalPago = document.getElementById('modalPago');
    const modalProductos = document.getElementById('modalProductos');
    if (event.target == modalPago) modalPago.style.display = 'none';
    if (event.target == modalProductos) modalProductos.style.display = 'none';
}