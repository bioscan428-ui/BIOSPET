<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../../includes/conexion.php';

$tipo_formato = $_POST['tipo_formato'] ?? '';
$id_usuario = $_SESSION['user_id'] ?? 0;
$ip = $_SERVER['REMOTE_ADDR'];

// Crear tabla si no existe
$sql_create = "CREATE TABLE IF NOT EXISTS FORMATOS_DIGITALES (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_formato VARCHAR(50),
    datos JSON,
    id_usuario INT,
    ip_usuario VARCHAR(45),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($sql_create);

// Guardar datos
$datos = json_encode($_POST, JSON_UNESCAPED_UNICODE);

$sql = "INSERT INTO FORMATOS_DIGITALES (tipo_formato, datos, id_usuario, ip_usuario) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssis", $tipo_formato, $datos, $id_usuario, $ip);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Formulario Enviado - BIOSPET</title>
        <link rel="stylesheet" href="../../assets/css/global.css">
        <style>
            body { background: #f5f5f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: Arial; }
            .success-container { text-align: center; background: white; padding: 40px; border-radius: 20px; max-width: 500px; margin: 20px; }
            .success-icon { font-size: 64px; color: #4caf50; }
            .btn { background: #E68D0B; color: white; padding: 12px 25px; border-radius: 8px; text-decoration: none; display: inline-block; margin: 10px; }
            .btn-secondary { background: #666; }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">✅</div>
            <h2>¡Formulario enviado correctamente!</h2>
            <p>Tu información ha sido registrada.</p>
            <div>
                <a href="<?php echo $_POST['return_url'] ?? 'ingreso_estetica.php'; ?>" class="btn">📝 Llenar otro formato</a>
                <a href="../dashboard.php" class="btn btn-secondary">📋 Ir al Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    echo "<p style='color:red; text-align:center;'>Error al guardar el formulario</p>";
}
?>