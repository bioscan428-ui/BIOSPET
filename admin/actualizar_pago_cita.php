<?php
session_start();
require_once __DIR__ . '/../includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$id_cita = (int)$_POST['id_cita'];
$metodo_pago = $_POST['metodo_pago'] ?? '';
$referencia = $_POST['referencia'] ?? null;

// Verificar que el usuario tenga sesión de empleado
if (!isset($_SESSION['empleado_id'])) {
    $_SESSION['error'] = "No se identificó al empleado que registra el pago";
    header("Location: detalle_cita.php?id=" . $id_cita);
    exit;
}

// Calcular total a pagar (servicios + productos)
$sql_totales = "SELECT 
    COALESCE((SELECT SUM(precio_fijado) FROM DETALLE_CITA WHERE id_cita = ?), 0) as total_servicios,
    COALESCE((SELECT SUM(dv.subtotal) 
              FROM DETALLE_VENTA dv 
              JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta 
              WHERE vc.id_cita = ?), 0) as total_productos";
$stmt = $conn->prepare($sql_totales);
$stmt->bind_param("ii", $id_cita, $id_cita);
$stmt->execute();
$totales = $stmt->get_result()->fetch_assoc();
$monto_total = $totales['total_servicios'] + $totales['total_productos'];

// Verificar si ya está pagada
$check_sql = "SELECT pagada FROM CITA WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $id_cita);
$check_stmt->execute();
$check_result = $check_stmt->get_result()->fetch_assoc();

if ($check_result['pagada'] == 1) {
    $_SESSION['error'] = "Esta cita ya fue pagada anteriormente";
    header("Location: detalle_cita.php?id=" . $id_cita);
    exit;
}

// Iniciar transacción
$conn->begin_transaction();

try {
    // 1. Insertar en AUDITORIA_PAGOS (para citas)
    $sql_auditoria = "INSERT INTO AUDITORIA_PAGOS 
                      (id_cita, monto, metodo_pago, referencia, id_empleado, ip_usuario, accion) 
                      VALUES (?, ?, ?, ?, ?, ?, 'pago')";
    $stmt_audit = $conn->prepare($sql_auditoria);
    $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt_audit->bind_param("idssis", $id_cita, $monto_total, $metodo_pago, $referencia, $_SESSION['empleado_id'], $ip_usuario);
    
    if (!$stmt_audit->execute()) {
        throw new Exception("Error al registrar en auditoría: " . $stmt_audit->error);
    }
    
    // 2. Actualizar la tabla CITA
    $sql_update = "UPDATE CITA SET 
                    pagada = 1, 
                    metodo_pago = ?, 
                    referencia_pago = ?,
                    pago_registrado_por = ?,
                    pago_fecha_registro = NOW(),
                    pago_ip_usuario = ?
                   WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssisi", $metodo_pago, $referencia, $_SESSION['empleado_id'], $ip_usuario, $id_cita);
    
    if (!$stmt_update->execute()) {
        throw new Exception("Error al actualizar la cita: " . $stmt_update->error);
    }
    
    // Confirmar transacción
    $conn->commit();
    
    $_SESSION['mensaje'] = "✅ Pago registrado exitosamente por $" . number_format($monto_total, 2);
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "❌ Error al registrar el pago: " . $e->getMessage();
}

header("Location: detalle_cita.php?id=" . $id_cita);
exit;
?>