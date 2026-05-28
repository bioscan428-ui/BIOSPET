        // Generar código de barras vía AJAX
        document.getElementById('btnGenerarCodigo').addEventListener('click', async function() {
            const btn = this;
            const inputCodigo = document.getElementById('codigo_barras');
            
            btn.textContent = '⏳ Generando...';
            btn.disabled = true;
            
            try {
                const response = await fetch('generar_codigo_barras.php');
                const data = await response.json();
                
                if (data.success) {
                    inputCodigo.value = data.codigo;
                    inputCodigo.style.backgroundColor = '#d4edda';
                    setTimeout(() => {
                        inputCodigo.style.backgroundColor = '';
                    }, 1000);
                } else {
                    alert('Error al generar código: ' + data.message);
                }
            } catch (error) {
                alert('Error al conectar con el servidor');
            } finally {
                btn.textContent = '🎲 Generar';
                btn.disabled = false;
            }
        });
        
        // Función para habilitar/deshabilitar campos de stock
        function toggleStockFields() {
            const checkBox = document.getElementById('maneja_stock');
            const stockActualInput = document.getElementById('stock_actual');
            const stockMinimoInput = document.getElementById('stock_minimo');
            const ubicacionInput = document.getElementById('ubicacion');
            const fechaVencimientoInput = document.getElementById('fecha_vencimiento');
            
            if (checkBox.checked) {
                // No maneja stock - deshabilitar campos y poner valores por defecto
                if (stockActualInput) {
                    stockActualInput.disabled = true;
                    stockActualInput.value = 0;
                }
                if (stockMinimoInput) {
                    stockMinimoInput.disabled = true;
                    stockMinimoInput.value = 0;
                }
                if (ubicacionInput) {
                    ubicacionInput.disabled = true;
                    ubicacionInput.value = '';
                }
                if (fechaVencimientoInput) {
                    fechaVencimientoInput.disabled = true;
                    fechaVencimientoInput.value = '';
                }
            } else {
                // Maneja stock - habilitar campos
                if (stockActualInput) {
                    stockActualInput.disabled = false;
                }
                if (stockMinimoInput) {
                    stockMinimoInput.disabled = false;
                }
                if (ubicacionInput) {
                    ubicacionInput.disabled = false;
                }
                if (fechaVencimientoInput) {
                    fechaVencimientoInput.disabled = false;
                }
            }
        }
        
        // Llamar a la función al cargar la página para inicializar
        document.addEventListener('DOMContentLoaded', function() {
            toggleStockFields();
        });
