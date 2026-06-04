<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$mensaje = '';
$error = '';

// Obtener estado actual de la promoción en grooming
$sql_cat = "SELECT id, promocion_activa, porcentaje_promocion 
            FROM CATEGORIA_PRODUCTO 
            WHERE nombre = 'GROOMING'";
$result = $conn->query($sql_cat);
$grooming = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'];
    $porcentaje = (int)$_POST['porcentaje'] ?? 20;
    
    if ($accion === 'activar') {
        $sql = "UPDATE CATEGORIA_PRODUCTO 
                SET promocion_activa = 1, porcentaje_promocion = ? 
                WHERE nombre = 'GROOMING'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $porcentaje);
        if ($stmt->execute()) {
            $mensaje = "✅ Promoción ACTIVADA en Grooming con $porcentaje% de descuento";
        } else {
            $error = "❌ Error al activar promoción";
        }
        $stmt->close();
    } elseif ($accion === 'desactivar') {
        $sql = "UPDATE CATEGORIA_PRODUCTO 
                SET promocion_activa = 0, porcentaje_promocion = 0 
                WHERE nombre = 'GROOMING'";
        if ($conn->query($sql)) {
            $mensaje = "✅ Promoción DESACTIVADA en Grooming";
        } else {
            $error = "❌ Error al desactivar promoción";
        }
    }
    
    // Recargar datos
    $result2 = $conn->query($sql_cat);
    $grooming = $result2->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promociones - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .container { max-width: 600px; margin: 50px auto; background: white; border-radius: 20px; padding: 30px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); text-align: center; }
        .btn-activar { background: #4caf50; color: white; border: none; padding: 15px 30px; border-radius: 10px; cursor: pointer; font-size: 18px; margin: 10px; }
        .btn-desactivar { background: #f44336; color: white; border: none; padding: 15px 30px; border-radius: 10px; cursor: pointer; font-size: 18px; margin: 10px; }
        .estado-activo { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .estado-inactivo { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .porcentaje-input { margin: 20px 0; }
        .porcentaje-input input { width: 100px; padding: 10px; text-align: center; font-size: 18px; }
        h1 { color: #E68D0B; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Promociones</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <h1>🎉 Promoción Grooming</h1>
        
        <?php if ($mensaje): ?>
            <div class="estado-activo"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="estado-inactivo"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($grooming['promocion_activa']): ?>
            <div class="estado-activo">
                🔥 PROMOCIÓN ACTIVA 🔥<br>
                <?php echo $grooming['porcentaje_promocion']; ?>% de descuento en todos los servicios de Grooming
            </div>
        <?php else: ?>
            <div class="estado-inactivo">
                ⚠️ PROMOCIÓN INACTIVA ⚠️<br>
                No hay descuentos activos en Grooming
            </div>
        <?php endif; ?>

        <form method="POST">
            <?php if ($grooming['promocion_activa']): ?>
                <button type="submit" name="accion" value="desactivar" class="btn-desactivar">🔴 Desactivar Promoción</button>
            <?php else: ?>
                <div class="porcentaje-input">
                    <label>Porcentaje de descuento:</label>
                    <input type="number" name="porcentaje" value="20" min="1" max="100" required>
                    <small>%</small>
                </div>
                <button type="submit" name="accion" value="activar" class="btn-activar">🟢 Activar Promoción</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>