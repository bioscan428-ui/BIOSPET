-- Índice para búsquedas por email de cliente
CREATE INDEX idx_cliente_email ON CLIENTE(email);

-- Índice para búsquedas por nombre de mascota
CREATE INDEX idx_mascota_nombre ON MASCOTA(nombre_mascota);

-- Índice compuesto para citas (fecha + estado)
CREATE INDEX idx_cita_fecha_estado ON CITA(fecha_cita, estado);

-- Índice para DETALLE_CITA por servicio
CREATE INDEX idx_detalle_servicio ON DETALLE_CITA(id_servicio);

--AUN NO ESTAN CREADOS
--INDICES DE EMPLEADO
INDEX (puesto),
INDEX (email)

-- Índices para inventario
CREATE INDEX idx_producto_stock ON PRODUCTO(stock_actual, stock_minimo);
CREATE INDEX idx_movimiento_fecha ON MOVIMIENTO_INVENTARIO(fecha_movimiento);
CREATE INDEX idx_compra_fecha ON COMPRA(fecha_compra);

-- Índices para empleados
CREATE INDEX idx_empleado_puesto ON EMPLEADO(puesto, activo);
CREATE INDEX idx_usuario_rol ON USUARIO(rol, activo);