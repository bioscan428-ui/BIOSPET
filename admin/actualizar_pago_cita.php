<?php
// admin/actualizar_pago_cita.php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_cita = (int)($_POST['id_cita'] ?? 0);
$metodo_pago = $_POST['metodo_pago'] ?? '';
$referencia = $_POST['referencia'] ?? null;
$recibido = isset($_POST['recibido']) ? (float)$_POST['recibido'] : null;
$vuelto = isset($_POST['vuelto']) ? (float)$_POST['vuelto'] : null;

if (!$id_cita || !$metodo_pago) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verificar que la cita existe y no está pagada
    $sql_cita = "SELECT c.*, cl.id as id_cliente 
                 FROM CITA c
                 JOIN MASCOTA m ON c.id_mascota = m.id
                 JOIN CLIENTE cl ON m.id_cliente = cl.id
                 WHERE c.id = ?";
    $stmt = $conn->prepare($sql_cita);
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $cita = $stmt->get_result()->fetch_assoc();
    
    if (!$cita) {
        echo json_encode(['success' => false, 'message' => 'Cita no encontrada']);
        exit;
    }
    
    if ($cita['pagada'] == 1) {
        echo json_encode(['success' => false, 'message' => 'Esta cita ya está pagada']);
        exit;
    }
    
    // Calcular total de servicios
    $sql_servicios = "SELECT SUM(dc.precio_fijado) as total_servicios FROM DETALLE_CITA dc WHERE dc.id_cita = ?";
    $stmt_serv = $conn->prepare($sql_servicios);
    $stmt_serv->bind_param("i", $id_cita);
    $stmt_serv->execute();
    $total_servicios = $stmt_serv->get_result()->fetch_assoc()['total_servicios'] ?? 0;
    
    // Calcular total de productos
    $sql_productos = "SELECT COALESCE(SUM(dv.subtotal), 0) as total_productos 
                      FROM DETALLE_VENTA dv
                      JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                      WHERE vc.id_cita = ?";
    $stmt_prod = $conn->prepare($sql_productos);
    $stmt_prod->bind_param("i", $id_cita);
    $stmt_prod->execute();
    $total_productos = $stmt_prod->get_result()->fetch_assoc()['total_productos'] ?? 0;
    
    $total_general = $total_servicios + $total_productos;
    
    if ($total_general <= 0) {
        echo json_encode(['success' => false, 'message' => 'No hay servicios o productos para pagar']);
        exit;
    }
    
    $empleado_id = $_SESSION['empleado_id'] ?? null;
    
    $conn->begin_transaction();
    
    // Crear venta
    $sql_venta = "INSERT INTO VENTA (id_cliente, id_empleado, subtotal, iva, total, metodo_pago, estado, notas) 
                  VALUES (?, ?, ?, 0, ?, ?, 'completada', ?)";
    $stmt_venta = $conn->prepare($sql_venta);
    $notas = "Pago de cita #" . $id_cita;
    $stmt_venta->bind_param("iiddss", $cita['id_cliente'], $empleado_id, $total_general, $total_general, $metodo_pago, $notas);
    $stmt_venta->execute();
    $id_venta = $conn->insert_id;
    
    // Insertar productos en detalle (si hay)
    $sql_productos_lista = "SELECT dv.id_producto, dv.cantidad, dv.precio_unitario, dv.subtotal
                           FROM DETALLE_VENTA dv
                           JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                           WHERE vc.id_cita = ?";
    $stmt_prod_lista = $conn->prepare($sql_productos_lista);
    $stmt_prod_lista->bind_param("i", $id_cita);
    $stmt_prod_lista->execute();
    $productos = $stmt_prod_lista->get_result();
    
    while($prod = $productos->fetch_assoc()) {
        $sql_detalle = "INSERT INTO DETALLE_VENTA (id_venta, id_producto, cantidad, precio_unitario, subtotal) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt_detalle = $conn->prepare($sql_detalle);
        $stmt_detalle->bind_param("iiidd", $id_venta, $prod['id_producto'], $prod['cantidad'], $prod['precio_unitario'], $prod['subtotal']);
        $stmt_detalle->execute();
    }
    
    // Relacionar venta con cita
    $sql_relacion = "INSERT INTO VENTA_CITA (id_cita, id_venta) VALUES (?, ?)";
    $stmt_rel = $conn->prepare($sql_relacion);
    $stmt_rel->bind_param("ii", $id_cita, $id_venta);
    $stmt_rel->execute();
    
    // Registrar pago en auditoría
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $sql_auditoria = "INSERT INTO AUDITORIA_PAGOS (id_cita, monto, metodo_pago, referencia, id_empleado, ip_usuario, accion) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pago')";
    $stmt_audit = $conn->prepare($sql_auditoria);
    $stmt_audit->bind_param("idssis", $id_cita, $total_general, $metodo_pago, $referencia, $empleado_id, $ip_usuario);
    $stmt_audit->execute();
    
    // Actualizar cita como pagada
    $sql_update = "UPDATE CITA SET pagada = 1, metodo_pago = ?, referencia_pago = ?,
                    pago_registrado_por = ?, pago_fecha_registro = NOW(), pago_ip_usuario = ?
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssisi", $metodo_pago, $referencia, $empleado_id, $ip_usuario, $id_cita);
    $stmt_update->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado exitosamente',
        'id_venta' => $id_venta,
        'id_cita' => $id_cita,
        'total' => $total_general,
        'recibido' => $recibido,
        'vuelto' => $vuelto
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>