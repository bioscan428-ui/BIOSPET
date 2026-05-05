<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar rol
if (!in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

// Obtener productos activos con stock
$sql_productos = "SELECT p.*, c.nombre as categoria 
                  FROM PRODUCTO p
                  JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
                  WHERE p.activo = 1 AND p.stock_actual > 0
                  ORDER BY p.nombre ASC";
$productos = $conn->query($sql_productos);

// Obtener clientes para seleccionar
$sql_clientes = "SELECT id, CONCAT(nombre, ' ', IFNULL(ape_pat, '')) as nombre 
                 FROM CLIENTE WHERE activo = 1 ORDER BY nombre ASC";
$clientes = $conn->query($sql_clientes);

// Obtener empleado actual (para registro de venta)
$empleado_id = $_SESSION['empleado_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punto de Venta - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/punto_venta.css">
    <style>
        /* Estilos adicionales para el buscador de código */
        .buscador-codigo input {
            font-family: monospace;
            font-size: 18px !important;
            letter-spacing: 1px;
        }
        .buscador-codigo input:focus {
            border-color: #4caf50;
            outline: none;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🛒 Punto de Venta - BIOSPET</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="productos.php">📦 Productos</a>
            <a href="clientes.php">👥 Clientes</a>
            <a href="logout.php">🚪 Salir</a>
        </div>
    </div>

    <div class="container">
        <div class="pos-container">
            <!-- Panel izquierdo: Productos -->
            <div class="productos-panel">
                <!-- ====== CÓDIGO DE BARRAS ====== -->
                <div class="buscador-codigo" style="margin-bottom: 15px;">
                    <input type="text" id="buscadorCodigo" 
                           placeholder="📷 Escanea código de barras o ingresa número" 
                           style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
                </div>
                
                <!-- Buscador por nombre -->
                <div class="buscador">
                    <input type="text" id="buscador" placeholder="🔍 Buscar producto por nombre..." autocomplete="off">
                </div>
                
                <div class="productos-grid" id="productosGrid">
                    <?php while($prod = $productos->fetch_assoc()): ?>
                        <div class="producto-card" data-id="<?php echo $prod['id']; ?>" 
                             data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>"
                             data-precio="<?php echo $prod['precio_venta']; ?>"
                             data-stock="<?php echo $prod['stock_actual']; ?>"
                             data-codigo="<?php echo $prod['codigo_barras'] ?? ''; ?>">
                            <div class="nombre"><?php echo htmlspecialchars($prod['nombre']); ?></div>
                            <div class="precio">$<?php echo number_format($prod['precio_venta'], 2); ?></div>
                            <div class="stock <?php echo $prod['stock_actual'] < 10 ? 'stock-bajo' : ''; ?>">
                                📦 Stock: <?php echo $prod['stock_actual']; ?>
                            </div>
                            <?php if(!empty($prod['codigo_barras'])): ?>
                                <div class="codigo" style="font-size: 10px; color: #999; margin-top: 5px;">
                                    📷 <?php echo $prod['codigo_barras']; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Panel derecho: Carrito -->
            <div class="carrito-panel">
                <div class="carrito-header">
                    <h3>🛒 Carrito de compras</h3>
                </div>
                
                <div class="carrito-items" id="carritoItems">
                    <div style="text-align: center; color: #999; padding: 40px;">
                        No hay productos agregados
                    </div>
                </div>
                
                <div class="carrito-total">
                    <div class="total-row">
                        <span>Subtotal:</span>
                        <span id="subtotal">$0.00</span>
                    </div>
                </div>
                
                <div class="cliente-selector">
                    <label>👤 Cliente *</label>
                    <select id="id_cliente">
                        <option value="">-- Seleccionar cliente --</option>
                        <?php while($cliente = $clientes->fetch_assoc()): ?>
                            <option value="<?php echo $cliente['id']; ?>">
                                <?php echo htmlspecialchars($cliente['nombre']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="metodo-pago">
                    <label>💳 Método de pago *</label>
                    <select id="metodo_pago">
                        <option value="">-- Seleccionar --</option>
                        <option value="efectivo">💵 Efectivo</option>
                        <option value="tarjeta">💳 Tarjeta</option>
                        <option value="transferencia">🏦 Transferencia</option>
                    </select>
                </div>
                
                <button class="btn-vaciar" id="btnVaciar" style="display: none;">🗑️ Vaciar carrito</button>
                <button class="btn-finalizar" id="btnFinalizar" disabled>💰 Finalizar venta</button>
            </div>
        </div>
    </div>

    <!-- Modal de pago exitoso -->
    <div id="modalExito" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-modal" onclick="cerrarModal()">&times;</span>
                <h3>💰 Finalizar Venta</h3>
            </div>
            <div class="modal-body" id="modalBody">
                <div id="ventaInfo">
                    <p><strong>Total a pagar:</strong> <span id="totalAPagar">$0.00</span></p>
                </div>
                <!-- NUEVO: Sección de vuelto (solo para efectivo) -->
                <div id="vueltoSection" style="display: none; margin-top: 15px;">
                    <div class="form-group">
                        <label>💵 Recibo con:</label>
                        <input type="number" id="montoRecibido" step="0.01" placeholder="0.00" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius:5px; font-size: 16px;">
                    </div>
                    <div id="vueltoInfo" style="margin-top: 10px; padding: 10px; border-radius: 5px; text-align: center; font-size: 18px;">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button onclick="confirmarVenta()" class="btn-guardar" style="background: #4caf50; width: 100%; padding: 12px;">
                        ✅ Confirmar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-------Fin del modal de pago--->
    
    <script src="../assets/js/punto_venta.js"></script>
</body>
</html>