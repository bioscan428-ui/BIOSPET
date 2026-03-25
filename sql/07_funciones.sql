-- Función: Calcular edad de mascota en años
DELIMITER $$
CREATE FUNCTION edad_mascota(p_fecha_nacimiento DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    IF p_fecha_nacimiento IS NULL THEN
        RETURN NULL;
    END IF;
    RETURN TIMESTAMPDIFF(YEAR, p_fecha_nacimiento, CURDATE());
END$$
DELIMITER ;

-- Función: Obtener total de citas de un cliente
DELIMITER $$
CREATE FUNCTION total_citas_cliente(p_cliente_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(DISTINCT c.id) INTO total
    FROM CITA c
    JOIN MASCOTA m ON c.id_mascota = m.id
    WHERE m.id_cliente = p_cliente_id;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

SELECT nombre_mascota, edad_mascota(fecha_nacimiento) FROM MASCOTA;

CALL reporte_ingresos('2024-01-01', CURDATE());
