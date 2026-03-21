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
        } else {
            infoDiv.innerHTML = 'Seleccione al menos un servicio para ver el precio';
        }
    }

    checkboxes.forEach(chk => {
        chk.addEventListener('change', actualizarTotal);
    });
