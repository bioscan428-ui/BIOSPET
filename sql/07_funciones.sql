-- ============================================
-- FUNCIONES PARA CLIENTES Y MASCOTAS
-- ============================================

-- Función: Calcular edad de mascota en años (YA LA TIENES) ✅ YA SE ESTA USANDO (detalle_cita.php)
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


-- Función: Obtener total de mascotas de un cliente ✅ YA SE ESTA USANDO (clientes.php)
DELIMITER $$
CREATE FUNCTION total_mascotas_cliente(p_cliente_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(*) INTO total
    FROM MASCOTA
    WHERE id_cliente = p_cliente_id AND activo = 1;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener última cita de una mascota  ✅ YA SE ESTA USANDO (carnet_mascota.php)
DELIMITER $$
CREATE FUNCTION ultima_cita_mascota(p_mascota_id INT)
RETURNS DATE
DETERMINISTIC
BEGIN
    DECLARE ultima DATE;
    
    SELECT MAX(fecha_cita) INTO ultima
    FROM CITA
    WHERE id_mascota = p_mascota_id;
    
    RETURN ultima;
END$$
DELIMITER ;

-- Función: Calcular edad humana equivalente (para perros)
DELIMITER $$
CREATE FUNCTION edad_humana(p_especie VARCHAR(20), p_edad INT)
RETURNS INT
DETERMINISTIC
BEGIN
    IF p_especie = 'Canino' THEN
        -- Fórmula aproximada: primeros 2 años = 21 años humanos, luego 4 años por cada año
        IF p_edad <= 2 THEN
            RETURN p_edad * 10.5;
        ELSE
            RETURN 21 + ((p_edad - 2) * 4);
        END IF;
    ELSEIF p_especie = 'Felino' THEN
        -- Gatos: primer año = 15 años, segundo = 24, luego 4 por año
        IF p_edad = 1 THEN
            RETURN 15;
        ELSEIF p_edad = 2 THEN
            RETURN 24;
        ELSE
            RETURN 24 + ((p_edad - 2) * 4);
        END IF;
    ELSE
        RETURN p_edad;
    END IF;
END$$
DELIMITER ;


-- ============================================
-- FUNCIONES PARA CITAS Y SERVICIOS
-- ============================================

-- Función: Obtener total de citas en un día ✅ YA SE ESTA USANDO (dashboard.php)
DELIMITER $$
CREATE FUNCTION total_citas_dia(p_fecha DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(*) INTO total
    FROM CITA
    WHERE fecha_cita = p_fecha;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener total de citas completadas de una mascota ✅ YA SE ESTA USANDO (historial_mascota.php)
DELIMITER $$
CREATE FUNCTION citas_completadas_mascota(p_mascota_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(*) INTO total
    FROM CITA
    WHERE id_mascota = p_mascota_id AND estado = 'completada';
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener total de servicios solicitados en una cita
DELIMITER $$
CREATE FUNCTION total_servicios_cita(p_cita_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(*) INTO total
    FROM DETALLE_CITA
    WHERE id_cita = p_cita_id;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener monto total de una cita
DELIMITER $$
CREATE FUNCTION monto_total_cita(p_cita_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    
    SELECT SUM(precio_fijado) INTO total
    FROM DETALLE_CITA
    WHERE id_cita = p_cita_id;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Verificar si una cita se puede cancelar
DELIMITER $$
CREATE FUNCTION cita_cancelable(p_cita_id INT)
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE estado_cita VARCHAR(20);
    DECLARE fecha_cita DATE;
    
    SELECT estado, fecha_cita INTO estado_cita, fecha_cita
    FROM CITA WHERE id = p_cita_id;
    
    RETURN estado_cita IN ('pendiente', 'confirmada') AND fecha_cita >= CURDATE();
END$$
DELIMITER ;


-- ============================================
-- FUNCIONES PARA EMPLEADOS
-- ============================================

-- Función: Obtener total de citas atendidas por un empleado
DELIMITER $$
CREATE FUNCTION total_citas_empleado(p_empleado_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(DISTINCT ac.id_cita) INTO total
    FROM ASIGNACION_CITA ac
    WHERE ac.id_empleado = p_empleado_id;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener horas trabajadas en la semana
DELIMITER $$
CREATE FUNCTION horas_semana_empleado(p_empleado_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total_horas INT DEFAULT 0;
    
    SELECT SUM(TIMESTAMPDIFF(HOUR, hora_entrada, hora_salida)) INTO total_horas
    FROM HORARIO_EMPLEADO
    WHERE id_empleado = p_empleado_id AND activo = 1;
    
    RETURN IFNULL(total_horas, 0);
END$$
DELIMITER ;

-- Función: Obtener antigüedad del empleado en años
DELIMITER $$
CREATE FUNCTION antiguedad_empleado(p_empleado_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE antiguedad INT;
    
    SELECT TIMESTAMPDIFF(YEAR, fecha_contratacion, CURDATE()) INTO antiguedad
    FROM EMPLEADO
    WHERE id = p_empleado_id;
    
    RETURN IFNULL(antiguedad, 0);
END$$
DELIMITER ;


-- ============================================
-- FUNCIONES PARA INVENTARIO
-- ============================================

-- Función: Verificar si hay stock suficiente
DELIMITER $$
CREATE FUNCTION stock_suficiente(p_producto_id INT, p_cantidad INT)
RETURNS BOOLEAN
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE stock_actual INT DEFAULT 0;
    
    -- Manejar valores nulos o inválidos
    IF p_producto_id IS NULL OR p_cantidad IS NULL OR p_cantidad <= 0 THEN
        RETURN FALSE;
    END IF;
    
    -- Obtener stock actual (solo productos activos)
    SELECT stock_actual INTO stock_actual
    FROM PRODUCTO
    WHERE id = p_producto_id AND activo = 1;
    
    -- Si no se encontró el producto, retornar FALSE
    IF stock_actual IS NULL THEN
        RETURN FALSE;
    END IF;
    
    RETURN stock_actual >= p_cantidad;
END$$
DELIMITER ;


-- Función: Obtener valor total del inventario
DELIMITER $$
CREATE FUNCTION valor_inventario_total()
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    
    SELECT SUM(stock_actual * precio_compra) INTO total
    FROM PRODUCTO
    WHERE activo = 1;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener productos con stock bajo
DELIMITER $$
CREATE FUNCTION productos_stock_bajo()
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(*) INTO total
    FROM PRODUCTO
    WHERE stock_actual <= stock_minimo AND activo = 1;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener precio de venta de un producto
DELIMITER $$
CREATE FUNCTION precio_venta_producto(p_producto_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE precio DECIMAL(10,2);
    
    SELECT precio_venta INTO precio
    FROM PRODUCTO
    WHERE id = p_producto_id;
    
    RETURN IFNULL(precio, 0);
END$$
DELIMITER ;


-- ============================================
-- FUNCIONES PARA VENTAS
-- ============================================

-- Función: Obtener total de ventas de un cliente
DELIMITER $$
CREATE FUNCTION total_ventas_cliente(p_cliente_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    
    SELECT SUM(total) INTO total
    FROM VENTA
    WHERE id_cliente = p_cliente_id AND estado = 'completada';
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener total de ventas del día
DELIMITER $$

DROP FUNCTION IF EXISTS ventas_dia$$

CREATE FUNCTION ventas_dia(p_fecha DATE)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total_ventas_productos DECIMAL(10,2);
    DECLARE total_pagos_citas DECIMAL(10,2);
    
    -- 1. VENTAS DE PRODUCTOS (punto de venta)
    -- Usamos VENTA.total, NO PAGO
    SELECT COALESCE(SUM(total), 0) INTO total_ventas_productos
    FROM VENTA
    WHERE DATE(fecha_venta) = p_fecha 
    AND estado = 'completada';
    
    -- 2. PAGOS DE CITAS (servicios veterinarios)
    -- Usamos AUDITORIA_PAGOS.monto
    SELECT COALESCE(SUM(monto), 0) INTO total_pagos_citas
    FROM AUDITORIA_PAGOS
    WHERE DATE(fecha_pago) = p_fecha 
    AND accion = 'pago';
    
    -- Total del día = productos + servicios
    RETURN total_ventas_productos + total_pagos_citas;
END$$

DELIMITER ;

-- Función: Obtener promedio de venta por cliente
DELIMITER $$
CREATE FUNCTION promedio_venta_cliente(p_cliente_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE promedio DECIMAL(10,2);
    
    SELECT AVG(total) INTO promedio
    FROM VENTA
    WHERE id_cliente = p_cliente_id AND estado = 'completada';
    
    RETURN IFNULL(promedio, 0);
END$$
DELIMITER ;

-- Función: Obtener total de pagos de una venta
DELIMITER $$
CREATE FUNCTION total_pagos_venta(p_venta_id INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    
    SELECT SUM(monto) INTO total
    FROM PAGO
    WHERE id_venta = p_venta_id;
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Verificar si una venta está pagada completamente
DELIMITER $$
CREATE FUNCTION venta_pagada_completa(p_venta_id INT)
RETURNS BOOLEAN
DETERMINISTIC
BEGIN
    DECLARE total_venta DECIMAL(10,2);
    DECLARE total_pagado DECIMAL(10,2);
    
    SELECT total INTO total_venta FROM VENTA WHERE id = p_venta_id;
    SELECT SUM(monto) INTO total_pagado FROM PAGO WHERE id_venta = p_venta_id;
    
    RETURN IFNULL(total_pagado, 0) >= total_venta;
END$$
DELIMITER ;


-- ============================================
-- FUNCIONES FINANCIERAS
-- ============================================

-- Función: Obtener ingresos totales del mes actual
DELIMITER $$
CREATE FUNCTION ingresos_mes_actual()
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    
    SELECT SUM(total) INTO total
    FROM VENTA
    WHERE MONTH(fecha_venta) = MONTH(CURDATE()) 
      AND YEAR(fecha_venta) = YEAR(CURDATE())
      AND estado = 'completada';
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener ingresos del año actual
DELIMITER $$
CREATE FUNCTION ingresos_anio_actual()
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE total DECIMAL(10,2);
    
    SELECT SUM(total) INTO total
    FROM VENTA
    WHERE YEAR(fecha_venta) = YEAR(CURDATE())
      AND estado = 'completada';
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener total de servicios realizados en el mes
DELIMITER $$
CREATE FUNCTION servicios_mes_actual()
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE total INT;
    
    SELECT COUNT(DISTINCT c.id) INTO total
    FROM CITA c
    WHERE MONTH(c.fecha_cita) = MONTH(CURDATE())
      AND YEAR(c.fecha_cita) = YEAR(CURDATE())
      AND c.estado = 'completada';
    
    RETURN IFNULL(total, 0);
END$$
DELIMITER ;

-- Función: Obtener tasa de conversión (citas confirmadas vs solicitadas)
DELIMITER $$
CREATE FUNCTION tasa_conversion_citas()
RETURNS DECIMAL(5,2)
DETERMINISTIC
BEGIN
    DECLARE total_solicitadas INT;
    DECLARE total_confirmadas INT;
    DECLARE tasa DECIMAL(5,2);
    
    SELECT COUNT(*) INTO total_solicitadas FROM CITA;
    SELECT COUNT(*) INTO total_confirmadas FROM CITA WHERE estado IN ('confirmada', 'completada');
    
    IF total_solicitadas > 0 THEN
        SET tasa = (total_confirmadas * 100.0) / total_solicitadas;
    ELSE
        SET tasa = 0;
    END IF;
    
    RETURN tasa;
END$$
DELIMITER ;