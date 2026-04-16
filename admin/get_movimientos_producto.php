<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Acceso denegado');
}

require_once __DIR__ . '/../includes/conexion.php';

$producto_id = (int)($_GET['id'] ?? 0);
if (!$producto_id) {
    die('ID de producto no válido');
}

// Obtener nombre del producto
$sql_nombre = "SELECT nombre FROM PRODUCTO WHERE id = ?";
$stmt_nom = $conn->prepare($sql_nombre);
$stmt_nom->bind_param("i", $producto_id);
$stmt_nom->execute();
$producto = $stmt_nom->get_result()->fetch_assoc();

// Usar la vista para obtener movimientos del producto
$sql = "SELECT * FROM vista_movimientos_recientes WHERE producto = ? ORDER BY fecha_movimiento DESC LIMIT 50";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $producto['nombre']);
$stmt->execute();
$movimientos = $stmt->get_result();
?>

<div style="overflow-x: auto;">
    <h3 style="margin-bottom: 15px;">Producto: <?php echo htmlspecialchars($producto['nombre']); ?></h3>
    
    <?php if ($movimientos->num_rows > 0): ?>
        <table class="movimientos-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Cantidad</th>
                    <th>Empleado</th>
                    <th>Motivo</th>
                    <th>Referencia</th>
                </tr>
            </thead>
            <tbody>
                <?php while($mov = $movimientos->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                    <td>
                        <span class="tipo-badge tipo-<?php echo $mov['tipo']; ?>">
                            <?php 
                            $iconos = ['entrada' => '📥', 'salida' => '📤', 'ajuste' => '⚙️', 'devolucion' => '🔄'];
                            echo $iconos[$mov['tipo']] . ' ' . ucfirst($mov['tipo']);
                            ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo $mov['cantidad']; ?></strong> unidades
                    </td>
                    <td><?php echo htmlspecialchars($mov['empleado']); ?></td>
                    <td><?php echo $mov['motivo'] ?: '—'; ?></td>
                    <td><?php echo $mov['referencia'] ?: '—'; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #999; text-align: center; padding: 40px;">No hay movimientos registrados para este producto.</p>
    <?php endif; ?>
</div>

<style>
    .movimientos-table {
        width: 100%;
        border-collapse: collapse;
    }
    .movimientos-table th, .movimientos-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
    .movimientos-table th {
        background: #f5f5f5;
        font-weight: bold;
    }
    .tipo-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
    }
    .tipo-entrada { background: #4caf50; color: white; }
    .tipo-salida { background: #f44336; color: white; }
    .tipo-ajuste { background: #ff9800; color: white; }
    .tipo-devolucion { background: #9c27b0; color: white; }
    .modal-grande { max-width: 900px !important; width: 95% !important; }
</style>