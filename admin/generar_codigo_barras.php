<?php
// admin/generar_codigo_barras.php
session_start();
require_once __DIR__ . '/../includes/conexion.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Función para calcular dígito de control
function calcularDigitoControl($codigo) {
    $suma = 0;
    $longitud = strlen($codigo);
    $alternar = true;
    
    for ($i = $longitud - 1; $i >= 0; $i--) {
        $digito = intval($codigo[$i]);
        if ($alternar) {
            $digito *= 2;
            if ($digito > 9) {
                $digito = ($digito % 10) + 1;
            }
        }
        $suma += $digito;
        $alternar = !$alternar;
    }
    
    $resto = $suma % 10;
    return ($resto == 0) ? 0 : (10 - $resto);
}

// Función para generar código único
function generarCodigoUnico($conn, $intento = 1) {
    $prefijo = 'BIO';
    $fecha = date('ymd');
    $aleatorio = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    $codigo_base = $prefijo . $fecha . $aleatorio;
    $digito_control = calcularDigitoControl($codigo_base);
    $codigo = $codigo_base . $digito_control;
    
    // Verificar si ya existe
    $sql = "SELECT id FROM PRODUCTO WHERE codigo_barras = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $codigo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0 && $intento < 5) {
        return generarCodigoUnico($conn, $intento + 1);
    }
    
    return $codigo;
}

// Generar y devolver código
$codigo = generarCodigoUnico($conn);
echo json_encode(['success' => true, 'codigo' => $codigo]);
?>