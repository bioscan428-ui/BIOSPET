-- 1. CLIENTE (con activo para borrado lógico)
CREATE TABLE CLIENTE (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ape_pat VARCHAR(50),
    ape_mat VARCHAR(50),
    telefono VARCHAR(15),
    email VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,  
    INDEX (telefono)
);

-- 2. MASCOTA (con activo para borrado lógico)
CREATE TABLE MASCOTA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    nombre_mascota VARCHAR(100) NOT NULL,
    especie ENUM('Canino', 'Felino', 'Ave', 'Reptil', 'Otro') NOT NULL,
    raza VARCHAR(50),
    fecha_nacimiento DATE,
    activo BOOLEAN DEFAULT TRUE,  -- ← NUEVO
    CONSTRAINT fk_cliente FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id) ON DELETE RESTRICT 
);

-- 3. SERVICIO 
CREATE TABLE SERVICIO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio VARCHAR(100) NOT NULL,
    descripcion TEXT,
    duracion INT,
    precio DECIMAL(10,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

-- 4. CITA (historial, no se borra)
CREATE TABLE CITA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha_cita DATE NOT NULL,
    hora_cita TIME NOT NULL,
    id_mascota INT NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada', 'completada') DEFAULT 'pendiente',
    notas TEXT,
    INDEX (fecha_cita),
    CONSTRAINT fk_cita_mascota FOREIGN KEY (id_mascota) REFERENCES MASCOTA(id) ON DELETE RESTRICT,  -- ← CAMBIADO
);

--5 DETALLE_CITA (Por si se solicita mas de un estudio)
CREATE TABLE DETALLE_CITA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL,
    id_servicio INT NOT NULL,
    precio_fijado DECIMAL(10,2) NOT NULL, -- El precio en ese momento
    CONSTRAINT fk_detalle_cita FOREIGN KEY (id_cita) REFERENCES CITA(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_servicio FOREIGN KEY (id_servicio) REFERENCES SERVICIO(id)
);
