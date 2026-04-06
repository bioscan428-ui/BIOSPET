-- 1. CLIENTE (con activo para borrado lógico)
CREATE TABLE CLIENTE (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ape_pat VARCHAR(50),
    ape_mat VARCHAR(50),
    telefono VARCHAR(15),
    email VARCHAR(100),
    activo BOOLEAN DEFAULT TRUE,  
    fecha_registro CURRENT_TIMESTAMP() NOT NULL,
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
    activo BOOLEAN DEFAULT TRUE,
    genero ENUM('MACHO', 'HEMBRA'),
    foto VARCHAR(500),
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

-- ============================================
-- TABLAS PARA EMPLEADOS
-- ============================================

-- 6. EMPLEADO (personal de la clínica)
CREATE TABLE EMPLEADO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ape_pat VARCHAR(50),
    ape_mat VARCHAR(50),
    email VARCHAR(100) UNIQUE NOT NULL,
    telefono VARCHAR(15),
    puesto ENUM('super_admin', 'admin', 'veterinario', 'asistente', 'recepcionista') NOT NULL,
    especialidad VARCHAR(100), -- para veterinarios: 'radiología', 'cirugía', etc.
    fecha_contratacion DATE NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    INDEX (puesto),
    INDEX (email)
);

-- 7. USUARIO (para acceso al sistema, relacionado con EMPLEADO)
CREATE TABLE USUARIO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT NOT NULL,
    nombre_usuario VARCHAR(50) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL, -- hash de contraseña
    rol ENUM('admin', 'veterinario', 'asistente', 'recepcionista') NOT NULL DEFAULT 'recepcionista',
    ultimo_acceso DATETIME,
    activo BOOLEAN DEFAULT TRUE,
    reset_token VARCHAR(64),
    reset_expira DATETIME,
    CONSTRAINT fk_usuario_empleado FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id) ON DELETE RESTRICT
);

-- 8. HORARIO_EMPLEADO (horarios de trabajo)
CREATE TABLE HORARIO_EMPLEADO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT NOT NULL,
    dia_semana ENUM('lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo') NOT NULL,
    hora_entrada TIME NOT NULL,
    hora_salida TIME NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    CONSTRAINT fk_horario_empleado FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id) ON DELETE CASCADE,
    INDEX (id_empleado, dia_semana)
);

-- 9. ASIGNACION_CITA (relaciona citas con empleados)
CREATE TABLE ASIGNACION_CITA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cita INT NOT NULL,
    id_empleado INT NOT NULL,
    rol_asignado ENUM('veterinario', 'asistente') NOT NULL,
    CONSTRAINT fk_asignacion_cita FOREIGN KEY (id_cita) REFERENCES CITA(id) ON DELETE CASCADE,
    CONSTRAINT fk_asignacion_empleado FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id) ON DELETE RESTRICT,
    UNIQUE KEY (id_cita, id_empleado, rol_asignado)
);


-- ============================================
-- TABLAS PARA INVENTARIO
-- ============================================

-- 10. CATEGORIA_PRODUCTO
CREATE TABLE CATEGORIA_PRODUCTO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
);

-- 11. PROVEEDOR
CREATE TABLE PROVEEDOR (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    contacto_nombre VARCHAR(100),
    telefono VARCHAR(15),
    email VARCHAR(100),
    direccion TEXT,
    activo BOOLEAN DEFAULT TRUE,
    INDEX (nombre)
);

-- 12. PRODUCTO (inventario)
CREATE TABLE PRODUCTO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    codigo_barras VARCHAR(50) UNIQUE,
    id_categoria INT NOT NULL,
    precio_compra DECIMAL(10,2) NOT NULL,
    precio_venta DECIMAL(10,2) NOT NULL,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT DEFAULT 5,
    unidad_medida VARCHAR(20) DEFAULT 'pieza',
    ubicacion VARCHAR(200),
    fecha_vencimiento DATE,
    activo BOOLEAN DEFAULT TRUE,
    imagen VARCHAR(500),
    CONSTRAINT fk_producto_categoria FOREIGN KEY (id_categoria) REFERENCES CATEGORIA_PRODUCTO(id) ON DELETE RESTRICT,
    INDEX (nombre),
    INDEX (codigo_barras)
);

-- 13. MOVIMIENTO_INVENTARIO (historial de entradas/salidas)
CREATE TABLE MOVIMIENTO_INVENTARIO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_empleado INT NOT NULL,
    tipo ENUM('entrada', 'salida', 'ajuste', 'devolucion') NOT NULL,
    cantidad INT NOT NULL,
    motivo TEXT,
    referencia VARCHAR(100), -- factura, receta, etc.
    id_producto INT NOT NULL,
    fecha_movimiento DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movimiento_empleado FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id) ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_producto FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id) ON DELETE RESTRICT,

    INDEX (id_producto),
    INDEX (fecha_movimiento)
);

-- 14. COMPRA (registro de compras a proveedores)
CREATE TABLE COMPRA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_proveedor INT NOT NULL,
    fecha_compra DATE NOT NULL,
    folio_factura VARCHAR(50),
    total DECIMAL(10,2) NOT NULL,
    id_empleado INT NOT NULL,
    CONSTRAINT fk_compra_proveedor FOREIGN KEY (id_proveedor) REFERENCES PROVEEDOR(id) ON DELETE RESTRICT,
    CONSTRAINT fk_compra_empleado FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id) ON DELETE RESTRICT
);

-- 15. DETALLE_COMPRA (productos por compra)
CREATE TABLE DETALLE_COMPRA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_compra INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalle_compra FOREIGN KEY (id_compra) REFERENCES COMPRA(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id) ON DELETE RESTRICT
);

-- 16. SERVICIO_PRODUCTO (relación servicios con productos consumibles)
CREATE TABLE SERVICIO_PRODUCTO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_servicio INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad_requerida INT NOT NULL DEFAULT 1,
    CONSTRAINT fk_servicio_producto_servicio FOREIGN KEY (id_servicio) REFERENCES SERVICIO(id) ON DELETE CASCADE,
    CONSTRAINT fk_servicio_producto_producto FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id) ON DELETE RESTRICT
);

-- ============================================
-- TABLAS PARA VENTAS (punto de venta)
-- ============================================

-- 17. VENTA (cabecera de la venta)
CREATE TABLE VENTA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    id_empleado INT NULL,
    fecha_venta DATETIME DEFAULT CURRENT_TIMESTAMP,
    tipo_comprobante ENUM('ticket', 'factura') DEFAULT 'ticket',
    folio VARCHAR(50),
    subtotal DECIMAL(10,2) NOT NULL,
    iva DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia', 'credito') NOT NULL,
    estado ENUM('completada', 'cancelada', 'pendiente') DEFAULT 'completada',
    notas TEXT,
    CONSTRAINT fk_venta_cliente FOREIGN KEY (id_cliente) REFERENCES CLIENTE(id) ON DELETE RESTRICT,
    CONSTRAINT fk_venta_empleado FOREIGN KEY (id_empleado) REFERENCES EMPLEADO(id) ON DELETE RESTRICT,
    INDEX (fecha_venta),
    INDEX (id_cliente)
);

-- 18. DETALLE_VENTA (productos vendidos)
CREATE TABLE DETALLE_VENTA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_detalle_venta FOREIGN KEY (id_venta) REFERENCES VENTA(id) ON DELETE CASCADE,
    CONSTRAINT fk_detalle_venta_producto FOREIGN KEY (id_producto) REFERENCES PRODUCTO(id) ON DELETE RESTRICT,
    INDEX (id_venta)
);

-- 19. PAGO (registro de pagos, útil para créditos o pagos parciales)
CREATE TABLE PAGO (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia') NOT NULL,
    referencia VARCHAR(100),
    CONSTRAINT fk_pago_venta FOREIGN KEY (id_venta) REFERENCES VENTA(id) ON DELETE CASCADE
);

-- 20. FACTURA (si se requiere facturación CFDI)
CREATE TABLE FACTURA (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_venta INT NOT NULL,
    rfc VARCHAR(13) NOT NULL,
    razon_social VARCHAR(100) NOT NULL,
    regimen_fiscal VARCHAR(50),
    uso_cfdi VARCHAR(50),
    uuid VARCHAR(36) UNIQUE, -- UUID único por factura (evita duplicados)
    fecha_timbrado DATETIME,
    xml TEXT,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_factura_venta FOREIGN KEY (id_venta) REFERENCES VENTA(id) ON DELETE CASCADE,
    CONSTRAINT uk_factura_venta UNIQUE (id_venta) -- Una venta solo puede tener una factura
    
);


