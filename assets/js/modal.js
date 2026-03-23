// assets/js/modal.js
const modal = document.getElementById('modal-equipo');
const modalImg = document.getElementById('modal-imagen');
const modalTitulo = document.getElementById('modal-titulo');
const modalDescripcion = document.getElementById('modal-descripcion');
const cerrar = document.querySelector('.modal-cerrar');

function abrirModal(imagen, titulo, descripcion) {
    modalImg.src = imagen;
    modalTitulo.textContent = titulo;
    modalDescripcion.textContent = descripcion;
    modal.style.display = 'flex';
}

cerrar.onclick = function() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}