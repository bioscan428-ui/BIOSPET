/**
 * dashboard.js - Funciones para el panel de administración
 */

// Modal de Historial de Mascotas
document.addEventListener('DOMContentLoaded', function() {
    const modalMascotas = document.getElementById('modalMascotas');
    const closeMascotas = document.querySelector('.close-mascotas');
    
    // Función global para abrir el modal
    window.abrirModalMascotas = function() {
        if (!modalMascotas) return;
        
        modalMascotas.style.display = 'block';
        const modalBody = document.getElementById('modalMascotasBody');
        
        if (modalBody) {
            modalBody.innerHTML = '<div style="text-align: center; padding: 40px;">Cargando historial de mascotas...</div>';
        }
        
        // Cargar datos vía AJAX
        fetch('get_historial_mascotas.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.text();
            })
            .then(html => {
                if (modalBody) {
                    modalBody.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                if (modalBody) {
                    modalBody.innerHTML = '<div style="color: red; text-align: center; padding: 40px;">❌ Error al cargar los datos</div>';
                }
            });
    };
    
    // Cerrar modal con la X
    if (closeMascotas) {
        closeMascotas.onclick = function() {
            if (modalMascotas) {
                modalMascotas.style.display = 'none';
            }
        };
    }
    
    // Cerrar modal al hacer clic fuera
    window.onclick = function(event) {
        if (event.target === modalMascotas) {
            modalMascotas.style.display = 'none';
        }
    };
    
    // Cerrar modal con tecla ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && modalMascotas && modalMascotas.style.display === 'block') {
            modalMascotas.style.display = 'none';
        }
    });
    
    // ============================================
    // DROPDOWN PARA MENÚ DE CLIENTES / FIDELIDAD
    // ============================================
    
    // Seleccionar todos los elementos con clase 'dropdown'
    const dropdowns = document.querySelectorAll('.dropdown');
    
    dropdowns.forEach(dropdown => {
        // Evento mouseenter (al pasar el mouse)
        dropdown.addEventListener('mouseenter', () => {
            const dropdownContent = dropdown.querySelector('.dropdown-content');
            if (dropdownContent) {
                dropdownContent.style.display = 'block';
            }
        });
        
        // Evento mouseleave (al salir el mouse)
        dropdown.addEventListener('mouseleave', () => {
            const dropdownContent = dropdown.querySelector('.dropdown-content');
            if (dropdownContent) {
                dropdownContent.style.display = 'none';
            }
        });
        
        // Opcional: Cerrar dropdown al hacer clic en un enlace
        const links = dropdown.querySelectorAll('.dropdown-content a');
        links.forEach(link => {
            link.addEventListener('click', () => {
                const dropdownContent = dropdown.querySelector('.dropdown-content');
                if (dropdownContent) {
                    dropdownContent.style.display = 'none';
                }
            });
        });
    });
});