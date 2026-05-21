<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/includes/conexion.php';

$id_venta = (int)($_GET['venta_id'] ?? 0);
if (!$id_venta) {
    die('Venta no encontrada');
}

// Verificar que la venta existe
$sql = "SELECT v.*, c.nombre as cliente_nombre 
        FROM VENTA v
        JOIN CLIENTE c ON v.id_cliente = c.id
        WHERE v.id = ? AND v.estado = 'completada'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_venta);
$stmt->execute();
$venta = $stmt->get_result()->fetch_assoc();

if (!$venta) {
    die('Venta no encontrada');
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_cliente = trim($_POST['nombre_cliente']);
    $rfc = strtoupper(trim($_POST['rfc']));
    $razon_social = trim($_POST['razon_social']);
    $regimen_fiscal = trim($_POST['regimen_fiscal']);
    $uso_cfdi = trim($_POST['uso_cfdi']);
    
    if (empty($rfc) || empty($razon_social)) {
        $error = "RFC y Razón Social son obligatorios";
    } else {
        // Llamar al procedimiento almacenado
        $sql_proc = "CALL registrar_factura(?, ?, ?, ?, ?, ?, @resultado, @mensaje)";
        $stmt_proc = $conn->prepare($sql_proc);
        $stmt_proc->bind_param("isssss", $id_venta, $razon_social, $rfc, $razon_social, $regimen_fiscal, $uso_cfdi);
        $stmt_proc->execute();
        
        // Obtener el resultado
        $result = $conn->query("SELECT @resultado as resultado, @mensaje as mensaje");
        $row = $result->fetch_assoc();
        
        if ($row['resultado'] == 1) {
            $mensaje = $row['mensaje'];
        } else {
            $error = $row['mensaje'];
        }
        
        $stmt_proc->close();
        $conn->next_result();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitar Factura - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        body { background: #f5f5f5; font-family: var(--font-main); }
        .form-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .form-container h1 { color: var(--primary); text-align: center; margin-bottom: 5px; }
        .form-container h2 { text-align: center; font-size: 18px; color: #666; margin-bottom: 25px; }
        .info-venta {
            background: #f0f7ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn-enviar {
            background: var(--primary);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-enviar:hover { background: var(--primary-dark); }
        .mensaje-exito { background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
        .mensaje-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>🐾 BIOSPET</h1>
        <h2>Solicitar Factura</h2>
        
        <div class="info-venta">
            <p><strong>Venta #:</strong> <?php echo $id_venta; ?></p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></p>
            <p><strong>Total:</strong> $<?php echo number_format($venta['total'], 2); ?></p>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="mensaje-exito"><?php echo $mensaje; ?></div>
            <a href="index.php" class="btn-enviar" style="text-align: center; text-decoration: none; display: block;">← Volver al Inicio</a>
        <?php elseif ($error): ?>
            <div class="mensaje-error"><?php echo $error; ?></div>
            <form method="POST">
                <!-- Mostrar formulario nuevamente -->
                <?php include 'facturar_form.php'; ?>
            </form>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>Razón Social *</label>
                    <input type="text" name="razon_social" required>
                </div>
                
                <div class="form-group">
                    <label>RFC *</label>
                    <input type="text" name="rfc" placeholder="XAXX010101000" maxlength="13" required>
                </div>
                
                <div class="form-group">
                    <label>Régimen Fiscal</label>
                    <select name="regimen_fiscal">
                        <option value="">Seleccionar...</option>
                        <option value="RESICO">RESICO - Régimen Simplificado de Confianza</option>
                        <option value="GENERAL">Régimen General</option>
                        <option value="PERSONA_FISICA">Persona Física</option>
                        <option value="PERSONA_MORAL">Persona Moral</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Uso de CFDI</label>
                    <select name="uso_cfdi">
                        <option value="">Seleccionar...</option>
                        <option value="G01">G01 - Adquisición de mercancías</option>
                        <option value="G03">G03 - Gastos en general</option>
                        <option value="D01">D01 - Honorarios médicos</option>
                        <option value="D04">D04 - Donativos</option>
                    </select>
                </div>
                <button type="submit" class="btn-enviar">📄 Solicitar Factura</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>