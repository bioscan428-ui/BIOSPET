-- ============================================
-- TRIGGERS PARA CITAS Y SERVICIOS
-- ============================================

-- Trigger: Al insertar un detalle de cita, verificar que la cita esté pendiente (YA LO TIENES)
DELIMITER $$
CREATE TRIGGER before_insert_detalle_cita
BEFORE INSERT ON DETALLE_CITA
FOR EACH ROW
BEGIN
    DECLARE estado_cita VARCHAR(20);
    
    SELECT estado INTO estado_cita FROM CITA WHERE id = NEW.id_cita;
    
    IF estado_cita != 'pendiente' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No se pueden agregar servicios a una cita que no está pendiente';
    END IF;
END$$
DELIMITER ;

-- Trigger: Al confirmar una cita, registrar en notas (YA LO TIENES)
DELIMITER $$
CREATE TRIGGER before_update_cita_estado
BEFORE UPDATE ON CITA
FOR EACH ROW
BEGIN
    IF NEW.estado = 'confirmada' AND OLD.estado = 'pendiente' THEN
        SET NEW.notas = CONCAT(IFNULL(OLD.notas, ''), ' [Confirmada el ', NOW(), ']');
    END IF;
END$$
DELIMITER ;

-- Trigger: Al cancelar una cita, registrar motivo si viene en notas
DELIMITER $$
CREATE TRIGGER before_update_cita_cancelar
BEFORE UPDATE ON CITA
FOR EACH ROW
BEGIN
    IF NEW.estado = 'cancelada' AND OLD.estado != 'cancelada' THEN
        IF NEW.notas IS NULL OR NEW.notas = OLD.notas THEN
            SET NEW.notas = CONCAT(IFNULL(OLD.notas, ''), ' [Cancelada el ', NOW(), ']');
        ELSE
            SET NEW.notas = CONCAT(IFNULL(OLD.notas, ''), ' [Cancelada el ', NOW(), ': ', NEW.notas, ']');
        END IF;
    END IF;
END$$
DELIMITER ;

-- Trigger: Al completar una cita, no permitir modificaciones posteriores
DELIMITER $$
CREATE TRIGGER before_update_cita_completada
BEFORE UPDATE ON CITA
FOR EACH ROW
BEGIN
    IF OLD.estado = 'completada' AND NEW.estado != 'completada' THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No se puede modificar una cita ya completada';
    END IF;
END$$
DELIMITER ;


-- ============================================
-- TRIGGERS PARA INVENTARIO
-- ============================================

-- Trigger: Al insertar un detalle de compra, actualizar stock automáticamente
DELIMITER $$
CREATE TRIGGER after_insert_detalle_compra
AFTER INSERT ON DETALLE_COMPRA
FOR EACH ROW
BEGIN
    -- Actualizar stock del producto
    UPDATE PRODUCTO 
    SET stock_actual = stock_actual + NEW.cantidad
    WHERE id = NEW.id_producto;
    
    -- Registrar movimiento de inventario (entrada por compra)
    INSERT INTO MOVIMIENTO_INVENTARIO 
        (id_producto, tipo, cantidad, motivo, referencia, id_empleado)
    SELECT 
        NEW.id_producto, 
        'entrada', 
        NEW.cantidad, 
        CONCAT('Compra #', NEW.id_compra),
        c.folio_factura,
        c.id_empleado
    FROM COMPRA c
    WHERE c.id = NEW.id_compra;
END$$
DELIMITER ;

-- Trigger: Al insertar un detalle de venta, actualizar stock automáticamente
DELIMITER $$
CREATE TRIGGER after_insert_detalle_venta
AFTER INSERT ON DETALLE_VENTA
FOR EACH ROW
BEGIN
    DECLARE stock_actual INT;
    DECLARE id_empleado_venta INT;
    
    -- Obtener stock actual
    SELECT stock_actual INTO stock_actual FROM PRODUCTO WHERE id = NEW.id_producto;
    
    -- Validar stock suficiente
    IF stock_actual < NEW.cantidad THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Stock insuficiente para realizar la venta';
    END IF;
    
    -- Actualizar stock del producto
    UPDATE PRODUCTO 
    SET stock_actual = stock_actual - NEW.cantidad
    WHERE id = NEW.id_producto;
    
    -- Obtener empleado de la venta
    SELECT id_empleado INTO id_empleado_venta FROM VENTA WHERE id = NEW.id_venta;
    
    -- Registrar movimiento de inventario (salida por venta)
    INSERT INTO MOVIMIENTO_INVENTARIO 
        (id_producto, tipo, cantidad, motivo, referencia, id_empleado)
    VALUES 
        (NEW.id_producto, 'salida', NEW.cantidad, 
         CONCAT('Venta #', NEW.id_venta), 
         CONCAT('VENTA-', NEW.id_venta), 
         id_empleado_venta);
END$$
DELIMITER ;

-- Trigger: Al cancelar una venta, restaurar stock
DELIMITER $$
CREATE TRIGGER after_update_venta_cancelar
AFTER UPDATE ON VENTA
FOR EACH ROW
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE prod_id INT;
    DECLARE cant INT;
    DECLARE cur CURSOR FOR 
        SELECT id_producto, cantidad FROM DETALLE_VENTA WHERE id_venta = NEW.id;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Si la venta cambió de completada a cancelada
    IF OLD.estado = 'completada' AND NEW.estado = 'cancelada' THEN
        OPEN cur;
        read_loop: LOOP
            FETCH cur INTO prod_id, cant;
            IF done THEN
                LEAVE read_loop;
            END IF;
            -- Restaurar stock
            UPDATE PRODUCTO SET stock_actual = stock_actual + cant WHERE id = prod_id;
            -- Registrar movimiento de reversión
            INSERT INTO MOVIMIENTO_INVENTARIO 
                (id_producto, tipo, cantidad, motivo, referencia, id_empleado)
            VALUES 
                (prod_id, 'ajuste', cant, 
                 CONCAT('Cancelación de venta #', NEW.id), 
                 CONCAT('CANCELACION-', NEW.id), 
                 NEW.id_empleado);
        END LOOP;
        CLOSE cur;
    END IF;
END$$
DELIMITER ;

-- Trigger: Evitar eliminar productos que tienen movimientos
DELIMITER $$
CREATE TRIGGER before_delete_producto
BEFORE DELETE ON PRODUCTO
FOR EACH ROW
BEGIN
    DECLARE total_movimientos INT;
    
    SELECT COUNT(*) INTO total_movimientos FROM MOVIMIENTO_INVENTARIO WHERE id_producto = OLD.id;
    
    IF total_movimientos > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No se puede eliminar un producto con historial de movimientos';
    END IF;
END$$
DELIMITER ;


-- ============================================
-- TRIGGERS PARA EMPLEADOS
-- ============================================

-- Trigger: Al crear un empleado, crear automáticamente su usuario si no existe
DELIMITER $$
CREATE TRIGGER after_insert_empleado
AFTER INSERT ON EMPLEADO
FOR EACH ROW
BEGIN
    -- Crear usuario por defecto (nombre de usuario = email)
    INSERT INTO USUARIO (id_empleado, nombre_usuario, contrasena, rol)
    VALUES (
        NEW.id, 
        NEW.email, 
        '$2y$10$default_hash_para_cambiar', -- contraseña temporal
        CASE 
            WHEN NEW.puesto = 'veterinario' THEN 'veterinario'
            WHEN NEW.puesto = 'administrador' THEN 'admin'
            ELSE 'recepcionista'
        END
    );
END$$
DELIMITER ;

-- Trigger: Evitar eliminar empleado con citas asignadas
DELIMITER $$
CREATE TRIGGER before_delete_empleado
BEFORE DELETE ON EMPLEADO
FOR EACH ROW
BEGIN
    DECLARE total_citas INT;
    
    SELECT COUNT(*) INTO total_citas FROM ASIGNACION_CITA WHERE id_empleado = OLD.id;
    
    IF total_citas > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No se puede eliminar un empleado con citas asignadas';
    END IF;
END$$
DELIMITER ;

-- Trigger: Al desactivar empleado, desactivar su usuario
DELIMITER $$
CREATE TRIGGER after_update_empleado_estado
AFTER UPDATE ON EMPLEADO
FOR EACH ROW
BEGIN
    IF NEW.activo = 0 AND OLD.activo = 1 THEN
        UPDATE USUARIO SET activo = 0 WHERE id_empleado = NEW.id;
    END IF;
    
    IF NEW.activo = 1 AND OLD.activo = 0 THEN
        UPDATE USUARIO SET activo = 1 WHERE id_empleado = NEW.id;
    END IF;
END$$
DELIMITER ;


-- ============================================
-- TRIGGERS PARA CLIENTES Y MASCOTAS
-- ============================================

-- Trigger: Al desactivar cliente, desactivar sus mascotas
DELIMITER $$
CREATE TRIGGER after_update_cliente_estado
AFTER UPDATE ON CLIENTE
FOR EACH ROW
BEGIN
    IF NEW.activo = 0 AND OLD.activo = 1 THEN
        UPDATE MASCOTA SET activo = 0 WHERE id_cliente = NEW.id AND activo = 1;
    END IF;
    
    IF NEW.activo = 1 AND OLD.activo = 0 THEN
        UPDATE MASCOTA SET activo = 1 WHERE id_cliente = NEW.id AND activo = 0;
    END IF;
END$$
DELIMITER ;

-- Trigger: Validar fecha de nacimiento de mascota (no puede ser futura)
DELIMITER $$
CREATE TRIGGER before_insert_mascota_fecha
BEFORE INSERT ON MASCOTA
FOR EACH ROW
BEGIN
    IF NEW.fecha_nacimiento > CURDATE() THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'La fecha de nacimiento no puede ser futura';
    END IF;
END$$
DELIMITER ;

-- Trigger: Al insertar mascota, validar que el cliente exista y esté activo
DELIMITER $$
CREATE TRIGGER before_insert_mascota_cliente
BEFORE INSERT ON MASCOTA
FOR EACH ROW
BEGIN
    DECLARE cliente_activo BOOLEAN;
    
    SELECT activo INTO cliente_activo FROM CLIENTE WHERE id = NEW.id_cliente;
    
    IF cliente_activo = 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'No se puede agregar mascota a un cliente inactivo';
    END IF;
END$$
DELIMITER ;


-- ============================================
-- TRIGGERS PARA FACTURACIÓN
-- ============================================

-- Trigger: Al insertar factura, validar que la venta no tenga factura previa
DELIMITER $$
CREATE TRIGGER before_insert_factura
BEFORE INSERT ON FACTURA
FOR EACH ROW
BEGIN
    DECLARE factura_existente INT;
    
    SELECT COUNT(*) INTO factura_existente FROM FACTURA WHERE id_venta = NEW.id_venta;
    
    IF factura_existente > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Esta venta ya tiene una factura asociada';
    END IF;
END$$
DELIMITER ;

-- Trigger: Al actualizar venta, si se genera factura, actualizar tipo_comprobante
DELIMITER $$
CREATE TRIGGER after_insert_factura_update_venta
AFTER INSERT ON FACTURA
FOR EACH ROW
BEGIN
    UPDATE VENTA SET tipo_comprobante = 'factura' WHERE id = NEW.id_venta;
END$$
DELIMITER ;