let contadorMascotas = 1;
        let mascotasDelCliente = [];

        // Función para filtrar servicios por especie
        function filtrarServiciosPorEspecie(idx, especie) {
            const serviciosGrid = document.querySelector(`.servicios-grid[data-idx="${idx}"]`);
            if (!serviciosGrid) return;

            const servicios = serviciosGrid.querySelectorAll('.servicio-checkbox');
            servicios.forEach(servicio => {
                // Mostrar TODOS los servicios sin filtrar
                servicio.style.display = 'flex';
            });
            calcularTotalMascota(idx);
        }

        // Calcular total de una mascota específica
        function calcularTotalMascota(idx) {
            const serviciosGrid = document.querySelector(`.servicios-grid[data-idx="${idx}"]`);
            if (!serviciosGrid) return;
            
            let total = 0;
            const checkboxes = serviciosGrid.querySelectorAll('input[type="checkbox"]:checked');
            checkboxes.forEach(checkbox => {
                total += parseFloat(checkbox.getAttribute('data-precio') || 0);
            });
            
            const totalDiv = document.getElementById(`total-mascota-${idx}`);
            if (totalDiv) {
                totalDiv.innerHTML = `💰 Total: $${total.toFixed(2)}`;
            }
            return total;
        }

        // Calcular total general de todas las mascotas
        function calcularTotalGeneral() {
            let totalGeneral = 0;
            for (let i = 0; i < contadorMascotas; i++) {
                const total = calcularTotalMascota(i);
                if (total) totalGeneral += total;
            }
            return totalGeneral;
        }

        function buscarMascotasPorTelefono() {
            const telefono = document.getElementById('telefono').value;
            if(telefono.length < 10) return;
            
            fetch(`controllers/buscar_mascotas_ajax.php?telefono=${telefono}`)
                .then(response => response.json())
                .then(data => {
                    mascotasDelCliente = data;
                    const container = document.getElementById('mascotas-existentes-container');
                    const listaDiv = document.getElementById('lista-mascotas-existentes');
                    
                    if(data.length > 0) {
                        listaDiv.innerHTML = '';
                        data.forEach(m => {
                            listaDiv.innerHTML += `
                                <div style="margin: 5px 0;">
                                    🐕 <strong>${m.nombre_mascota}</strong> - ${m.especie} ${m.raza ? '- ' + m.raza : ''}
                                </div>
                            `;
                        });
                        container.style.display = 'block';
                        
                        document.querySelectorAll('.select-mascota-existente').forEach(select => {
                            actualizarSelectMascotas(select, data);
                        });
                    } else {
                        container.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function actualizarSelectMascotas(select, mascotas) {
            select.innerHTML = '<option value="">-- Seleccionar mascota --</option>';
            if(mascotas && mascotas.length) {
                mascotas.forEach(m => {
                    select.innerHTML += `<option value="${m.id}" data-especie="${m.especie}">${m.nombre_mascota} (${m.especie})</option>`;
                });
            } else {
                select.innerHTML += '<option value="" disabled>No hay mascotas registradas con este teléfono</option>';
            }
        }

        function toggleMascotaForm(idx, tipo) {
            const nuevaForm = document.getElementById(`mascota-nueva-${idx}`);
            const existenteForm = document.getElementById(`mascota-existente-${idx}`);
            
            if(!nuevaForm || !existenteForm) {
                console.error('No se encontraron los formularios para idx:', idx);
                return;
            }
            
            if(tipo === 'nueva') {
                nuevaForm.style.display = 'block';
                existenteForm.style.display = 'none';
                
                const select = document.querySelector(`select[name="mascotas_existentes[${idx}][id]"]`);
                if(select) select.removeAttribute('required');
                
                const nombreInput = document.querySelector(`input[name="mascotas_nuevas[${idx}][nombre]"]`);
                if(nombreInput) nombreInput.setAttribute('required', 'required');
                
                // Resetear servicios cuando es nueva mascota
                const serviciosGrid = document.querySelector(`.servicios-grid[data-idx="${idx}"]`);
                if(serviciosGrid) {
                    serviciosGrid.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
                }
            } else {
                nuevaForm.style.display = 'none';
                existenteForm.style.display = 'block';
                
                const select = document.querySelector(`select[name="mascotas_existentes[${idx}][id]"]`);
                if(select) select.setAttribute('required', 'required');
                
                const nombreInput = document.querySelector(`input[name="mascotas_nuevas[${idx}][nombre]"]`);
                if(nombreInput) nombreInput.removeAttribute('required');
                
                if(mascotasDelCliente.length) {
                    actualizarSelectMascotas(select, mascotasDelCliente);
                }
                
                // Al seleccionar mascota existente, cargar su especie para filtrar servicios
                if(select) {
                    select.onchange = function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const especie = selectedOption.getAttribute('data-especie') || 'Canino';
                        const especieInput = document.getElementById(`especie-mostrada-${idx}`);
                        if(especieInput) especieInput.value = especie;
                        filtrarServiciosPorEspecie(idx, especie);
                    };
                }
            }
        }

        function agregarMascota() {
    // Logs de depuración
    console.log('=== agregarMascota INICIADO ===');
    console.log('contadorMascotas actual:', contadorMascotas);
    
    const container = document.getElementById('mascotas-container');
    console.log('container encontrado:', container ? 'SI' : 'NO');
    
    const template = document.querySelector('.mascota-card');
    console.log('template encontrado:', template ? 'SI' : 'NO');
    
    if (!template) {
        console.error('ERROR: No se encontró el template .mascota-card');
        alert('Error al agregar mascota. Contacta al administrador.');
        return;
    }
    
    // Clonar el template (NO redeclarar container)
    const nuevoTemplate = template.cloneNode(true);
    const nuevoIdx = contadorMascotas;
    
    nuevoTemplate.setAttribute('data-idx', nuevoIdx);
    
    const titulo = nuevoTemplate.querySelector('h3');
    if (titulo) titulo.textContent = `🐕 Mascota #${nuevoIdx + 1}`;
    
    // Actualizar el select de tipo
    const selectTipo = nuevoTemplate.querySelector('select[name*="mascota_tipo"]');
    if (selectTipo) {
        const nuevoName = `mascota_tipo[${nuevoIdx}]`;
        selectTipo.setAttribute('name', nuevoName);
        selectTipo.setAttribute('onchange', `toggleMascotaForm(${nuevoIdx}, this.value)`);
        selectTipo.value = 'nueva';
    }
    
    // Actualizar inputs de mascota nueva
    nuevoTemplate.querySelectorAll('[name]').forEach(el => {
        const name = el.getAttribute('name');
        if (name && name.includes('[0]')) {
            const nuevoName = name.replace('[0]', `[${nuevoIdx}]`);
            el.setAttribute('name', nuevoName);
        }
        if (el.tagName === 'INPUT' && el.type !== 'file') el.value = '';
        if (el.tagName === 'SELECT' && el !== selectTipo) el.value = '';
    });
    
    // Actualizar IDs de los divs
    const nuevaDiv = nuevoTemplate.querySelector('.mascota-nueva-form');
    const existenteDiv = nuevoTemplate.querySelector('.mascota-existente-form');
    if (nuevaDiv) nuevaDiv.id = `mascota-nueva-${nuevoIdx}`;
    if (existenteDiv) existenteDiv.id = `mascota-existente-${nuevoIdx}`;
    
    // Actualizar select de mascotas existentes
    const selectExistente = nuevoTemplate.querySelector('.select-mascota-existente');
    if (selectExistente) {
        selectExistente.setAttribute('name', `mascotas_existentes[${nuevoIdx}][id]`);
        selectExistente.setAttribute('data-idx', nuevoIdx);
        if (mascotasDelCliente.length > 0) {
            actualizarSelectMascotas(selectExistente, mascotasDelCliente);
        }
    }
    
    // Actualizar servicios grid
    const serviciosGrid = nuevoTemplate.querySelector('.servicios-grid');
    if (serviciosGrid) {
        serviciosGrid.setAttribute('data-idx', nuevoIdx);
        serviciosGrid.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            const name = cb.getAttribute('name');
            if (name) {
                const nuevoName = name.replace(/\[\d+\]/, `[${nuevoIdx}]`);
                cb.setAttribute('name', nuevoName);
            }
            cb.checked = false;
        });
    }
    
    // Actualizar total
    const totalDiv = nuevoTemplate.querySelector('.total-mascota');
    if (totalDiv) totalDiv.id = `total-mascota-${nuevoIdx}`;
    
    // Agregar event listener a select de especie para filtrar servicios
    const especieSelect = nuevoTemplate.querySelector('.especie-select');
    if (especieSelect) {
        especieSelect.setAttribute('data-idx', nuevoIdx);
        especieSelect.onchange = function() {
            filtrarServiciosPorEspecie(nuevoIdx, this.value);
        };
    }
    
    // Agregar event listeners a checkboxes para calcular total
    serviciosGrid?.querySelectorAll('input[type="checkbox"]').forEach(cb => {
        cb.onchange = () => calcularTotalMascota(nuevoIdx);
    });
    
    const btnRemove = nuevoTemplate.querySelector('.btn-remove-mascota');
    if (btnRemove) {
        btnRemove.style.display = 'inline-block';
        btnRemove.setAttribute('onclick', 'eliminarMascota(this)');
    }
    
    container.appendChild(nuevoTemplate);
    contadorMascotas++;
    console.log('Mascota agregada, total:', contadorMascotas);
}

        function eliminarMascota(btn) {
            const cards = document.querySelectorAll('.mascota-card');
            if(cards.length > 1) {
                btn.closest('.mascota-card').remove();
            } else {
                alert('Debe haber al menos una mascota por cita');
            }
        }

        // Inicializar
        document.addEventListener('DOMContentLoaded', () => {
            toggleMascotaForm(0, 'nueva');
            
            // Agregar event listener a especie de la primera mascota
            const especieSelect = document.querySelector('.especie-select');
            if(especieSelect) {
                especieSelect.onchange = function() {
                    filtrarServiciosPorEspecie(0, this.value);
                };
            }
            
            // Agregar event listeners a checkboxes de la primera mascota
            const checkboxes = document.querySelectorAll('.servicios-grid[data-idx="0"] input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.onchange = () => calcularTotalMascota(0);
            });
        });