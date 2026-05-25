-- ============================================
-- VISTAS BIOSPET - SISTEMA COMPLETO
-- ============================================

-- 1. VISTAS DE CITAS Y SERVICIOS
CREATE OR REPLACE VIEW vista_citas_completas AS
SELECT 
    c.id AS cita_id,
    c.fecha_cita,
    c.hora_cita,
    c.estado,
    c.origen,              -- ← CAMPO FALTANTE
    c.pagada,
    c.metodo_pago,
    c.notas,
    m.id AS mascota_id,
    m.nombre_mascota,
    m.especie,
    m.foto,
    cl.id AS cliente_id,
    cl.nombre AS nombre_dueno,
    cl.ape_pat,
    cl.ape_mat,
    cl.telefono,
    cl.email,
    COALESCE(GROUP_CONCAT(s.nombre_servicio SEPARATOR ', '), '') AS servicios,
    COALESCE(GROUP_CONCAT(dc.precio_fijado SEPARATOR ', '), '') AS precios,
    COALESCE(SUM(dc.precio_fijado), 0) AS total_cobrado
FROM CITA c
JOIN MASCOTA m ON c.id_mascota = m.id
JOIN CLIENTE cl ON m.id_cliente = cl.id
LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
GROUP BY c.id
ORDER BY c.fecha_cita DESC, c.hora_cita DESC;
-------------------------------------------------------------
CREATE OR REPLACE VIEW vista_ingresos_diarios AS
SELECT 
    c.fecha_cita,
    COUNT(DISTINCT c.id) AS total_citas,
    COUNT(dc.id) AS total_servicios,
    SUM(dc.precio_fijado) AS ingresos_totales,
    AVG(dc.precio_fijado) AS promedio_por_servicio
FROM CITA c
JOIN DETALLE_CITA dc ON c.id = dc.id_cita
WHERE c.estado IN ('confirmada', 'completada')
GROUP BY c.fecha_cita
ORDER BY c.fecha_cita DESC;

CREATE OR REPLACE VIEW vista_historial_mascota AS
SELECT 
    m.id AS mascota_id,
    m.nombre_mascota,
    m.especie,
    cl.nombre AS nombre_dueno,
    cl.telefono,
    c.fecha_cita,
    c.estado,
    s.nombre_servicio,
    dc.precio_fijado
FROM MASCOTA m
JOIN CLIENTE cl ON m.id_cliente = cl.id
LEFT JOIN CITA c ON m.id = c.id_mascota
LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
LEFT JOIN SERVICIO s ON dc.id_servicio = s.id
WHERE m.activo = 1
ORDER BY m.id, c.fecha_cita DESC;

-- 2. VISTAS DE CLIENTES Y MASCOTAS
CREATE OR REPLACE VIEW vista_clientes_activos AS
SELECT 
    cl.id,
    cl.nombre,
    cl.ape_pat,
    cl.ape_mat,
    cl.telefono,
    cl.email,
    cl.fecha_registro,
    cl.activo,
    -- Mascotas (subconsulta)
    (SELECT COUNT(*) FROM MASCOTA WHERE id_cliente = cl.id AND activo = 1) AS total_mascotas,
    -- Total citas (subconsulta)
    (SELECT COUNT(*) FROM CITA c JOIN MASCOTA m ON c.id_mascota = m.id WHERE m.id_cliente = cl.id) AS total_citas,
    -- Citas completadas (subconsulta)
    (SELECT COUNT(*) FROM CITA c JOIN MASCOTA m ON c.id_mascota = m.id WHERE m.id_cliente = cl.id AND c.estado = 'completada') AS citas_completadas,
    -- Total gastado (subconsulta: ventas + servicios)
    (SELECT COALESCE(SUM(total), 0) FROM VENTA WHERE id_cliente = cl.id AND estado = 'completada') +
    (SELECT COALESCE(SUM(dc.precio_fijado), 0) 
     FROM CITA c 
     JOIN MASCOTA m ON c.id_mascota = m.id 
     JOIN DETALLE_CITA dc ON c.id = dc.id_cita 
     WHERE m.id_cliente = cl.id AND c.estado = 'completada') AS total_gastado
FROM CLIENTE cl
WHERE cl.activo = 1
ORDER BY total_gastado DESC;

CREATE OR REPLACE VIEW vista_mascotas_completas AS
SELECT 
    m.id,
    m.nombre_mascota,
    m.especie,
    m.raza,
    m.genero,
    m.fecha_nacimiento,
    TIMESTAMPDIFF(YEAR, m.fecha_nacimiento, CURDATE()) AS edad_anios,
    cl.id AS id_dueno,
    cl.nombre AS nombre_dueno,
    cl.telefono,
    COUNT(c.id) AS total_citas,
    MAX(c.fecha_cita) AS ultima_cita
FROM MASCOTA m
JOIN CLIENTE cl ON m.id_cliente = cl.id
LEFT JOIN CITA c ON m.id = c.id_mascota
WHERE m.activo = 1
GROUP BY m.id;

-- 3. VISTAS DE EMPLEADOS
CREATE OR REPLACE VIEW vista_empleados_activos AS
SELECT 
    e.id,
    e.nombre,
    e.ape_pat,
    e.ape_mat,
    e.email,
    e.telefono,
    e.puesto,
    e.especialidad,
    e.fecha_contratacion,
    u.nombre_usuario,
    u.rol,
    u.ultimo_acceso,
    COUNT(DISTINCT ac.id_cita) AS citas_asignadas
FROM EMPLEADO e
LEFT JOIN USUARIO u ON e.id = u.id_empleado AND u.activo = 1
LEFT JOIN ASIGNACION_CITA ac ON e.id = ac.id_empleado
WHERE e.activo = 1
GROUP BY e.id
ORDER BY e.puesto, e.nombre;

CREATE OR REPLACE VIEW vista_citas_por_empleado AS
SELECT 
    e.id AS empleado_id,
    e.nombre AS empleado_nombre,
    e.puesto,
    ac.rol_asignado,
    c.id AS cita_id,
    c.fecha_cita,
    c.hora_cita,
    c.estado,
    m.nombre_mascota,
    cl.nombre AS dueno
FROM ASIGNACION_CITA ac
JOIN EMPLEADO e ON ac.id_empleado = e.id
JOIN CITA c ON ac.id_cita = c.id
JOIN MASCOTA m ON c.id_mascota = m.id
JOIN CLIENTE cl ON m.id_cliente = cl.id
WHERE e.activo = 1
ORDER BY c.fecha_cita DESC, e.nombre;

-- 4. VISTAS DE INVENTARIO
CREATE OR REPLACE VIEW vista_stock_critico AS
SELECT 
    p.id,
    p.nombre,
    p.codigo_barras,
    c.nombre AS categoria,
    p.stock_actual,
    p.stock_minimo,
    p.precio_compra,
    p.precio_venta,
    p.ubicacion,
    p.fecha_vencimiento,
    DATEDIFF(p.fecha_vencimiento, CURDATE()) AS dias_vencimiento
FROM PRODUCTO p
JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
WHERE p.activo = 1 
  AND (p.stock_actual <= p.stock_minimo 
       OR p.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
ORDER BY p.stock_actual ASC, p.fecha_vencimiento ASC;

CREATE OR REPLACE VIEW vista_movimientos_recientes AS
SELECT 
    m.id,
    p.nombre AS producto,
    m.tipo,
    m.cantidad,
    m.motivo,
    m.referencia,
    e.nombre AS empleado,
    m.fecha_movimiento
FROM MOVIMIENTO_INVENTARIO m
JOIN PRODUCTO p ON m.id_producto = p.id
JOIN EMPLEADO e ON m.id_empleado = e.id
WHERE m.fecha_movimiento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
ORDER BY m.fecha_movimiento DESC;

CREATE OR REPLACE VIEW vista_compras_proveedor AS
SELECT 
    pr.id AS proveedor_id,
    pr.nombre AS proveedor,
    COUNT(c.id) AS total_compras,
    SUM(c.total) AS total_gastado,
    MAX(c.fecha_compra) AS ultima_compra,
    COUNT(DISTINCT dc.id_producto) AS productos_distintos
FROM PROVEEDOR pr
LEFT JOIN COMPRA c ON pr.id = c.id_proveedor
LEFT JOIN DETALLE_COMPRA dc ON c.id = dc.id_compra
WHERE pr.activo = 1
GROUP BY pr.id
ORDER BY total_gastado DESC;

-- 5. VISTAS DE VENTAS
CREATE OR REPLACE VIEW vista_ventas_completas AS
SELECT 
    v.id AS venta_id,
    v.fecha_venta,
    v.tipo_comprobante,
    v.folio,
    v.subtotal,
    v.iva,
    v.total,
    v.metodo_pago,
    v.estado,
    cl.id AS cliente_id,
    cl.nombre AS cliente_nombre,
    cl.telefono,
    e.id AS empleado_id,
    e.nombre AS empleado_nombre,
    COUNT(dv.id) AS total_productos,
    SUM(dv.cantidad) AS total_unidades
FROM VENTA v
JOIN CLIENTE cl ON v.id_cliente = cl.id
JOIN EMPLEADO e ON v.id_empleado = e.id
LEFT JOIN DETALLE_VENTA dv ON v.id = dv.id_venta
WHERE v.estado = 'completada'
GROUP BY v.id
ORDER BY v.fecha_venta DESC;

CREATE OR REPLACE VIEW vista_productos_mas_vendidos AS
SELECT 
    p.id,
    p.nombre,
    p.codigo_barras,
    cat.nombre AS categoria,
    SUM(dv.cantidad) AS unidades_vendidas,
    COUNT(DISTINCT dv.id_venta) AS total_ventas,
    SUM(dv.subtotal) AS ingresos_generados
FROM PRODUCTO p
JOIN CATEGORIA_PRODUCTO cat ON p.id_categoria = cat.id
JOIN DETALLE_VENTA dv ON p.id = dv.id_producto
JOIN VENTA v ON dv.id_venta = v.id
WHERE v.estado = 'completada'
GROUP BY p.id
ORDER BY unidades_vendidas DESC
LIMIT 20;

CREATE OR REPLACE VIEW vista_pagos_venta AS
SELECT 
    v.id AS venta_id,
    v.total AS total_venta,
    SUM(p.monto) AS total_pagado,
    v.total - SUM(p.monto) AS saldo_pendiente,
    COUNT(p.id) AS numero_pagos
FROM VENTA v
LEFT JOIN PAGO p ON v.id = p.id_venta
WHERE v.estado IN ('completada', 'pendiente')
GROUP BY v.id;

-- 6. VISTAS FINANCIERAS
CREATE OR REPLACE VIEW vista_ingresos_mensuales AS
SELECT 
    DATE_FORMAT(fecha_venta, '%Y-%m') AS mes,
    COUNT(DISTINCT v.id) AS total_ventas,
    SUM(v.total) AS ingresos_ventas,
    COUNT(DISTINCT c.id) AS total_citas,
    SUM(dc.precio_fijado) AS ingresos_servicios
FROM VENTA v
JOIN CLIENTE cl ON v.id_cliente = cl.id
LEFT JOIN CITA c ON c.id_mascota IN (SELECT id FROM MASCOTA WHERE id_cliente = cl.id)
LEFT JOIN DETALLE_CITA dc ON c.id = dc.id_cita
WHERE v.estado = 'completada' AND v.fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY mes
ORDER BY mes DESC;

CREATE OR REPLACE VIEW vista_resumen_negocio AS
SELECT 
    (SELECT COUNT(*) FROM CLIENTE WHERE activo = 1) AS clientes_activos,
    (SELECT COUNT(*) FROM MASCOTA WHERE activo = 1) AS mascotas_activas,
    (SELECT COUNT(*) FROM EMPLEADO WHERE activo = 1) AS empleados_activos,
    (SELECT COUNT(*) FROM CITA WHERE estado = 'pendiente') AS citas_pendientes,
    (SELECT COUNT(*) FROM CITA WHERE estado = 'confirmada') AS citas_confirmadas,
    (SELECT COUNT(*) FROM PRODUCTO WHERE stock_actual <= stock_minimo AND activo = 1) AS productos_stock_critico,
    (SELECT SUM(total) FROM VENTA WHERE estado = 'completada' AND fecha_venta >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS ingresos_ultimos_30_dias,
    (SELECT SUM(total) FROM VENTA WHERE estado = 'completada') AS ingresos_totales;

-- VISTA para nivel de puntos
CREATE OR REPLACE VIEW vista_cliente_fidelidad AS
SELECT 
    c.id AS cliente_id,
    c.nombre,
    c.ape_pat,
    c.ape_mat,
    c.telefono,
    c.email,
    COALESCE(cp.puntos_actuales, 0) AS puntos_actuales,
    COALESCE(cp.puntos_acumulados_historial, 0) AS puntos_acumulados,
    cp.ultima_actualizacion,
    CASE 
        WHEN COALESCE(cp.puntos_actuales, 0) >= 3000 THEN 'platino'
        WHEN COALESCE(cp.puntos_actuales, 0) >= 1500 THEN 'oro'
        WHEN COALESCE(cp.puntos_actuales, 0) >= 500 THEN 'plata'
        ELSE 'bronce'
    END AS nivel,
    -- Beneficios por nivel
    CASE 
        WHEN COALESCE(cp.puntos_actuales, 0) >= 3000 THEN 20.00
        WHEN COALESCE(cp.puntos_actuales, 0) >= 1500 THEN 15.00
        WHEN COALESCE(cp.puntos_actuales, 0) >= 500 THEN 10.00
        ELSE 5.00
    END AS descuento_maximo,
    CASE 
        WHEN COALESCE(cp.puntos_actuales, 0) >= 3000 THEN 2.50
        WHEN COALESCE(cp.puntos_actuales, 0) >= 1500 THEN 2.00
        WHEN COALESCE(cp.puntos_actuales, 0) >= 500 THEN 1.50
        ELSE 1.00
    END AS multiplicador_puntos
FROM CLIENTE c
LEFT JOIN CLIENTE_PUNTOS cp ON c.id = cp.id_cliente
WHERE c.activo = 1;


-----AUDITORIA DE PRECIOS EN VENTAS (pendiente)


------VISTA DE TICKET PARA VENTA
CREATE OR REPLACE VIEW vista_ticket_venta AS
SELECT 
    v.id AS venta_id,
    v.folio,
    v.fecha_venta,
    v.subtotal,
    v.iva,
    v.total,
    v.metodo_pago,
    v.estado,
    
    -- Datos del cliente
    c.id AS cliente_id,
    CONCAT(c.nombre, ' ', IFNULL(c.ape_pat, ''), ' ', IFNULL(c.ape_mat, '')) AS cliente_nombre,
    c.telefono AS cliente_telefono,
    c.email AS cliente_email,
    c.direccion AS cliente_direccion,
    
    -- Datos del empleado (vendedor)
    e.id AS empleado_id,
    CONCAT(e.nombre, ' ', IFNULL(e.ape_pat, ''), ' ', IFNULL(e.ape_mat, '')) AS empleado_nombre,
    
    -- Datos de la empresa (para el ticket)
    'BIOSPET' AS empresa_nombre,
    'Clínica Veterinaria' AS empresa_eslogan,
    'Paseo Opera 7 Local 210 Lomas de Angelópolis 72830' AS empresa_direccion,
    'Tel: 221 820 3396' AS empresa_telefono,
    'RFC: XXXXXX' AS empresa_rfc,
    
    -- Totales por método de pago (si aplica)
    CASE WHEN v.metodo_pago = 'efectivo' THEN v.total ELSE 0 END AS total_efectivo,
    CASE WHEN v.metodo_pago = 'tarjeta' THEN v.total ELSE 0 END AS total_tarjeta,
    CASE WHEN v.metodo_pago = 'transferencia' THEN v.total ELSE 0 END AS total_transferencia,
    CASE WHEN v.metodo_pago = 'credito' THEN v.total ELSE 0 END AS total_credito
    
FROM VENTA v
LEFT JOIN CLIENTE c ON v.id_cliente = c.id
LEFT JOIN EMPLEADO e ON v.id_empleado = e.id
WHERE v.estado = 'completada';

-------Vista para DETALLE del TICKET (productos)
CREATE OR REPLACE VIEW vista_ticket_detalle AS
SELECT 
    dv.id_venta,
    dv.id_producto,
    p.nombre AS producto_nombre,
    dv.cantidad,
    dv.precio_unitario,
    dv.descuento,
    dv.subtotal,
    -- Calcular subtotal sin descuento
    (dv.cantidad * dv.precio_unitario) AS subtotal_sin_descuento,
    -- Si tiene descuento, mostrar porcentaje
    CASE 
        WHEN dv.descuento > 0 THEN CONCAT(ROUND((dv.descuento / (dv.cantidad * dv.precio_unitario)) * 100, 0), '%')
        ELSE NULL
    END AS porcentaje_descuento
FROM DETALLE_VENTA dv
JOIN PRODUCTO p ON dv.id_producto = p.id;


---------------------------------------------------------VISTAS DE DETALLE_CITA----------------------------------------------------------------
-- Vista para el encabezado del ticket de cita
CREATE OR REPLACE VIEW vista_ticket_cita_venta AS
SELECT 
    v.id AS venta_id,
    v.folio,
    v.fecha_venta,
    v.subtotal,
    v.iva,
    v.total,
    v.metodo_pago,
    v.estado,
    
    -- Datos del cliente
    c.id AS cliente_id,
    CONCAT(c.nombre, ' ', IFNULL(c.ape_pat, ''), ' ', IFNULL(c.ape_mat, '')) AS cliente_nombre,
    c.telefono AS cliente_telefono,
    c.email AS cliente_email,
    c.direccion AS cliente_direccion,
    
    -- Datos del empleado (vendedor)
    e.id AS empleado_id,
    CONCAT(e.nombre, ' ', IFNULL(e.ape_pat, ''), ' ', IFNULL(e.ape_mat, '')) AS empleado_nombre,
    
    -- Datos de la cita
    ct.id AS cita_id,
    ct.fecha_cita,
    ct.hora_cita,
    m.nombre_mascota,
    ct.notas as motivo,
    
    -- Datos de la empresa
    'BIOSPET' AS empresa_nombre,
    'Clínica Veterinaria' AS empresa_eslogan,
    'Paseo Opera 7 Local 210 Lomas de Angelópolis 72830' AS empresa_direccion,
    'Tel: 221 820 3396' AS empresa_telefono
    
FROM VENTA v
JOIN VENTA_CITA vc ON v.id = vc.id_venta
JOIN CITA ct ON vc.id_cita = ct.id
JOIN MASCOTA m ON ct.id_mascota = m.id
LEFT JOIN CLIENTE c ON v.id_cliente = c.id
LEFT JOIN EMPLEADO e ON v.id_empleado = e.id
WHERE v.estado = 'completada';

-- Vista para el detalle del ticket de cita (productos y servicios)
CREATE OR REPLACE VIEW vista_ticket_cita_detalle AS
SELECT 
    v.id AS id_venta,
    'producto' AS tipo_item,
    p.nombre AS descripcion,
    dv.cantidad,
    dv.precio_unitario,
    dv.subtotal
FROM VENTA v
JOIN VENTA_CITA vc ON v.id = vc.id_venta
JOIN DETALLE_VENTA dv ON v.id = dv.id_venta
JOIN PRODUCTO p ON dv.id_producto = p.id
WHERE v.estado = 'completada'

UNION ALL

SELECT 
    v.id AS id_venta,
    'servicio' AS tipo_item,
    s.nombre_servicio AS descripcion,
    1 AS cantidad,
    dc.precio_fijado AS precio_unitario,
    dc.precio_fijado AS subtotal
FROM VENTA v
JOIN VENTA_CITA vc ON v.id = vc.id_venta
JOIN CITA ct ON vc.id_cita = ct.id
JOIN DETALLE_CITA dc ON ct.id = dc.id_cita
JOIN SERVICIO s ON dc.id_servicio = s.id
WHERE v.estado = 'completada'

ORDER BY id_venta, tipo_item DESC, descripcion;