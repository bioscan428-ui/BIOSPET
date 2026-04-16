// Modal de movimientos
        const modalMovimientos = document.getElementById('modalMovimientos');
        const closeMovimientos = document.querySelector('.close-movimientos');

        function verMovimientos(productoId, productoNombre) {
            modalMovimientos.style.display = 'block';
            document.getElementById('modalMovimientosBody').innerHTML = `<div style="text-align: center; padding: 40px;">Cargando movimientos de ${productoNombre}...</div>`;
            
            fetch(`get_movimientos_producto.php?id=${productoId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('modalMovimientosBody').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('modalMovimientosBody').innerHTML = '<div style="color: red; text-align: center; padding: 40px;">❌ Error al cargar los datos</div>';
                });
        }

        if (closeMovimientos) {
            closeMovimientos.onclick = function() {
                modalMovimientos.style.display = 'none';
            }
        }

        window.onclick = function(event) {
            if (event.target == modalMovimientos) {
                modalMovimientos.style.display = 'none';
            }
        }