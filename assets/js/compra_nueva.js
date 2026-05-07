// Array para almacenar productos agregados
        let productosAgregados = [];
        
        // Elementos DOM
        const productoNombre = document.getElementById('productoNombre');
        const productoCantidad = document.getElementById('productoCantidad');
        const productoPrecio = document.getElementById('productoPrecio');
        const esNuevoProducto = document.getElementById('esNuevoProducto');
        const nuevoProductoFields = document.getElementById('nuevoProductoFields');
        const nuevaCategoria = document.getElementById('nuevaCategoria');
        const precioVentaSugerido = document.getElementById('precioVentaSugerido');
        
        // Mostrar/ocultar campos de producto nuevo
        esNuevoProducto.addEventListener('change', function() {
            nuevoProductoFields.style.display = this.checked ? 'block' : 'none';
            if (this.checked) {
                productoNombre.placeholder = "Nombre del nuevo producto *";
            } else {
                productoNombre.placeholder = "Nombre del producto *";
            }
        });
        
        // Calcular precio de venta sugerido
        productoPrecio.addEventListener('input', function() {
            if (this.value) {
                const precioCompra = parseFloat(this.value);
                const precioVenta = precioCompra * 1.3; // 30% de margen
                precioVentaSugerido.value = '$' + precioVenta.toFixed(2);
            } else {
                precioVentaSugerido.value = '';
            }
        });
        
        // Función para actualizar la tabla
        function actualizarTabla() {
            const tbody = document.getElementById('tablaProductosBody');
            const totalSpan = document.getElementById('totalCompra');
            let total = 0;
            
            if (productosAgregados.length === 0) {
                tbody.innerHTML = '<tr class="empty-row"><td colspan="5" style="text-align: center; color: #999;">No hay productos agregados</td></tr>';
                totalSpan.innerText = '$0.00';
                document.getElementById('productos_json').value = '';
                return;
            }
            
            let html = '';
            productosAgregados.forEach((item, index) => {
                const subtotal = item.cantidad * item.precio;
                total += subtotal;
                const esNuevo = item.es_nuevo ? '<span class="badge-nuevo">NUEVO</span>' : '';
                html += `
                    <tr>
                        <td>${item.nombre} ${esNuevo}</td>
                        <td>${item.cantidad}</td>
                        <td>$${item.precio.toFixed(2)}</td>
                        <td>$${subtotal.toFixed(2)}</td>
                        <td><button type="button" class="btn-eliminar-producto" data-index="${index}">🗑️</button></td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            totalSpan.innerText = `$${total.toFixed(2)}`;
            
            // Actualizar JSON oculto
            const productosJSON = productosAgregados.map(item => ({
                id_producto: item.id_producto || 0,
                nombre_nuevo: item.es_nuevo ? item.nombre : null,
                id_categoria: item.id_categoria || null,
                cantidad: item.cantidad,
                precio_unitario: item.precio,
                subtotal: item.cantidad * item.precio,
                es_nuevo: item.es_nuevo
            }));
            document.getElementById('productos_json').value = JSON.stringify(productosJSON);
            
            // Agregar event listeners a botones de eliminar
            document.querySelectorAll('.btn-eliminar-producto').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = parseInt(this.dataset.index);
                    productosAgregados.splice(index, 1);
                    actualizarTabla();
                });
            });
        }
        
        // Agregar producto
        function agregarProducto() {
            const nombre = productoNombre.value.trim();
            const cantidad = parseInt(productoCantidad.value);
            const precio = parseFloat(productoPrecio.value);
            const esNuevo = esNuevoProducto.checked;
            
            if (!nombre) {
                alert('Ingrese el nombre del producto');
                return;
            }
            
            if (!cantidad || cantidad < 1) {
                alert('Ingrese una cantidad válida');
                return;
            }
            
            if (!precio || precio <= 0) {
                alert('Ingrese un precio válido');
                return;
            }
            
            if (esNuevo && !nuevaCategoria.value) {
                alert('Seleccione una categoría para el nuevo producto');
                return;
            }
            
            // Verificar si el producto ya está agregado (mismo nombre)
            const existe = productosAgregados.some(item => item.nombre.toLowerCase() === nombre.toLowerCase());
            if (existe) {
                alert('Este producto ya está agregado. Si necesita más cantidad, edite la cantidad existente.');
                return;
            }
            
            productosAgregados.push({
                id_producto: 0, // 0 indica que es nuevo
                nombre: nombre,
                cantidad: cantidad,
                precio: precio,
                es_nuevo: esNuevo,
                id_categoria: esNuevo ? parseInt(nuevaCategoria.value) : null
            });
            
            // Resetear campos
            productoNombre.value = '';
            productoCantidad.value = '1';
            productoPrecio.value = '';
            esNuevoProducto.checked = false;
            nuevoProductoFields.style.display = 'none';
            nuevaCategoria.value = '';
            precioVentaSugerido.value = '';
            
            actualizarTabla();
        }
        
        // Eventos
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('btnAgregarProducto').addEventListener('click', agregarProducto);
            
            // Enter en el campo de nombre
            productoNombre.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    agregarProducto();
                }
            });
            
            // Prevenir envío si no hay productos
            document.getElementById('formCompra').addEventListener('submit', function(e) {
                if (productosAgregados.length === 0) {
                    e.preventDefault();
                    alert('Debe agregar al menos un producto a la compra');
                }
            });
        });