<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para cambiar estado o registrar pago
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista', 'caja', 'groomer'])) {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';
require_once __DIR__ . '/../includes/enviar_email.php';

$id_cita = (int)($_GET['id'] ?? 0);
$accion = $_GET['accion'] ?? $_GET['estado'] ?? '';
$metodo_pago = $_GET['metodo'] ?? '';

// ========== REGISTRAR PAGO ==========
if ($accion === 'pagar') {
    if (!$id_cita) {
        die("ID de cita inválido");
    }
    
    $sql = "UPDATE CITA SET pagada = 1, metodo_pago = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $metodo_pago, $id_cita);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Pago registrado correctamente";
    } else {
        $_SESSION['error'] = "Error al registrar el pago: " . $conn->error;
    }
    
    header("Location: detalle_cita.php?id=$id_cita");
    exit;
}

// ========== CAMBIAR ESTADO DE CITA ==========
$estados_validos = ['pendiente', 'confirmada', 'cancelada', 'completada'];

if (!$id_cita || !in_array($accion, $estados_validos)) {
    die("Parámetros inválidos");
}

// Si se confirma, obtener datos para el email
if ($accion === 'confirmada') {
    $sql_datos = "SELECT 
                    cl.email,
                    cl.nombre AS nombre_cliente,
                    c.fecha_cita,
                    c.hora_cita,
                    GROUP_CONCAT(s.nombre_servicio SEPARATOR ', ') AS servicios,
                    SUM(dc.precio_fijado) AS total
                FROM CITA c
                JOIN MASCOTA m ON c.id_mascota = m.id
                JOIN CLIENTE cl ON m.id_cliente = cl.id
                JOIN DETALLE_CITA dc ON c.id = dc.id_cita
                JOIN SERVICIO s ON dc.id_servicio = s.id
                WHERE c.id = ?
                GROUP BY c.id";
    
    $stmt = $conn->prepare($sql_datos);
    $stmt->bind_param("i", $id_cita);
    $stmt->execute();
    $result = $stmt->get_result();
    $cita = $result->fetch_assoc();
    
    if ($cita && !empty($cita['email'])) {
        $fecha_formateada = date('d/m/Y', strtotime($cita['fecha_cita']));
        enviarEmailConfirmacion(
            $cita['email'],
            $cita['nombre_cliente'],
            $fecha_formateada,
            $cita['hora_cita'],
            $cita['servicios'],
            $cita['total']
        );
    }
}

// Actualizar estado de la cita
$sql = "UPDATE CITA SET estado = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $accion, $id_cita);
$stmt->execute();

header('Location: detalle_cita.php?id=' . $id_cita);
exit;
?>