-- TABLA 1. CLIENTE--
CREATE INDEX idx_cliente_email ON CLIENTE(email);

--TABLA 2. MASCOTA--
CREATE INDEX idx_mascota_nombre ON MASCOTA(nombre_mascota);

--TABLA 4. CITA--
CREATE INDEX idx_cita_fecha_estado ON CITA(fecha_cita, estado);

-- TABLA 5. DETALLE_CITA--
CREATE INDEX idx_detalle_servicio ON DETALLE_CITA(id_servicio);

--A PARTIR DE AQUI AUN NO ESTAN CREADOS

--TABLA 6. EMPLEADO--
INDEX (puesto),
INDEX (email)

--TABLA 7. USUARIO--
-- Índice para búsquedas por nombre de usuario (ya es UNIQUE, pero para login)
CREATE INDEX idx_usuario_nombre ON USUARIO(nombre_usuario);

-- Índice para búsquedas por rol
CREATE INDEX idx_usuario_rol ON USUARIO(rol);

--TABLA 8. HORARIO_EMPLEADO--
CREATE INDEX idx_horario_dia ON HORARIO_EMPLEADO(dia_semana);

--TABLA 9. ASIGNACION_CITA--
-- Índice para búsquedas por empleado (consultar citas de un veterinario)
CREATE INDEX idx_asignacion_empleado ON ASIGNACION_CITA(id_empleado);

-- Índice para búsquedas por cita (ya existe FK, pero mejora)
CREATE INDEX idx_asignacion_cita ON ASIGNACION_CITA(id_cita);

--TABLA 10. CATEGORIA_PRODUCTO--
-- Índice para búsquedas por nombre de categoría
CREATE INDEX idx_categoria_nombre ON CATEGORIA_PRODUCTO(nombre);

--TABLA 11. PROVEEDOR--
-- Índice para búsquedas por teléfono
CREATE INDEX idx_proveedor_telefono ON PROVEEDOR(telefono);

--TABLA 12. PRODUCTO--
-- Índice para búsquedas por categoría
CREATE INDEX idx_producto_categoria ON PRODUCTO(id_categoria);

-- Índice para búsquedas de productos con stock bajo
CREATE INDEX idx_producto_stock ON PRODUCTO(stock_actual, stock_minimo);

-- Índice para búsquedas por fecha de vencimiento
CREATE INDEX idx_producto_vencimiento ON PRODUCTO(fecha_vencimiento);

--TABLA 13. MOVIMIENTO_INVENTARIO--
-- Índices que ya tienes: INDEX (id_producto), INDEX (fecha_movimiento)

-- Índice para búsquedas por tipo de movimiento
CREATE INDEX idx_movimiento_tipo ON MOVIMIENTO_INVENTARIO(tipo);

-- Índice para búsquedas por empleado
CREATE INDEX idx_movimiento_empleado ON MOVIMIENTO_INVENTARIO(id_empleado);

--TABLA 14. COMPRA--
-- Índice para búsquedas por proveedor
CREATE INDEX idx_compra_proveedor ON COMPRA(id_proveedor);

-- Índice para búsquedas por fecha
CREATE INDEX idx_compra_fecha ON COMPRA(fecha_compra);

-- Índice para búsquedas por empleado
CREATE INDEX idx_compra_empleado ON COMPRA(id_empleado);

--TABLA 15. DETALLE_COMPRA--
-- Índice para búsquedas por compra
CREATE INDEX idx_detalle_compra ON DETALLE_COMPRA(id_compra);

-- Índice para búsquedas por producto
CREATE INDEX idx_detalle_compra_producto ON DETALLE_COMPRA(id_producto);

--TABLA 16. SERVICIO_PRODUCTO--
-- Índice para búsquedas por servicio
CREATE INDEX idx_servicio_producto_servicio ON SERVICIO_PRODUCTO(id_servicio);

-- Índice para búsquedas por producto
CREATE INDEX idx_servicio_producto_producto ON SERVICIO_PRODUCTO(id_producto);

--TABLA 17. VENTA--
-- Índice para búsquedas por empleado
CREATE INDEX idx_venta_empleado ON VENTA(id_empleado);

-- Índice para búsquedas por estado
CREATE INDEX idx_venta_estado ON VENTA(estado);

-- Índice para búsquedas por método de pago
CREATE INDEX idx_venta_metodo_pago ON VENTA(metodo_pago);

--TABLA 18. DETALLE_VENTA--
-- Índice para búsquedas por producto
CREATE INDEX idx_detalle_venta_producto ON DETALLE_VENTA(id_producto);

--TABLA 19. PAGO--
-- Índice para búsquedas por venta
CREATE INDEX idx_pago_venta ON PAGO(id_venta);

-- Índice para búsquedas por fecha
CREATE INDEX idx_pago_fecha ON PAGO(fecha_pago);

-- Índice para búsquedas por método de pago
CREATE INDEX idx_pago_metodo ON PAGO(metodo_pago);


--TABLA 20. FACTURA--
-- Índice para búsquedas por venta (ya es UNIQUE, pero ayuda)
CREATE INDEX idx_factura_venta ON FACTURA(id_venta);
