<?php
session_start();

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol para acceso (solo super_admin puede eliminar)
if ($_SESSION['rol'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_proveedor = (int)($_GET['id'] ?? 0);
if (!$id_proveedor) {
    header('Location: proveedores.php');
    exit;
}

// Obtener datos del proveedor para mostrar en la confirmación
$sql = "SELECT * FROM PROVEEDOR WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_proveedor);
$stmt->execute();
$proveedor = $stmt->get_result()->fetch_assoc();

if (!$proveedor) {
    header('Location: proveedores.php');
    exit;
}

// Verificar si el proveedor tiene compras asociadas
$sql_check = "SELECT COUNT(*) as total FROM COMPRA WHERE id_proveedor = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("i", $id_proveedor);
$stmt_check->execute();
$result_check = $stmt_check->get_result();
$tiene_compras = $result_check->fetch_assoc()['total'];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirmar'])) {
        // Si tiene compras, no se puede eliminar
        if ($tiene_compras > 0) {
            $_SESSION['error'] = "No se puede eliminar el proveedor porque tiene {$tiene_compras} compra(s) asociada(s).";
            header('Location: proveedores.php');
            exit;
        }
        
        // Eliminar el proveedor
        $sql_delete = "DELETE FROM PROVEEDOR WHERE id = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        $stmt_delete->bind_param("i", $id_proveedor);
        
        if ($stmt_delete->execute()) {
            $_SESSION['mensaje'] = "Proveedor eliminado correctamente";
            header('Location: proveedores.php');
            exit;
        } else {
            $error = "Error al eliminar: " . $conn->error;
        }
    } else {
        header('Location: proveedores.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Proveedor - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/proveedor_eliminar.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Eliminar Proveedor</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="proveedores.php">🏭 Proveedores</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <div class="warning-icon">⚠️</div>
        <h2>¿Eliminar Proveedor?</h2>
        
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="proveedor-info">
            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($proveedor['nombre']); ?></p>
            <p><strong>Contacto:</strong> <?php echo htmlspecialchars($proveedor['contacto_nombre'] ?? 'No especificado'); ?></p>
            <p><strong>Teléfono:</strong> <?php echo $proveedor['telefono'] ?: 'No registrado'; ?></p>
            <p><strong>Email:</strong> <?php echo $proveedor['email'] ?: 'No registrado'; ?></p>
        </div>
        
        <?php if ($tiene_compras > 0): ?>
            <div class="warning-message">
                ⚠️ Este proveedor tiene <strong><?php echo $tiene_compras; ?> compra(s)</strong> asociadas.<br>
                No se puede eliminar. Puedes desactivarlo en su lugar.
            </div>
            <div class="buttons">
                <a href="proveedores.php" class="btn-cancelar">Volver</a>
            </div>
        <?php else: ?>
            <p style="color: #666; margin: 20px 0;">
                Esta acción no se puede deshacer. Se eliminará permanentemente el proveedor.
            </p>
            <form method="POST">
                <div class="buttons">
                    <button type="submit" name="confirmar" value="1" class="btn-eliminar">🗑️ Sí, Eliminar</button>
                    <a href="proveedores.php" class="btn-cancelar">Cancelar</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>