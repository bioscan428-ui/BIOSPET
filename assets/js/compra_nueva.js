// Array para almacenar productos agregados
let productosAgregados = [];
let contadorProductos = 0;

// Elementos del DOM
const productoExistente = document.getElementById('productoExistente');
const productoCantidad = document.getElementById('productoCantidad');
const productoPrecio = document.getElementById('productoPrecio');
const btnAgregar = document.getElementById('btnAgregarProducto');
const tablaBody = document.getElementById('tablaProductosBody');
const totalSpan = document.getElementById('totalCompra');
const productosJson = document.getElementById('productos_json');
const esNuevoCheckbox = document.getElementById('esNuevoProducto');
const nuevoProductoFields = document.getElementById('nuevoProductoFields');
const nuevoProductoNombre = document.getElementById('nuevoProductoNombre');
const nuevaCategoria = document.getElementById('nuevaCategoria');
const precioVentaSugerido = document.getElementById('precioVentaSugerido');

// Mostrar/ocultar campos de producto nuevo
if (esNuevoCheckbox) {
    esNuevoCheckbox.addEventListener('change', function() {
        if (this.checked) {
            nuevoProductoFields.classList.add('visible');
            if (productoExistente) productoExistente.disabled = true;
            if (productoExistente) productoExistente.value = '';
        } else {
            nuevoProductoFields.classList.remove('visible');
            if (productoExistente) productoExistente.disabled = false;
            if (nuevoProductoNombre) nuevoProductoNombre.value = '';
            if (nuevaCategoria) nuevaCategoria.value = '';
            if (precioVentaSugerido) precioVentaSugerido.value = '';
        }
    });
}

// Actualizar precio de venta sugerido
if (productoPrecio) {
    productoPrecio.addEventListener('input', function() {
        if (esNuevoCheckbox && esNuevoCheckbox.checked && this.value) {
            const precioCompra = parseFloat(this.value);
            const precioVenta = precioCompra * 1.3;
            if (precioVentaSugerido) {
                precioVentaSugerido.value = '$' + precioVenta.toFixed(2);
            }
        }
    });
}

// Cargar precio del producto seleccionado
if (productoExistente) {
    productoExistente.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const precio = selectedOption.dataset.precio;
        if (precio && productoPrecio) {
            productoPrecio.value = precio;
        }
    });
}

// Agregar producto a la lista
function agregarProducto() {
    console.log('=== agregarProducto() iniciada ===');
    let idProducto, cantidad, precioUnitario;
    let nombreNuevo = null;
    let idCategoria = null;
    let esNuevo = 0;
    
    if (esNuevoCheckbox && esNuevoCheckbox.checked) {
        // Producto nuevo
        console.log('Modo: Producto NUEVO');
        
        if (!nuevoProductoNombre.value.trim()) {
            alert('Ingrese el nombre del nuevo producto');
            return;
        }
        if (!nuevaCategoria.value) {
            alert('Seleccione una categoría');
            return;
        }
        idProducto = 0;
        nombreNuevo = nuevoProductoNombre.value.trim();
        idCategoria = parseInt(nuevaCategoria.value);
        esNuevo = 1;
        cantidad = parseInt(productoCantidad.value);
        precioUnitario = parseFloat(productoPrecio.value);
        
        console.log('Datos producto nuevo:', {
            nombreNuevo: nombreNuevo,
            idCategoria: idCategoria,
            cantidad: cantidad,
            precioUnitario: precioUnitario
        });
    } else {
        // Producto existente
        console.log('Modo: Producto EXISTENTE');
        
        if (!productoExistente.value) {
            alert('Seleccione un producto');
            return;
        }
        const selectedOption = productoExistente.options[productoExistente.selectedIndex];
        idProducto = parseInt(productoExistente.value);
        esNuevo = 0;
        cantidad = parseInt(productoCantidad.value);
        precioUnitario = parseFloat(productoPrecio.value);
        
        console.log('Datos producto existente:', {
            idProducto: idProducto,
            cantidad: cantidad,
            precioUnitario: precioUnitario
        });
    }
    
    if (isNaN(cantidad) || cantidad <= 0) {
        alert('Cantidad válida requerida');
        return;
    }
    if (isNaN(precioUnitario) || precioUnitario <= 0) {
        alert('Precio unitario válido requerido');
        return;
    }
    
    const subtotal = cantidad * precioUnitario;
    
    // Agregar al array - USANDO "id" en lugar de "id_producto"
    const nuevoItem = {
        id: idProducto,
        cantidad: cantidad,
        precio_unitario: precioUnitario,
        subtotal: subtotal,
        es_nuevo: esNuevo,
        nombre_nuevo: nombreNuevo,
        id_categoria: idCategoria
    };
    
    console.log('Item a agregar:', nuevoItem);
    productosAgregados.push(nuevoItem);
    
    actualizarTabla();
    
    // Limpiar campos
    if (productoExistente) productoExistente.value = '';
    if (productoCantidad) productoCantidad.value = 1;
    if (productoPrecio) productoPrecio.value = '';
    if (esNuevoCheckbox && esNuevoCheckbox.checked) {
        if (nuevoProductoNombre) nuevoProductoNombre.value = '';
        if (nuevaCategoria) nuevaCategoria.value = '';
        if (precioVentaSugerido) precioVentaSugerido.value = '';
        esNuevoCheckbox.checked = false;
        if (nuevoProductoFields) nuevoProductoFields.classList.remove('visible');
        if (productoExistente) productoExistente.disabled = false;
    }
    
    console.log('=== agregarProducto() finalizada ===');
}

// Eliminar producto de la lista
function eliminarProducto(index) {
    productosAgregados.splice(index, 1);
    actualizarTabla();
}

// Actualizar tabla y total
function actualizarTabla() {
    console.log('=== actualizarTabla() iniciada ===');
    console.log('productosAgregados:', productosAgregados);
    
    if (productosAgregados.length === 0) {
        if (tablaBody) {
            tablaBody.innerHTML = '<tr class="empty-row"><td colspan="6" style="text-align: center; color: #999;">No hay productos agregados</td></tr>';
        }
        if (totalSpan) totalSpan.innerText = '$0.00';
        if (productosJson) productosJson.value = '';
        console.log('Carrito vacío, JSON limpiado');
        return;
    }
    
    let html = '';
    let total = 0;
    
    productosAgregados.forEach((item, index) => {
        total += item.subtotal;
        const displayNombre = item.es_nuevo === 1 ? '🆕 NUEVO: ' + item.nombre_nuevo : (item.nombre_producto || 'Producto');
        html += `
            <tr>
                <td>${item.es_nuevo === 1 ? '🆕 Nuevo' : item.id}</td>
                <td>${displayNombre}</td>
                <td>${item.cantidad}</td>
                <td>$${item.precio_unitario.toFixed(2)}</td>
                <td>$${item.subtotal.toFixed(2)}</td>
                <td><button type="button" class="btn-eliminar" onclick="eliminarProducto(${index})">Eliminar</button></td>
            </tr>
        `;
    });
    
    if (tablaBody) tablaBody.innerHTML = html;
    if (totalSpan) totalSpan.innerText = `$${total.toFixed(2)}`;
    
    // Preparar JSON para el procedimiento - USANDO "id" en lugar de "id_producto"
    const productosParaProcedimiento = productosAgregados.map(item => ({
        id: item.id,
        cantidad: item.cantidad,
        precio_unitario: item.precio_unitario,
        es_nuevo: item.es_nuevo,
        nombre_nuevo: item.nombre_nuevo,
        id_categoria: item.id_categoria
    }));
    
    if (productosJson) productosJson.value = JSON.stringify(productosParaProcedimiento);
    
    console.log('=== JSON a enviar ===');
    console.log(productosJson.value);
}

// Evento del botón agregar
if (btnAgregar) {
    btnAgregar.addEventListener('click', agregarProducto);
}

// Permitir Enter en los campos
if (productoCantidad) {
    productoCantidad.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') agregarProducto();
    });
}

if (productoPrecio) {
    productoPrecio.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') agregarProducto();
    });
}

// Validar que haya productos antes de enviar
const formCompra = document.getElementById('formCompra');
if (formCompra) {
    formCompra.addEventListener('submit', function(e) {
        if (productosAgregados.length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un producto a la compra');
        }
    });
}

console.log('=== compra_nueva.js cargado correctamente ===');