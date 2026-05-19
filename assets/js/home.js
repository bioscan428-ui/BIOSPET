// ========== MENÚ HAMBURGUESA ==========
const btnMenu = document.querySelector('.menu-toggle');
const menu = document.getElementById('menu-lateral');
const overlay = document.getElementById('overlay');

if(btnMenu && menu && overlay) {
    btnMenu.addEventListener('click', () => {
        menu.classList.toggle('active');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        menu.classList.remove('active');
        overlay.classList.remove('active');
    });
}

// ========== MODAL LOGIN ==========
const btnUsuario = document.getElementById('btn-usuario');
const modal = document.getElementById('modal-login');
const cerrarModal = document.getElementById('cerrar-modal');
const btnAcceder = document.getElementById('btn-acceder');
const loginUsuario = document.getElementById('login-usuario');
const loginPassword = document.getElementById('login-password');

// Abrir modal
if(btnUsuario) {
    btnUsuario.addEventListener('click', (e) => {
        e.preventDefault();
        modal.classList.add('active');
        if(loginUsuario) loginUsuario.focus();
    });
}

// Cerrar modal
if(cerrarModal) {
    cerrarModal.addEventListener('click', () => {
        modal.classList.remove('active');
        if(loginUsuario) loginUsuario.value = '';
        if(loginPassword) loginPassword.value = '';
    });
}

// Cerrar modal al hacer clic fuera
if(modal) {
    modal.addEventListener('click', (e) => {
        if(e.target === modal) {
            modal.classList.remove('active');
            if(loginUsuario) loginUsuario.value = '';
            if(loginPassword) loginPassword.value = '';
        }
    });
}

// Procesar login
if(btnAcceder) {
    btnAcceder.addEventListener('click', async () => {
        const usuario = loginUsuario ? loginUsuario.value.trim() : '';
        const password = loginPassword ? loginPassword.value.trim() : '';
        
        if (!usuario || !password) {
            alert('Por favor ingresa usuario y contraseña');
            return;
        }
        
        const textoOriginal = btnAcceder.textContent;
        btnAcceder.textContent = 'Ingresando...';
        btnAcceder.disabled = true;
        
        try {
            const formData = new FormData();
            formData.append('usuario', usuario);
            formData.append('password', password);
            
            const response = await fetch('admin/verificar_login.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert('¡Bienvenido ' + data.nombre + '!');
                modal.classList.remove('active');
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    location.reload();
                }
            } else {
                alert('Error: ' + data.message);
                if(loginPassword) loginPassword.value = '';
                if(loginPassword) loginPassword.focus();
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Error al conectar con el servidor. Verifica que el archivo admin/verificar_login.php exista.');
        } finally {
            btnAcceder.textContent = textoOriginal;
            btnAcceder.disabled = false;
        }
    });
}

// Login con Enter
if(loginPassword) {
    loginPassword.addEventListener('keypress', (e) => {
        if (e.key === 'Enter' && btnAcceder && !btnAcceder.disabled) {
            btnAcceder.click();
        }
    });
    
    if(loginUsuario) {
        loginUsuario.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && loginPassword) {
                loginPassword.focus();
            }
        });
    }
}

// ========== SLIDER HERO ==========
const images = document.querySelectorAll(".hero-bg img");
const textos = [
    "Lunes a Sábado: 8:00am - 6:00pm",
    "Tecnología de punta para el cuidado de tu mascota",
    "Diagnósticos precisos con atención especializada",
    "Cuidamos a tu mejor amigo como parte de nuestra familia",
    "Comprometidos con la salud y bienestar animal"
];
const heroTexto = document.getElementById("hero-texto");
let slideIndex = 0;

function cambiarSlide() {
    if(images.length === 0) return;
    images[slideIndex].classList.remove("active");
    slideIndex = (slideIndex + 1) % images.length;
    images[slideIndex].classList.add("active");
    if(heroTexto) {
        heroTexto.style.opacity = '0';
        setTimeout(() => {
            heroTexto.innerText = textos[slideIndex % textos.length];
            heroTexto.style.opacity = '1';
        }, 300);
    }
}

if(images.length > 0) {
    setInterval(cambiarSlide, 7000);
}

// ========== SCROLL SUAVE ==========
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if(target) {
            target.scrollIntoView({ behavior: 'smooth' });
            if(menu) menu.classList.remove('active');
            if(overlay) overlay.classList.remove('active');
        }
    });
});
console.log('JavaScript cargado correctamente');