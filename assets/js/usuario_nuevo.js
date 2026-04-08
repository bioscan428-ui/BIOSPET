function toggleFormulario() {
            const opcion = document.querySelector('input[name="opcion"]:checked').value;
            const seccionNuevo = document.getElementById('seccion-nuevo');
            const seccionExistente = document.getElementById('seccion-existente');
            const selectExistente = document.getElementById('id_empleado');
            
            if (opcion === 'nuevo') {
                seccionNuevo.style.display = 'block';
                seccionExistente.style.display = 'none';
                // Remover required del select existente
                if (selectExistente) {
                    selectExistente.removeAttribute('required');
                }
                // Agregar required a campos de nuevo empleado
                document.querySelectorAll('#seccion-nuevo input, #seccion-nuevo select').forEach(input => {
                    if (input.hasAttribute('data-required')) {
                        input.setAttribute('required', 'required');
                    }
                });
            } else {
                seccionNuevo.style.display = 'none';
                seccionExistente.style.display = 'block';
                // Agregar required al select existente
                if (selectExistente) {
                    selectExistente.setAttribute('required', 'required');
                }
                // Remover required de campos de nuevo empleado
                document.querySelectorAll('#seccion-nuevo input, #seccion-nuevo select').forEach(input => {
                    input.removeAttribute('required');
                });
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Marcar campos de nuevo empleado como requeridos condicionalmente
            document.querySelectorAll('#seccion-nuevo input, #seccion-nuevo select').forEach(input => {
                if (input.hasAttribute('required')) {
                    input.setAttribute('data-required', 'true');
                    input.removeAttribute('required');
                }
            });
            
            toggleFormulario();
            
            document.querySelectorAll('input[name="opcion"]').forEach(radio => {
                radio.addEventListener('change', toggleFormulario);
            });
        });