<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener fecha para el corte (por defecto hoy)
$fecha_corte = $_GET['fecha'] ?? date('Y-m-d');

// Obtener empleado que realiza el corte
$empleado_nombre = $_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Administrador';
$empleado_id = $_SESSION['empleado_id'] ?? null;

// Llamar al procedimiento caja_diaria (actualizado previamente)
$stmt = $conn->prepare("CALL caja_diaria(?)");
$stmt->bind_param("s", $fecha_corte);
$stmt->execute();
$result = $stmt->get_result();
$caja = $result->fetch_assoc();
$stmt->close();
$conn->next_result();

// Calcular total general
$total_general = ($caja['total_ventas'] ?? 0) + ($caja['total_servicios'] ?? 0);
$efectivo_total = $caja['efectivo'] ?? 0;
$electronico_total = $caja['electronico'] ?? 0;

// Verificar si ya se hizo corte para esta fecha
$sql_verificar = "SELECT * FROM REGISTRO_CORTE WHERE fecha_corte = ?";
$stmt_verificar = $conn->prepare($sql_verificar);
$stmt_verificar->bind_param("s", $fecha_corte);
$stmt_verificar->execute();
$corte_existente = $stmt_verificar->get_result()->fetch_assoc();

$mensaje = '';
$error = '';

// Procesar cierre de caja
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cerrar_caja') {
    if ($corte_existente) {
        $error = "⚠️ Ya se realizó un corte para esta fecha. No se puede volver a cerrar.";
    } else {
        $observaciones = $_POST['observaciones'] ?? '';
        
        $sql_insert = "INSERT INTO REGISTRO_CORTE (fecha_corte, id_empleado, total_ventas, total_servicios, total_efectivo, total_electronico, total_general, observaciones) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("siddddds", $fecha_corte, $empleado_id, $caja['total_ventas'], $caja['total_servicios'], $efectivo_total, $electronico_total, $total_general, $observaciones);
        
        if ($stmt_insert->execute()) {
            $mensaje = "✅ Corte de caja cerrado exitosamente a las " . date('H:i:s');
            // Recargar la página para mostrar el estado actualizado
            header("Location: corte_caja.php?fecha=$fecha_corte&mensaje=" . urlencode($mensaje));
            exit;
        } else {
            $error = "❌ Error al cerrar el corte: " . $conn->error;
        }
    }
}

// Mostrar mensajes
if (isset($_GET['mensaje'])) {
    $mensaje = $_GET['mensaje'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte de Caja - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/corte_caja.css">
</head>
<body>
    <div class="admin-header no-print">
        <h1>🐾 BIOSPET - Corte de Caja</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="punto_venta.php">🛒 Punto de Venta</a>
            <a href="reportes.php">📊 Reportes</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="corte-container">
        <div class="header">
            <h1>🐾 BIOSPET</h1>
            <h2>Corte de Caja Diario</h2>
            <p><?php echo date('d/m/Y', strtotime($fecha_corte)); ?></p>
            <p>Realizado por: <?php echo htmlspecialchars($empleado_nombre); ?></p>
        </div>

        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="mensaje-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($corte_existente): ?>
            <div class="corte-realizado">
                <p>✅ <strong>Corte de caja ya realizado para esta fecha</strong></p>
                <p>Realizado el: <?php echo date('d/m/Y H:i:s', strtotime($corte_existente['fecha_registro'])); ?></p>
                <p>Por: <?php echo htmlspecialchars($empleado_nombre); ?></p>
                <?php if($corte_existente['observaciones']): ?>
                <p>Observaciones: <?php echo htmlspecialchars($corte_existente['observaciones']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="fecha-selector no-print">
            <form method="GET" action="">
                <label>Seleccionar fecha:</label>
                <input type="date" name="fecha" value="<?php echo $fecha_corte; ?>">
                <button type="submit" class="btn-buscar">🔍 Consultar</button>
            </form>
            <div>
                <button onclick="window.print()" class="btn-imprimir">🖨️ Imprimir Corte</button>
                <a href="dashboard.php" class="btn-volver" style="margin-left: 10px;">⬅️ Volver</a>
            </div>
        </div>

        <!-- Tarjetas principales -->
        <div class="totales-grid">
            <div class="total-card">
                <div class="label">🛒 Ventas de Productos</div>
                <div class="monto">$<?php echo number_format($caja['total_ventas'] ?? 0, 2); ?></div>
            </div>
            <div class="total-card">
                <div class="label">🏥 Servicios Médicos</div>
                <div class="monto">$<?php echo number_format($caja['total_servicios'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- Resumen detallado -->
        <div class="resumen-detalle">
            <h3 style="margin-bottom: 15px;">📋 Detalle de Ingresos</h3>
            
            <div class="resumen-item">
                <span class="label">💵 Efectivo:</span>
                <span class="valor">$<?php echo number_format($caja['efectivo'] ?? 0, 2); ?></span>
            </div>
            <div class="resumen-item">
                <span class="label">💳 Tarjeta / Transferencia:</span>
                <span class="valor">$<?php echo number_format($caja['electronico'] ?? 0, 2); ?></span>
            </div>
            
            <div style="margin: 15px 0; border-top: 1px dashed #ddd;"></div>
            
            <div class="resumen-item">
                <span class="label">📊 Número de transacciones (ventas):</span>
                <span><?php echo $caja['numero_ventas'] ?? 0; ?></span>
            </div>
            <div class="resumen-item">
                <span class="label">📊 Número de servicios (citas):</span>
                <span><?php echo $caja['numero_servicios'] ?? 0; ?></span>
            </div>
        </div>

        <!-- Total general -->
        <div class="total-general">
            <span>💰 TOTAL GENERAL DEL DÍA</span>
            <span>$<?php echo number_format($total_general, 2); ?></span>
        </div>

        <!-- Formulario para cerrar caja (solo si no hay corte realizado) -->
        <?php if (!$corte_existente): ?>
        <div class="seccion-cerrar">
            <h3 style="margin-bottom: 15px;">🔒 Cerrar Corte de Caja</h3>
            <form method="POST" onsubmit="return confirm('¿Estás seguro de cerrar el corte de caja? Una vez cerrado, no se podrán modificar las ventas de esta fecha.')">
                <input type="hidden" name="accion" value="cerrar_caja">
                <div class="form-group">
                    <label>Observaciones (opcional)</label>
                    <textarea name="observaciones" rows="2" placeholder="Notas sobre el corte, diferencias, etc."></textarea>
                </div>
                <button type="submit" class="btn-cerrar">🔒 Cerrar Corte de Caja</button>
            </form>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Este documento es un comprobante interno de caja</p>
            <p>Fecha y hora de emisión: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
</body>
</html>