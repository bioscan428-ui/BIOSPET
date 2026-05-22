<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$busqueda_producto = trim($_POST['buscar_producto'] ?? '');

if (!empty($busqueda_producto)) {
    // Usar el procedimiento buscar_producto
    $stmt = $conn->prepare("CALL buscar_producto(?)");
    $stmt->bind_param("s", $busqueda_producto);
    $stmt->execute();
    $productos = $stmt->get_result();
    $stmt->close();
    $conn->next_result();
} else {
    // Mostrar todos los productos activos con stock
    $sql = "SELECT id, nombre, precio_venta, stock_actual 
            FROM PRODUCTO 
            WHERE activo = 1 AND stock_actual > 0 
            ORDER BY nombre 
            LIMIT 20";
    $productos = $conn->query($sql);
}

$html = '';
if ($productos && $productos->num_rows > 0) {
    while($prod = $productos->fetch_assoc()) {
        $html .= '
        <div class="producto-item">
            <div class="producto-info">
                <div class="producto-nombre">' . htmlspecialchars($prod['nombre']) . '</div>
                <div class="producto-precio">$' . number_format($prod['precio_venta'], 2) . '</div>
                <div class="producto-stock">Stock: ' . $prod['stock_actual'] . ' unidades</div>
            </div>
            <div>
                <input type="number" id="cantidad_' . $prod['id'] . '" value="1" min="1" max="' . $prod['stock_actual'] . '" style="width: 60px; padding: 5px;">
                <button type="button" class="btn-agregar-producto" data-id="' . $prod['id'] . '" data-nombre="' . addslashes($prod['nombre']) . '" data-precio="' . $prod['precio_venta'] . '">+ Agregar</button>
            </div>
        </div>';
    }
} else {
    $html = '<p style="text-align: center; padding: 20px; color: #999;">No hay productos disponibles. ' . (!empty($busqueda_producto) ? 'Intenta con otra búsqueda.' : '') . '</p>';
}

echo json_encode(['success' => true, 'html' => $html]);
?>