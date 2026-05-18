<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_venta = (int)($_GET['id'] ?? 0);
if (!$id_venta) {
    die('Venta no especificada');
}

// Verificar que la venta existe y no tiene factura
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

// Verificar si ya tiene factura
$sql_factura = "SELECT id FROM FACTURA WHERE id_venta = ?";
$stmt_factura = $conn->prepare($sql_factura);
$stmt_factura->bind_param("i", $id_venta);
$stmt_factura->execute();
if ($stmt_factura->get_result()->num_rows > 0) {
    die('Esta venta ya tiene una factura asociada');
}

// URL para el formulario de facturación
$url = "https://{$_SERVER['HTTP_HOST']}/biospet.bioscan.services/admin/facturar.php?id={$id_venta}";

// Función para generar QR (sin librería externa, usa API de Google Charts)
function generarQR($texto, $tamanio = 200) {
    $url = "https://chart.googleapis.com/chart?chs={$tamanio}x{$tamanio}&cht=qr&chl=" . urlencode($texto);
    return $url;
}

$qr_url = generarQR($url);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código QR para Factura - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <style>
        .qr-container {
            max-width: 500px;
            margin: 50px auto;
            background: white;
            border-radius: 16px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .qr-container h1 {
            color: var(--primary);
            margin-bottom: 10px;
        }
        .qr-code {
            margin: 30px 0;
        }
        .qr-code img {
            width: 200px;
            height: 200px;
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 10px;
        }
        .info-venta {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        .btn-imprimir {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
        }
        .btn-volver {
            background: #666;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 5px;
            display: inline-block;
            margin: 10px;
        }
        .alert {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="qr-container">
        <h1>🐾 BIOSPET</h1>
        <h2>Código QR para Facturación</h2>
        
        <div class="info-venta">
            <p><strong>Venta #:</strong> <?php echo $id_venta; ?></p>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($venta['cliente_nombre']); ?></p>
            <p><strong>Total:</strong> $<?php echo number_format($venta['total'], 2); ?></p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></p>
        </div>
        
        <div class="qr-code">
            <img src="<?php echo $qr_url; ?>" alt="Código QR para facturación">
        </div>
        
        <p>Escanea el código QR con tu celular para ingresar tus datos fiscales y obtener tu factura.</p>
        
        <div class="no-print">
            <button onclick="window.print()" class="btn-imprimir">🖨️ Imprimir QR</button>
            <a href="dashboard.php" class="btn-volver">← Volver al Dashboard</a>
        </div>
    </div>
</body>
</html>