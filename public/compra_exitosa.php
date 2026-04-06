<?php
session_start();
$id_venta = (int)($_GET['id'] ?? 0);
if (!$id_venta) {
    header('Location: tienda.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compra Exitosa - BIOSPET</title>
    <link rel="stylesheet" href="assets/css/global.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 80px auto;
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-soft);
        }
        .icono {
            font-size: 5rem;
        }
        .btn {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="icono">✅</div>
        <h1>¡Compra Exitosa!</h1>
        <p>Gracias por tu compra. Tu número de pedido es: <strong>#<?php echo $id_venta; ?></strong></p>
        <p>Te enviaremos un correo con los detalles de tu pedido.</p>
        <a href="tienda.php" class="btn">Seguir comprando</a>
    </div>
</body>
</html>