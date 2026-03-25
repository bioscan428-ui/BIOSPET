-- Vista: Citas completas con todos los datos
CREATE OR REPLACE VIEW vista_citas_completas AS
SELECT 
    c.id AS cita_id,
    c.fecha_cita,
    c.hora_cita,
    c.estado,
    c.notas,
    m.id AS mascota_id,
    m.nombre_mascota,
    m.especie,
    cl.id AS cliente_id,
    cl.nombre AS nombre_dueno,
    cl.ape_pat,
    cl.ape_mat,
    cl.telefono,
    cl.email,
    GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
    GROUP_CONCAT(dc.precio_fijado SEPARATOR ', ') AS precios,
    SUM(dc.precio_fijado) AS total_cobrado
FROM CITA c
JOIN MASCOTA m ON c.id_mascota = m.id
JOIN CLIENTE cl ON m.id_cliente = cl.id
JOIN DETALLE_CITA dc ON c.id = dc.id_cita
JOIN SERVICIO s ON dc.id_servicio = s.id
GROUP BY c.id;

-- Vista: Resumen de ingresos por día
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

-- Vista: Historial completo de mascotas
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

SELECT * FROM vista_citas_completas LIMIT 5;
