-----EVITA AGREGAR SERVICIOS A CITAS NO PENDIENTES-----
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

------REGISTRA FECHA/HORA DE CONFIRMACION-----
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

-----REGISTRA FECHA/HORA DE CANCELACION
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

-----BLOQUEA MODIFICACION DE CITAS COMPLETADAS
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

-----ACTUALIZA STOCK Y REGISTRA MOVIMIENTO-----
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



-----RESTAURA STOCK AL CANCELAR VENTA-----
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

-----EVITA ELIMINAR PRODUCTOS CON HISTORIAL-----
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

-----EVITA ELIMINAR EMPLEADOS CON CITAS-----
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

-----Sincroniza estado USUARIO con EMPLEADO-----
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

-----Sincroniza estado USUARIO con EMPLEADO-----
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

-----VALIDA LA FECHA DE NACIMIENTO-----
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

-----VALIDA QUE EL CLIENTE ESTE ACTIVO-----
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

-----EVITA DUPLICAR FACTURAS-----
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

-----ACTUALIZA TIPO DE COMPROBANTE-----
DELIMITER $$
CREATE TRIGGER after_insert_factura_update_venta
AFTER INSERT ON FACTURA
FOR EACH ROW
BEGIN
    UPDATE VENTA SET tipo_comprobante = 'factura' WHERE id = NEW.id_venta;
END$$
DELIMITER ;


-----TRIGGER PARA SUMAR PUNTOS CUANDO SE PAGA UNA CITA
DELIMITER $$

DROP TRIGGER IF EXISTS after_update_cita_pagada$$

CREATE TRIGGER after_update_cita_pagada
AFTER UPDATE ON CITA
FOR EACH ROW
BEGIN
    DECLARE v_puntos INT;
    DECLARE v_puntos_por_gasto DECIMAL(10,2);
    DECLARE v_saldo_actual INT;
    DECLARE v_total_cita DECIMAL(10,2);
    DECLARE v_id_cliente INT;
    
    -- Solo cuando cambia de no pagada a pagada
    IF NEW.pagada = 1 AND OLD.pagada = 0 THEN
        
        -- Obtener el ID del cliente desde la mascota
        SELECT id_cliente INTO v_id_cliente FROM MASCOTA WHERE id = NEW.id_mascota;
        
        -- Calcular total de la cita desde DETALLE_CITA
        SELECT IFNULL(SUM(precio_fijado), 0) INTO v_total_cita 
        FROM DETALLE_CITA 
        WHERE id_cita = NEW.id;
        
        -- Obtener puntos por gasto de la configuración actual
        SELECT puntos_por_gasto INTO v_puntos_por_gasto 
        FROM PROGRAMA_FIDELIDAD 
        WHERE activo = 1 
        ORDER BY id DESC LIMIT 1;
        
        -- Calcular puntos (cada $10 = puntos_por_gasto)
        IF v_puntos_por_gasto > 0 AND v_total_cita > 0 THEN
            SET v_puntos = FLOOR(v_total_cita / v_puntos_por_gasto);
        ELSE
            SET v_puntos = 0;
        END IF;
        
        IF v_puntos > 0 THEN
            -- Obtener saldo actual del cliente
            SELECT puntos_actuales INTO v_saldo_actual 
            FROM CLIENTE_PUNTOS 
            WHERE id_cliente = v_id_cliente;
            
            -- Si no hay registro, crear uno
            IF v_saldo_actual IS NULL THEN
                INSERT INTO CLIENTE_PUNTOS (id_cliente, puntos_actuales, puntos_acumulados_historial)
                VALUES (v_id_cliente, v_puntos, v_puntos);
                SET v_saldo_actual = 0;
            ELSE
                UPDATE CLIENTE_PUNTOS 
                SET puntos_actuales = puntos_actuales + v_puntos,
                    puntos_acumulados_historial = puntos_acumulados_historial + v_puntos,
                    ultima_actualizacion = NOW()
                WHERE id_cliente = v_id_cliente;
            END IF;
            
            -- Registrar movimiento de puntos
            INSERT INTO MOVIMIENTO_PUNTOS (id_cliente, id_venta, tipo, puntos, saldo_antes, saldo_despues, concepto, fecha_vencimiento)
            VALUES (v_id_cliente, NULL, 'ganados', v_puntos, 
                    IFNULL(v_saldo_actual, 0), IFNULL(v_saldo_actual, 0) + v_puntos,
                    CONCAT('Pago de cita #', NEW.id),
                    DATE_ADD(NOW(), INTERVAL 1 YEAR));
        END IF;
    END IF;
END$$

DELIMITER ;

-- Trigger para sumar puntos cuando se completa una venta de productos
DELIMITER $$
CREATE TRIGGER after_insert_venta_puntos
AFTER INSERT ON VENTA
FOR EACH ROW
BEGIN
    DECLARE v_puntos INT;
    DECLARE v_puntos_por_gasto DECIMAL(10,2);
    DECLARE v_saldo_actual INT;
    
    -- Solo si la venta está completada
    IF NEW.estado = 'completada' THEN
        -- Obtener puntos por gasto
        SELECT puntos_por_gasto INTO v_puntos_por_gasto 
        FROM PROGRAMA_FIDELIDAD 
        WHERE activo = 1 
        ORDER BY id DESC LIMIT 1;
        
        -- Calcular puntos
        SET v_puntos = FLOOR(NEW.total / v_puntos_por_gasto);
        
        -- Obtener saldo actual
        SELECT puntos_actuales INTO v_saldo_actual 
        FROM CLIENTE_PUNTOS 
        WHERE id_cliente = NEW.id_cliente;
        
        IF v_puntos > 0 THEN
            IF v_saldo_actual IS NULL THEN
                INSERT INTO CLIENTE_PUNTOS (id_cliente, puntos_actuales, puntos_acumulados_historial)
                VALUES (NEW.id_cliente, v_puntos, v_puntos);
            ELSE
                UPDATE CLIENTE_PUNTOS 
                SET puntos_actuales = puntos_actuales + v_puntos,
                    puntos_acumulados_historial = puntos_acumulados_historial + v_puntos
                WHERE id_cliente = NEW.id_cliente;
            END IF;
            
            -- Registrar movimiento
            INSERT INTO MOVIMIENTO_PUNTOS (id_cliente, id_venta, tipo, puntos, saldo_antes, saldo_despues, concepto, fecha_vencimiento)
            VALUES (NEW.id_cliente, NEW.id, 'ganados', v_puntos, 
                    IFNULL(v_saldo_actual, 0), IFNULL(v_saldo_actual, 0) + v_puntos,
                    CONCAT('Compra #', NEW.id),
                    DATE_ADD(NOW(), INTERVAL 1 YEAR));
        END IF;
    END IF;
END$$
DELIMITER ;