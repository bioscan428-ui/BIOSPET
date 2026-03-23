// assets/js/slider.js - Para el carrusel de imágenes
let slideIndex = 1;
let slideInterval;

function mostrarSlide(n) {
    let slides = document.getElementsByClassName("slide");
    let dots = document.getElementsByClassName("dot");
    
    if (n > slides.length) slideIndex = 1;
    if (n < 1) slideIndex = slides.length;
    
    for (let i = 0; i < slides.length; i++) {
        slides[i].style.display = "none";
    }
    for (let i = 0; i < dots.length; i++) {
        dots[i].className = dots[i].className.replace(" active", "");
    }
    
    slides[slideIndex-1].style.display = "block";
    dots[slideIndex-1].className += " active";
}

function cambiarSlide(n) {
    mostrarSlide(slideIndex += n);
    resetInterval();
}

function slideActual(n) {
    mostrarSlide(slideIndex = n);
    resetInterval();
}

function iniciarIntervalo() {
    slideInterval = setInterval(function() {
        cambiarSlide(1);
    }, 5000);
}

function resetInterval() {
    clearInterval(slideInterval);
    iniciarIntervalo();
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    mostrarSlide(slideIndex);
    iniciarIntervalo();
});