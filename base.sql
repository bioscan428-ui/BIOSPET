-- 1. Tabla DUEÑO (Información de contacto)
CREATE TABLE CLIENTE (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    ape_pat VARCHAR(100),
    ape_mat VARCHAR(100),
    telefono VARCHAR(12),
    email VARCHAR(100)
);

-- 2. Tabla MASCOTA (Vinculada al dueño)
CREATE TABLE MASCOTA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    nombre_mascota VARCHAR(100) NOT NULL,
    especie ENUM('Canino', 'Felino', 'Ave', 'Reptil', 'Otro') NOT NULL,
    raza VARCHAR(50),
    fecha_nacimiento DATE,
    CONSTRAINT fk_cliente FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id_cliente)
);

-- 3. Tabla SERVICIO (Se mantiene igual, solo asegúrate de llenar el precio)
CREATE TABLE SERVICIO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio ENUM('Tomografia', 'Rayos X', 'Ultrasonido', 'Electrocardiograma') NOT NULL,
    descripcion TEXT,
    duracion INT, -- minutos
    precio DECIMAL(10,2) NOT NULL,
    activo BOOLEAN DEFAULT TRUE
);

-- 4. Tabla CITA (Ahora apunta a la mascota)
CREATE TABLE CITA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha_cita DATE NOT NULL,
    hora_cita TIME NOT NULL,
    id_mascota INT NOT NULL,
    id_servicio INT NOT NULL,
    estado ENUM('pendiente', 'confirmada', 'cancelada', 'completada') DEFAULT 'pendiente',
    monto_cobrado DECIMAL(10,2), -- Guardamos el precio al momento de la cita
    notas TEXT,
    CONSTRAINT fk_cita_mascota FOREIGN KEY (id_mascota) REFERENCES MASCOTA(id),
    CONSTRAINT fk_cita_servicio FOREIGN KEY (id_servicio) REFERENCES SERVICIO(id)
);


CREATE TABLE 