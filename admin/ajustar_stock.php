<?php
// admin/ajustar_stock.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $producto_id = $_POST['producto_id'];
    $nuevo_stock = $_POST['nuevo_stock'];
    $motivo = $_POST['motivo'];
    
    $stmt = $conn->prepare("CALL ajustar_stock(?, ?, ?, ?, @ajuste)");
    $stmt->bind_param("iisi", $producto_id, $nuevo_stock, $motivo, $_SESSION['empleado_id']);
    $stmt->execute();
    
    $result = $conn->query("SELECT @ajuste as ajuste");
    $ajuste = $result->fetch_assoc();
    
    echo "Stock ajustado en " . $ajuste['ajuste'] . " unidades";
}
?>