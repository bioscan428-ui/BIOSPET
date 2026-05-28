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
        
        // Vista previa de imagen
        document.getElementById('imagenProducto').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const vistaPrevia = document.getElementById('vistaPrevia');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    vistaPrevia.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                vistaPrevia.style.display = 'none';
                previewImg.src = '';
            }
        });
        
        // Validar que el código de barras no esté repetido
        const inputCodigo = document.getElementById('codigo_barras');
        inputCodigo.addEventListener('blur', async function() {
            const codigo = this.value.trim();
            if (codigo === '') return;
            
            try {
                const response = await fetch(`validar_codigo_barras.php?codigo=${encodeURIComponent(codigo)}`);
                const data = await response.json();
                
                if (data.existe) {
                    alert('⚠️ Este código de barras ya existe. Se generará uno automático al guardar.');
                    this.style.backgroundColor = '#f8d7da';
                } else {
                    this.style.backgroundColor = '#d4edda';
                    setTimeout(() => {
                        this.style.backgroundColor = '';
                    }, 1000);
                }
            } catch (error) {
                console.error('Error al validar:', error);
            }
        });

        // Función para habilitar/deshabilitar campos de stock
        function toggleStockFields() {
            const checkBox = document.getElementById('maneja_stock');
            const stockActualInput = document.querySelector('input[name="stock_actual"]');
            const stockMinimoInput = document.querySelector('input[name="stock_minimo"]');
            const ubicacionInput = document.querySelector('input[name="ubicacion"]');
            const fechaVencimientoInput = document.querySelector('input[name="fecha_vencimiento"]');
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
                if (ubicacionInput) ubicacionInput.disabled = true;
                if (fechaVencimientoInput) fechaVencimientoInput.disabled = true;
            } else {
                // Maneja stock - habilitar campos
                if (stockActualInput) {
                    stockActualInput.disabled = false;
                    stockActualInput.value = 0;
                }
                if (stockMinimoInput) {
                    stockMinimoInput.disabled = false;
                    stockMinimoInput.value = 5;
                }
                if (ubicacionInput) ubicacionInput.disabled = false;
                if (fechaVencimientoInput) fechaVencimientoInput.disabled = false;
            }
        }
        // Llamar a la función al cargar la página para inicializar
        document.addEventListener('DOMContentLoaded', function() {
            toggleStockFields();
        });