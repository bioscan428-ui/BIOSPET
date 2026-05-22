<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$id_empleado = (int)($_POST['id_empleado'] ?? 0);
$tipo_documento = $_POST['tipo_documento'] ?? '';
$descripcion = trim($_POST['descripcion'] ?? '');

if (!$id_empleado || !$tipo_documento || !isset($_FILES['archivo'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$archivo = $_FILES['archivo'];
if ($archivo['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Error al subir el archivo']);
    exit;
}

$extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
$extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
if (!in_array(strtolower($extension), $extensiones_permitidas)) {
    echo json_encode(['success' => false, 'error' => 'Formato no permitido. Use PDF, JPG o PNG']);
    exit;
}

// Crear directorio si no existe
$upload_dir = __DIR__ . '/../assets/uploads/expedientes/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generar nombre único
$nombre_archivo = 'exp_' . $id_empleado . '_' . time() . '_' . uniqid() . '.' . $extension;
$ruta_destino = $upload_dir . $nombre_archivo;

if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
    $ruta_db = 'assets/uploads/expedientes/' . $nombre_archivo;
    
    $sql = "INSERT INTO EXPEDIENTE_EMPLEADO (id_empleado, tipo_documento, nombre_archivo, ruta_archivo, tamano, tipo_archivo, descripcion, subido_por) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $tamano = $archivo['size'];
    $tipo_archivo = strtoupper($extension);
    $subido_por = $_SESSION['empleado_id'] ?? null;
    $stmt->bind_param("isssissi", $id_empleado, $tipo_documento, $archivo['name'], $ruta_db, $tamano, $tipo_archivo, $descripcion, $subido_por);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Documento subido correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar en BD']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al mover el archivo']);
}
?>