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

DELIMITER $$
CREATE TRIGGER after_insert_detalle_compra
AFTER INSERT ON DETALLE_COMPRA
FOR EACH ROW
BEGIN
    UPDATE PRODUCTO 
    SET stock_actual = stock_actual + NEW.cantidad
    WHERE id = NEW.id_producto;
    
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

DELIMITER $$
CREATE TRIGGER after_insert_detalle_venta
AFTER INSERT ON DETALLE_VENTA
FOR EACH ROW
BEGIN
    DECLARE stock_actual INT;
    DECLARE id_empleado_venta INT;
    
    SELECT stock_actual INTO stock_actual FROM PRODUCTO WHERE id = NEW.id_producto;
    
    IF stock_actual < NEW.cantidad THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Stock insuficiente para realizar la venta';
    END IF;
    
    UPDATE PRODUCTO 
    SET stock_actual = stock_actual - NEW.cantidad
    WHERE id = NEW.id_producto;
    
    SELECT id_empleado INTO id_empleado_venta FROM VENTA WHERE id = NEW.id_venta;
    
    INSERT INTO MOVIMIENTO_INVENTARIO 
        (id_producto, tipo, cantidad, motivo, referencia, id_empleado)
    VALUES 
        (NEW.id_producto, 'salida', NEW.cantidad, 
         CONCAT('Venta #', NEW.id_venta), 
         CONCAT('VENTA-', NEW.id_venta), 
         id_empleado_venta);
END$$
DELIMITER ;

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
    
    IF OLD.estado = 'completada' AND NEW.estado = 'cancelada' THEN
        OPEN cur;
        read_loop: LOOP
            FETCH cur INTO prod_id, cant;
            IF done THEN
                LEAVE read_loop;
            END IF;
            UPDATE PRODUCTO SET stock_actual = stock_actual + cant WHERE id = prod_id;
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

DELIMITER $$
CREATE TRIGGER after_insert_factura_update_venta
AFTER INSERT ON FACTURA
FOR EACH ROW
BEGIN
    UPDATE VENTA SET tipo_comprobante = 'factura' WHERE id = NEW.id_venta;
END$$
DELIMITER ;

