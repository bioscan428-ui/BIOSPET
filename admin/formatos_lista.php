<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}
require_once '../../includes/conexion.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formatos Digitales Guardados - BIOSPET</title>
    <link rel="stylesheet" href="../../assets/css/global.css">
    <style>
        body { background: #f5f5f5; }
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background: white; border-radius: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; color: #666; }
        .btn-ver { background: #2196f3; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 12px; }
        .tipo-badge { background: #E68D0B; color: white; padding: 4px 8px; border-radius: 20px; font-size: 11px; display: inline-block; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>📁 Formatos Digitales Guardados</h1>
        <div>
            <a href="../dashboard.php">📋 Dashboard</a>
            <a href="consentimiento_informado.php">📝 Nuevo Formato</a>
            <a href="../../logout.php">🚪 Salir</a>
        </div>
    </div>

    <div class="container">
        <h2>Listado de Formularios Registrados</h2>
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tipo de Formato</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>IP</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT id, tipo_formato, datos, fecha_registro, ip_usuario FROM FORMATOS_DIGITALES ORDER BY id DESC";
                    $result = $conn->query($sql);
                    while($row = $result->fetch_assoc()):
                        $datos = json_decode($row['datos'], true);
                        $cliente = $datos['propietario_nombre'] ?? $datos['nombre_propietario'] ?? $datos['firma'] ?? 'No especificado';
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><span class="tipo-badge"><?php echo str_replace('_', ' ', $row['tipo_formato']); ?></span></td>
                        <td><?php echo htmlspecialchars($cliente); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($row['fecha_registro'])); ?></td>
                        <td><?php echo $row['ip_usuario']; ?></td>
                        <td><a href="ver_formato.php?id=<?php echo $row['id']; ?>" class="btn-ver">Ver Detalle</a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>