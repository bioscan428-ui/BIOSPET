<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'caja'])) {
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmar = $_POST['confirmar'] ?? 0;
    
    if ($confirmar != 1) {
        echo json_encode(['success' => false, 'message' => 'Confirmación requerida']);
        exit;
    }
    
    // Obtener IDs de citas canceladas para auditoría
    $sql_select = "SELECT id FROM CITA WHERE estado = 'cancelada'";
    $result = $conn->query($sql_select);
    $citas_canceladas = [];
    while ($row = $result->fetch_assoc()) {
        $citas_canceladas[] = $row['id'];
    }
    
    $cantidad = count($citas_canceladas);
    
    if ($cantidad == 0) {
        echo json_encode(['success' => true, 'message' => 'No hay citas canceladas para eliminar']);
        exit;
    }
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Eliminar asignaciones de citas (ASIGNACION_CITA)
        $sql_asignaciones = "DELETE FROM ASIGNACION_CITA WHERE id_cita IN (SELECT id FROM CITA WHERE estado = 'cancelada')";
        $conn->query($sql_asignaciones);
        
        // Eliminar detalles de citas (DETALLE_CITA)
        $sql_detalles = "DELETE FROM DETALLE_CITA WHERE id_cita IN (SELECT id FROM CITA WHERE estado = 'cancelada')";
        $conn->query($sql_detalles);
        
        // Eliminar relaciones VENTA_CITA si existen
        $sql_venta_cita = "DELETE FROM VENTA_CITA WHERE id_cita IN (SELECT id FROM CITA WHERE estado = 'cancelada')";
        $conn->query($sql_venta_cita);
        
        // Eliminar las citas canceladas
        $sql_delete = "DELETE FROM CITA WHERE estado = 'cancelada'";
        $conn->query($sql_delete);
        
        $conn->commit();
        
        // Registrar en log de auditoría
        $empleado_id = $_SESSION['empleado_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_sql = "INSERT INTO AUDITORIA_PAGOS (id_cita, monto, metodo_pago, id_empleado, ip_usuario, accion) 
                    VALUES (0, 0, 'sistema', ?, ?, 'eliminacion_masiva_citas')";
        $stmt_log = $conn->prepare($log_sql);
        $stmt_log->bind_param("is", $empleado_id, $ip);
        $stmt_log->execute();
        
        echo json_encode(['success' => true, 'message' => "Se eliminaron $cantidad citas canceladas correctamente"]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>