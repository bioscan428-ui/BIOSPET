<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../../includes/conexion.php';

// Obtener datos del formulario
$tipo_formato = $_POST['tipo_formato'] ?? '';
$id_cliente = (int)($_POST['id_cliente'] ?? 0);
$id_mascota = (int)($_POST['id_mascota'] ?? 0);
$firma_nombre = trim($_POST['firma_nombre'] ?? '');
$id_empleado = $_SESSION['empleado_id'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'];

// Validar datos mínimos
$errores = [];
if ($id_cliente == 0) {
    $errores[] = "No se ha seleccionado un cliente";
}
if (empty($firma_nombre)) {
    $errores[] = "La firma es requerida";
}
if (empty($tipo_formato)) {
    $errores[] = "Tipo de formato no especificado";
}

if (!empty($errores)) {
    echo "<div style='color:red; text-align:center; padding:50px;'>";
    echo "<h3>❌ Error al guardar el formulario</h3>";
    echo "<ul>";
    foreach ($errores as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
    echo "<a href='" . ($_POST['return_url'] ?? 'ingreso_estetica.php') . "' class='btn'>← Volver</a>";
    echo "</div>";
    exit;
}

// Guardar todos los datos del formulario como JSON
$datos_a_guardar = $_POST;
unset($datos_a_guardar['tipo_formato']);
unset($datos_a_guardar['id_cliente']);
unset($datos_a_guardar['id_mascota']);
unset($datos_a_guardar['firma_nombre']);
unset($datos_a_guardar['return_url']);

$datos_json = json_encode($datos_a_guardar, JSON_UNESCAPED_UNICODE);

$sql = "INSERT INTO FORMATO_CLIENTE (id_cliente, id_mascota, tipo_formato, datos, firma_nombre, ip_usuario, id_empleado) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iissssi", $id_cliente, $id_mascota, $tipo_formato, $datos_json, $firma_nombre, $ip, $id_empleado);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    // Mostrar mensaje de éxito
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Formulario Guardado - BIOSPET</title>
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
            <h2>¡Formulario guardado correctamente!</h2>
            <p>El formato ha sido registrado en el historial del cliente.</p>
            <div>
                <a href="<?php echo $_POST['return_url'] ?? 'ingreso_estetica.php'; ?>" class="btn">📝 Llenar otro formato</a>
                <a href="formatos_lista.php" class="btn btn-secondary">📋 Ver todos los formatos</a>
                <a href="../dashboard.php" class="btn btn-secondary">📋 Ir al Dashboard</a>
            </div>
        </div>
    </body>
    </html>
    <?php
} else {
    echo "<div style='color:red; text-align:center; padding:50px;'>";
    echo "<h3>❌ Error al guardar el formulario</h3>";
    echo "<p>" . $conn->error . "</p>";
    echo "<a href='" . ($_POST['return_url'] ?? 'ingreso_estetica.php') . "' class='btn'>← Volver</a>";
    echo "</div>";
}
?>