-- Procedimiento: Cancelar una cita y registrar motivo
DELIMITER $$
CREATE PROCEDURE cancelar_cita(
    IN p_cita_id INT,
    IN p_motivo VARCHAR(255)
)
BEGIN
    DECLARE estado_actual VARCHAR(20);
    
    -- Obtener estado actual
    SELECT estado INTO estado_actual FROM CITA WHERE id = p_cita_id;
    
    -- Validar que se pueda cancelar
    IF estado_actual NOT IN ('pendiente', 'confirmada') THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Solo se pueden cancelar citas pendientes o confirmadas';
    END IF;
    
    -- Actualizar cita
    UPDATE CITA 
    SET estado = 'cancelada',
        notas = CONCAT(IFNULL(notas, ''), ' [Cancelada: ', p_motivo, ' el ', NOW(), ']')
    WHERE id = p_cita_id;
    
    -- Retornar resultado
    SELECT 'Cita cancelada exitosamente' AS mensaje;
END$$
DELIMITER ;

-- Procedimiento: Reporte de ingresos por rango de fechas
DELIMITER $$
CREATE PROCEDURE reporte_ingresos(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    SELECT 
        DATE(c.fecha_cita) AS fecha,
        COUNT(DISTINCT c.id) AS total_citas,
        COUNT(dc.id) AS total_servicios,
        SUM(dc.precio_fijado) AS ingresos_totales
    FROM CITA c
    JOIN DETALLE_CITA dc ON c.id = dc.id_cita
    WHERE c.fecha_cita BETWEEN p_fecha_inicio AND p_fecha_fin
      AND c.estado IN ('confirmada', 'completada')
    GROUP BY DATE(c.fecha_cita)
    ORDER BY fecha ASC;
END$$
DELIMITER ;

-- Procedimiento: Obtener citas de una mascota
DELIMITER $$
CREATE PROCEDURE citas_por_mascota(
    IN p_mascota_id INT
)
BEGIN
    SELECT 
        c.id,
        c.fecha_cita,
        c.hora_cita,
        c.estado,
        GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
        SUM(dc.precio_fijado) AS total
    FROM CITA c
    JOIN DETALLE_CITA dc ON c.id = dc.id_cita
    JOIN SERVICIO s ON dc.id_servicio = s.id
    WHERE c.id_mascota = p_mascota_id
    GROUP BY c.id
    ORDER BY c.fecha_cita DESC;
END$$
DELIMITER ;

CALL cancelar_cita(1, 'Cliente no se presentó');
