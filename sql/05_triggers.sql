-- Trigger: Al insertar un detalle de cita, verificar que la cita esté pendiente
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

-- Trigger: Al confirmar una cita, actualizar automáticamente el monto total si no se fijó
DELIMITER $$
CREATE TRIGGER before_update_cita_estado
BEFORE UPDATE ON CITA
FOR EACH ROW
BEGIN
    IF NEW.estado = 'confirmada' AND OLD.estado = 'pendiente' THEN
        -- Aquí podrías hacer alguna acción adicional si es necesario
        -- Por ejemplo, registrar en un log o enviar notificación
        SET NEW.notas = CONCAT(IFNULL(OLD.notas, ''), ' [Confirmada el ', NOW(), ']');
    END IF;
END$$
DELIMITER ;