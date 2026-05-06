-- ============================================
-- PROCEDIMIENTOS PARA CLIENTES Y MASCOTAS
-- ============================================

-- Procedimiento: Registrar nuevo cliente con mascota - ✅ YA SE ESTA USANDO (citas.php)
DELIMITER $$

DROP PROCEDURE IF EXISTS registrar_cliente_mascota$$

CREATE PROCEDURE registrar_cliente_mascota(
    IN p_nombre_cliente VARCHAR(100),
    IN p_ape_pat VARCHAR(50),
    IN p_ape_mat VARCHAR(50),
    IN p_telefono VARCHAR(15),
    IN p_email VARCHAR(100),
    IN p_direccion TEXT,
    IN p_nombre_mascota VARCHAR(100),
    IN p_especie ENUM('Canino', 'Felino', 'Otro'),
    IN p_raza VARCHAR(50),
    IN p_genero ENUM('MACHO', 'HEMBRA'),
    IN p_foto VARCHAR(500),
    OUT p_id_cliente INT,
    OUT p_id_mascota INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Insertar cliente
    INSERT INTO CLIENTE (nombre, ape_pat, ape_mat, telefono, email, direccion)
    VALUES (p_nombre_cliente, p_ape_pat, p_ape_mat, p_telefono, p_email, p_direccion);
    SET p_id_cliente = LAST_INSERT_ID();
    
    -- Insertar mascota
    INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero, foto)
    VALUES (p_id_cliente, p_nombre_mascota, p_especie, p_raza, p_genero, p_foto);
    SET p_id_mascota = LAST_INSERT_ID();
    
    COMMIT;
END$$

DELIMITER ;

-- Procedimiento: Buscar cliente por teléfono o email ✅ YA SE ESTA USANDO (clientes.php)
DELIMITER $$
CREATE PROCEDURE buscar_cliente(
    IN p_criterio VARCHAR(100)
)
BEGIN
    SELECT 
        id, nombre, ape_pat, ape_mat, telefono, email,
        (SELECT COUNT(*) FROM MASCOTA WHERE id_cliente = CLIENTE.id AND activo = 1) AS total_mascotas
    FROM CLIENTE
    WHERE telefono LIKE CONCAT('%', p_criterio, '%')
       OR email LIKE CONCAT('%', p_criterio, '%')
       OR nombre LIKE CONCAT('%', p_criterio, '%')
    ORDER BY nombre;
END$$
DELIMITER ;

-- ============================================
-- PROCEDIMIENTOS PARA EMPLEADOS
-- ============================================


-- Procedimiento: Asignar horario a empleado ✅ YA SE ESTA USANDO (horario_asignar.php)
DELIMITER $$
CREATE PROCEDURE asignar_horario(
    IN p_empleado_id INT,
    IN p_dia_semana ENUM('lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'),
    IN p_hora_entrada TIME,
    IN p_hora_salida TIME
)
BEGIN
    -- Verificar si ya existe horario para ese día
    IF EXISTS (SELECT 1 FROM HORARIO_EMPLEADO WHERE id_empleado = p_empleado_id AND dia_semana = p_dia_semana) THEN
        UPDATE HORARIO_EMPLEADO 
        SET hora_entrada = p_hora_entrada, hora_salida = p_hora_salida, activo = 1
        WHERE id_empleado = p_empleado_id AND dia_semana = p_dia_semana;
    ELSE
        INSERT INTO HORARIO_EMPLEADO (id_empleado, dia_semana, hora_entrada, hora_salida)
        VALUES (p_empleado_id, p_dia_semana, p_hora_entrada, p_hora_salida);
    END IF;
END$$
DELIMITER ;

-- Procedimiento: Obtener empleados disponibles en una fecha/hora
DELIMITER $$
CREATE PROCEDURE empleados_disponibles(
    IN p_fecha DATE,
    IN p_hora TIME,
    IN p_puesto ENUM('veterinario', 'asistente')
)
BEGIN
    SELECT 
        e.id,
        e.nombre,
        e.ape_pat,
        e.ape_mat,
        e.puesto,
        e.especialidad
    FROM EMPLEADO e
    JOIN HORARIO_EMPLEADO h ON e.id = h.id_empleado
    WHERE e.activo = 1
      AND e.puesto = p_puesto
      AND h.dia_semana = DAYNAME(p_fecha)
      AND h.hora_entrada <= p_hora
      AND h.hora_salida >= p_hora
      AND NOT EXISTS (
          SELECT 1 FROM ASIGNACION_CITA ac
          JOIN CITA c ON ac.id_cita = c.id
          WHERE ac.id_empleado = e.id
            AND c.fecha_cita = p_fecha
            AND c.hora_cita = p_hora
      )
    ORDER BY e.nombre;
END$$
DELIMITER ;


-- ============================================
-- PROCEDIMIENTOS PARA INVENTARIO
-- ============================================

-- Procedimiento: Registrar compra con múltiples productos
DELIMITER $$

DROP PROCEDURE IF EXISTS registrar_compra$$

CREATE PROCEDURE registrar_compra(
    IN p_proveedor_id INT,
    IN p_folio_factura VARCHAR(50),
    IN p_empleado_id INT,
    IN p_productos JSON
)
BEGIN
    DECLARE v_compra_id INT;
    DECLARE v_total DECIMAL(10,2) DEFAULT 0;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_cantidad INT;
    DECLARE v_precio DECIMAL(10,2);
    DECLARE v_producto_id INT;
    DECLARE v_subtotal DECIMAL(10,2);
    DECLARE v_es_nuevo INT;
    DECLARE v_nombre_nuevo VARCHAR(100);
    DECLARE v_id_categoria INT;
    DECLARE v_precio_venta DECIMAL(10,2);
    DECLARE v_total_items INT;
    DECLARE v_nombre_limpio VARCHAR(100);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    SET v_total_items = JSON_LENGTH(p_productos);
    
    -- Primera pasada: crear productos nuevos
    SET v_idx = 0;
    WHILE v_idx < v_total_items DO
        -- Extraer valores JSON (usando "id")
        SET v_producto_id = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].id'));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].cantidad'));
        SET v_precio = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].precio_unitario'));
        SET v_es_nuevo = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].es_nuevo'));
        SET v_nombre_nuevo = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].nombre_nuevo'));
        SET v_id_categoria = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].id_categoria'));
        
        -- Convertir a tipos correctos
        SET v_producto_id = CAST(v_producto_id AS UNSIGNED);
        SET v_cantidad = CAST(v_cantidad AS UNSIGNED);
        SET v_precio = CAST(v_precio AS DECIMAL(10,2));
        SET v_es_nuevo = CAST(v_es_nuevo AS UNSIGNED);
        
        -- Limpiar el nombre (quitar comillas)
        IF v_nombre_nuevo IS NOT NULL THEN
            SET v_nombre_limpio = REPLACE(REPLACE(REPLACE(v_nombre_nuevo, '"', ''), '\\', ''), '[', '');
            SET v_nombre_limpio = TRIM(v_nombre_limpio);
        ELSE
            SET v_nombre_limpio = NULL;
        END IF;
        
        -- Convertir categoría
        SET v_id_categoria = CAST(v_id_categoria AS UNSIGNED);
        
        -- Valores por defecto
        IF v_producto_id IS NULL THEN SET v_producto_id = 0; END IF;
        IF v_cantidad IS NULL OR v_cantidad = 0 THEN SET v_cantidad = 1; END IF;
        IF v_precio IS NULL THEN SET v_precio = 0; END IF;
        IF v_es_nuevo IS NULL THEN SET v_es_nuevo = 0; END IF;
        IF v_id_categoria IS NULL THEN SET v_id_categoria = 0; END IF;
        
        -- Si es producto nuevo
        IF (v_producto_id = 0 OR v_es_nuevo = 1) AND v_nombre_limpio IS NOT NULL AND v_nombre_limpio != '' THEN
            SET v_precio_venta = v_precio * 1.3;
            
            -- Insertar nuevo producto
            INSERT INTO PRODUCTO (nombre, id_categoria, precio_compra, precio_venta, stock_actual, activo)
            VALUES (v_nombre_limpio, v_id_categoria, v_precio, v_precio_venta, 0, 1);
            
            SET v_producto_id = LAST_INSERT_ID();
        END IF;
        
        SET v_subtotal = v_cantidad * v_precio;
        SET v_total = v_total + v_subtotal;
        SET v_idx = v_idx + 1;
    END WHILE;
    
    -- Insertar cabecera de compra
    INSERT INTO COMPRA (id_proveedor, fecha_compra, folio_factura, total, id_empleado)
    VALUES (p_proveedor_id, CURDATE(), p_folio_factura, v_total, p_empleado_id);
    SET v_compra_id = LAST_INSERT_ID();
    
    -- Segunda pasada: insertar detalles
    SET v_idx = 0;
    WHILE v_idx < v_total_items DO
        -- Extraer valores nuevamente
        SET v_producto_id = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].id'));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].cantidad'));
        SET v_precio = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].precio_unitario'));
        SET v_es_nuevo = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].es_nuevo'));
        SET v_nombre_nuevo = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].nombre_nuevo'));
        
        -- Convertir
        SET v_producto_id = CAST(v_producto_id AS UNSIGNED);
        SET v_cantidad = CAST(v_cantidad AS UNSIGNED);
        SET v_precio = CAST(v_precio AS DECIMAL(10,2));
        
        -- Limpiar nombre
        IF v_nombre_nuevo IS NOT NULL THEN
            SET v_nombre_limpio = REPLACE(REPLACE(REPLACE(v_nombre_nuevo, '"', ''), '\\', ''), '[', '');
            SET v_nombre_limpio = TRIM(v_nombre_limpio);
        END IF;
        
        IF v_producto_id IS NULL THEN SET v_producto_id = 0; END IF;
        IF v_cantidad IS NULL THEN SET v_cantidad = 1; END IF;
        IF v_precio IS NULL THEN SET v_precio = 0; END IF;
        
        -- Si es producto nuevo, obtener el ID del producto que acabamos de crear
        IF v_producto_id = 0 AND v_nombre_limpio IS NOT NULL AND v_nombre_limpio != '' THEN
            SELECT id INTO v_producto_id FROM PRODUCTO 
            WHERE nombre = v_nombre_limpio
            ORDER BY id DESC LIMIT 1;
        END IF;
        
        SET v_subtotal = v_cantidad * v_precio;
        
        INSERT INTO DETALLE_COMPRA (id_compra, id_producto, cantidad, precio_unitario, subtotal)
        VALUES (v_compra_id, v_producto_id, v_cantidad, v_precio, v_subtotal);
        
        SET v_idx = v_idx + 1;
    END WHILE;
    
    COMMIT;
    
    SELECT v_compra_id AS compra_id, v_total AS total_compra;
END$$

DELIMITER ;

-- Procedimiento: Registrar venta con múltiples productos
DELIMITER $$
CREATE PROCEDURE registrar_venta(
    IN p_cliente_id INT,
    IN p_empleado_id INT,
    IN p_metodo_pago ENUM('efectivo', 'tarjeta', 'transferencia', 'credito'),
    IN p_productos JSON,
    OUT p_venta_id INT,
    OUT p_total DECIMAL(10,2)
)
BEGIN
    DECLARE v_subtotal DECIMAL(10,2) DEFAULT 0;
    DECLARE v_iva DECIMAL(10,2) DEFAULT 0;
    DECLARE v_idx INT DEFAULT 0;
    DECLARE v_cantidad INT;
    DECLARE v_precio DECIMAL(10,2);
    DECLARE v_descuento DECIMAL(10,2);
    DECLARE v_producto_id INT;
    DECLARE v_stock_actual INT;
    DECLARE v_total_items INT;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    SET v_total_items = JSON_LENGTH(p_productos);
    
    -- 🔒 BLOQUEO PESIMISTA: Bloquear productos para evitar sobreventa
    WHILE v_idx < v_total_items DO
        SET v_producto_id = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].id_producto'));
        
        -- SELECT con FOR UPDATE bloquea la fila hasta COMMIT
        SELECT stock_actual, precio_venta INTO v_stock_actual, v_precio
        FROM PRODUCTO WHERE id = v_producto_id FOR UPDATE;
        
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].cantidad'));
        SET v_descuento = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].descuento'));
        
        IF v_stock_actual < v_cantidad THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = CONCAT('Stock insuficiente para producto: ', v_producto_id);
        END IF;
        
        SET v_subtotal = v_subtotal + (v_cantidad * v_precio * (1 - v_descuento/100));
        SET v_idx = v_idx + 1;
    END WHILE;
    
    SET v_iva = v_subtotal * 0.16;
    SET p_total = v_subtotal + v_iva;
    
    -- Insertar venta
    INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago)
    VALUES (p_cliente_id, p_empleado_id, v_subtotal, v_iva, p_total, p_metodo_pago);
    SET p_venta_id = LAST_INSERT_ID();
    
    -- Insertar detalles y actualizar stock
    SET v_idx = 0;
    WHILE v_idx < v_total_items DO
        SET v_producto_id = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].id_producto'));
        SET v_cantidad = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].cantidad'));
        SET v_descuento = JSON_EXTRACT(p_productos, CONCAT('$[', v_idx, '].descuento'));
        
        SELECT precio_venta INTO v_precio FROM PRODUCTO WHERE id = v_producto_id;
        
        INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, descuento, subtotal)
        VALUES (p_venta_id, v_producto_id, v_cantidad, v_precio, v_descuento, 
                v_cantidad * v_precio * (1 - v_descuento/100));
        
        -- Actualizar stock (ya tenemos el bloqueo)
        UPDATE PRODUCTO SET stock_actual = stock_actual - v_cantidad 
        WHERE id = v_producto_id;
        
        SET v_idx = v_idx + 1;
    END WHILE;
    
    COMMIT;
END$$
DELIMITER ;

-- Procedimiento: Ajustar stock manualmente
DELIMITER $$
CREATE PROCEDURE ajustar_stock(
    IN p_producto_id INT,
    IN p_nuevo_stock INT,
    IN p_motivo TEXT,
    IN p_empleado_id INT,
    OUT p_ajuste_realizado INT
)
BEGIN
    DECLARE stock_anterior INT;
    
    SELECT stock_actual INTO stock_anterior FROM PRODUCTO WHERE id = p_producto_id;
    
    UPDATE PRODUCTO SET stock_actual = p_nuevo_stock WHERE id = p_producto_id;
    
    SET p_ajuste_realizado = ABS(p_nuevo_stock - stock_anterior);
    
    INSERT INTO MOVIMIENTO_INVENTARIO 
        (id_producto, tipo, cantidad, motivo, referencia, id_empleado)
    VALUES 
        (p_producto_id, 'ajuste', p_ajuste_realizado, 
         p_motivo, CONCAT('AJUSTE-', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), 
         p_empleado_id);
END$$
DELIMITER ;


-- ============================================
-- PROCEDIMIENTOS PARA REPORTES AVANZADOS
-- ============================================

-- Procedimiento: Dashboard ejecutivo (resumen completo)
DELIMITER $$
CREATE PROCEDURE dashboard_ejecutivo()
BEGIN
    -- Resumen del día
    SELECT 
        CURDATE() AS fecha,
        (SELECT COUNT(*) FROM CITA WHERE fecha_cita = CURDATE()) AS citas_hoy,
        (SELECT COUNT(*) FROM CITA WHERE fecha_cita = CURDATE() AND estado = 'pendiente') AS citas_pendientes,
        (SELECT IFNULL(SUM(total), 0) FROM VENTA WHERE DATE(fecha_venta) = CURDATE() AND estado = 'completada') AS ventas_hoy;
    
    -- Próximas citas (7 días)
    SELECT 
        c.id, c.fecha_cita, c.hora_cita, c.estado,
        m.nombre_mascota, cl.nombre AS dueno
    FROM CITA c
    JOIN MASCOTA m ON c.id_mascota = m.id
    JOIN CLIENTE cl ON m.id_cliente = cl.id
    WHERE c.fecha_cita BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY c.fecha_cita, c.hora_cita;
    
    -- Productos con stock bajo
    SELECT nombre, stock_actual, stock_minimo, ubicacion
    FROM PRODUCTO
    WHERE stock_actual <= stock_minimo AND activo = 1;
    
    -- Ingresos últimos 30 días
    SELECT 
        DATE_FORMAT(fecha_venta, '%Y-%m-%d') AS fecha,
        COUNT(*) AS ventas,
        SUM(total) AS total
    FROM VENTA
    WHERE fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      AND estado = 'completada'
    GROUP BY DATE(fecha_venta)
    ORDER BY fecha DESC;
END$$
DELIMITER ;

-- Procedimiento: Reporte de citas por rango de fechas (ya lo tienes, lo mejoramos)
DELIMITER $$
CREATE PROCEDURE reporte_citas(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    SELECT 
        c.fecha_cita,
        c.hora_cita,
        c.estado,
        m.nombre_mascota,
        cl.nombre AS dueno,
        cl.telefono,
        GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
        SUM(dc.precio_fijado) AS total
    FROM CITA c
    JOIN MASCOTA m ON c.id_mascota = m.id
    JOIN CLIENTE cl ON m.id_cliente = cl.id
    JOIN DETALLE_CITA dc ON c.id = dc.id_cita
    JOIN SERVICIO s ON dc.id_servicio = s.id
    WHERE c.fecha_cita BETWEEN p_fecha_inicio AND p_fecha_fin
    GROUP BY c.id
    ORDER BY c.fecha_cita, c.hora_cita;
END$$
DELIMITER ;

-- Procedimiento: Reporte financiero consolidado
DELIMITER $$
CREATE PROCEDURE reporte_financiero(
    IN p_fecha_inicio DATE,
    IN p_fecha_fin DATE
)
BEGIN
    -- Ventas del período
    SELECT 
        'VENTAS' AS concepto,
        COUNT(*) AS total_transacciones,
        SUM(total) AS monto_total,
        AVG(total) AS promedio
    FROM VENTA
    WHERE fecha_venta BETWEEN p_fecha_inicio AND p_fecha_fin
      AND estado = 'completada';
    
    -- Servicios realizados
    SELECT 
        'SERVICIOS' AS concepto,
        COUNT(DISTINCT c.id) AS total_transacciones,
        SUM(dc.precio_fijado) AS monto_total,
        AVG(dc.precio_fijado) AS promedio
    FROM CITA c
    JOIN DETALLE_CITA dc ON c.id = dc.id_cita
    WHERE c.fecha_cita BETWEEN p_fecha_inicio AND p_fecha_fin
      AND c.estado = 'completada';
    
    -- Productos más vendidos
    SELECT 
        p.nombre AS producto,
        SUM(dv.cantidad) AS unidades_vendidas,
        SUM(dv.subtotal) AS ingresos
    FROM DETALLE_VENTA dv
    JOIN PRODUCTO p ON dv.id_producto = p.id
    JOIN VENTA v ON dv.id_venta = v.id
    WHERE v.fecha_venta BETWEEN p_fecha_inicio AND p_fecha_fin
      AND v.estado = 'completada'
    GROUP BY p.id
    ORDER BY unidades_vendidas DESC
    LIMIT 10;
END$$
DELIMITER ;

-- Procedimiento: Resumen de caja diario
DELIMITER $$
CREATE PROCEDURE caja_diaria(
    IN p_fecha DATE
)
BEGIN
    SELECT 
        p_fecha AS fecha,
        -- Ventas
        (SELECT IFNULL(SUM(total), 0) FROM VENTA WHERE DATE(fecha_venta) = p_fecha AND estado = 'completada') AS total_ventas,
        -- Citas
        (SELECT IFNULL(SUM(dc.precio_fijado), 0) FROM CITA c JOIN DETALLE_CITA dc ON c.id = dc.id_cita WHERE c.fecha_cita = p_fecha AND c.estado = 'completada') AS total_servicios,
        -- Efectivo vs otros métodos
        (SELECT IFNULL(SUM(total), 0) FROM VENTA WHERE DATE(fecha_venta) = p_fecha AND metodo_pago = 'efectivo' AND estado = 'completada') AS efectivo,
        (SELECT IFNULL(SUM(total), 0) FROM VENTA WHERE DATE(fecha_venta) = p_fecha AND metodo_pago IN ('tarjeta', 'transferencia') AND estado = 'completada') AS electronico,
        -- Detalle de ventas
        (SELECT COUNT(*) FROM VENTA WHERE DATE(fecha_venta) = p_fecha AND estado = 'completada') AS numero_ventas,
        (SELECT COUNT(*) FROM CITA WHERE fecha_cita = p_fecha AND estado = 'completada') AS numero_servicios;
END$$
DELIMITER ;