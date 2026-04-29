// assets/js/citas.js
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.chk-servicio');
    const infoDiv = document.getElementById('precio-info');
    
    function actualizarTotal() {
        let total = 0;
        let seleccionados = 0;
        
        checkboxes.forEach(chk => {
            if (chk.checked) {
                total += parseFloat(chk.dataset.precio);
                seleccionados++;
            }
        });
        
        if (seleccionados > 0) {
            infoDiv.innerHTML = `💰 <strong>Total estimado:</strong> $${total.toFixed(2)} MXN (${seleccionados} servicio/s)`;
            infoDiv.style.background = '#d4edda';
            infoDiv.style.color = '#155724';
            infoDiv.style.borderLeft = '4px solid #28a745';
        } else {
            infoDiv.innerHTML = '⚠️ Seleccione al menos un servicio para continuar';
            infoDiv.style.background = '#f8d7da';
            infoDiv.style.color = '#721c24';
            infoDiv.style.borderLeft = '4px solid #dc3545';
        }
    }
    
    checkboxes.forEach(chk => {
        chk.addEventListener('change', actualizarTotal);
    });
    
    // Validar antes de enviar
    document.querySelector('.form-cita').addEventListener('submit', function(e) {
        const seleccionados = document.querySelectorAll('.chk-servicio:checked');
        if (seleccionados.length === 0) {
            e.preventDefault();
            infoDiv.innerHTML = '❌ <strong>Error:</strong> Debe seleccionar al menos un servicio';
            infoDiv.style.background = '#f8d7da';
            infoDiv.style.color = '#721c24';
            infoDiv.style.borderLeft = '4px solid #dc3545';
            infoDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
});