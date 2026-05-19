<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!in_array($_SESSION['rol'], ['super_admin', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/conexion.php';

$tab = $_GET['tab'] ?? 'todos';
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if (!empty($busqueda)) {
    // Usar el procedimiento buscar_producto
    $stmt = $conn->prepare("CALL buscar_producto(?)");
    $stmt->bind_param("s", $busqueda);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->next_result();
    $criticos_count = $conn->query("SELECT COUNT(*) as total FROM vista_stock_critico")->fetch_assoc()['total'];
} elseif ($tab === 'criticos') {
    $sql = "SELECT * FROM vista_stock_critico";
    $result = $conn->query($sql);
    $criticos_count = $conn->query("SELECT COUNT(*) as total FROM vista_stock_critico")->fetch_assoc()['total'];
} else {
    $sql = "SELECT p.*, c.nombre AS categoria_nombre 
            FROM PRODUCTO p
            JOIN CATEGORIA_PRODUCTO c ON p.id_categoria = c.id
            ORDER BY p.stock_actual ASC, p.id DESC";
    $result = $conn->query($sql);
    $criticos_count = $conn->query("SELECT COUNT(*) as total FROM vista_stock_critico")->fetch_assoc()['total'];
}

// Mensaje de importación
$mensaje_importacion = '';
$tipo_mensaje = '';

if (isset($_SESSION['importacion_mensaje'])) {
    $mensaje_importacion = $_SESSION['importacion_mensaje'];
    $tipo_mensaje = $_SESSION['importacion_tipo'];
    unset($_SESSION['importacion_mensaje']);
    unset($_SESSION['importacion_tipo']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/productos.css">
    <style>
        /* Estilos para el modal de importación */
        .modal-import {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-import.active {
            display: flex;
        }
        .modal-import-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .modal-import-content h3 {
            color: var(--primary);
            margin-bottom: 20px;
        }
        .modal-import-content input[type="file"] {
            width: 100%;
            padding: 15px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            margin: 15px 0;
            cursor: pointer;
        }
        .modal-import-content input[type="file"]:hover {
            border-color: var(--primary);
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .btn-importar {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-cancelar-modal {
            background: #ccc;
            color: #333;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-excel {
            background: #1d7e3b;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-excel:hover {
            background: #156b32;
        }
        .alert-exito {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        .acciones-botones {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Gestión de Productos</h1>
        <div>
            <a href="dashboard.php">📋 Dashboard</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="productos.php">🛒 Productos</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <!-- Mensajes de importación -->
        <?php if ($mensaje_importacion): ?>
            <div class="alert-<?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje_importacion); ?>
            </div>
        <?php endif; ?>

        <div class="header-actions">
            <div class="tab-buttons">
                <a href="?tab=todos" class="tab-btn <?php echo $tab === 'todos' ? 'active' : ''; ?>">
                    📦 Todos los productos
                </a>
                <a href="?tab=criticos" class="tab-btn <?php echo $tab === 'criticos' ? 'active' : ''; ?>">
                    ⚠️ Stock crítico
                    <?php if ($criticos_count > 0): ?>
                        <span class="badge-count"><?php echo $criticos_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <!-- Buscador -->
            <form method="GET" class="buscador-form">
                <input type="hidden" name="tab" value="todos">
                <div class="buscador-wrapper">
                    <input type="text" name="buscar" placeholder="🔍 Buscar por ID, nombre o código de barras..." 
                           value="<?php echo htmlspecialchars($busqueda); ?>" 
                           class="buscador-input">
                    <button type="submit" class="btn-buscar">Buscar</button>
                    <?php if (!empty($busqueda)): ?>
                        <a href="?tab=todos" class="btn-limpiar">🗑️ Limpiar</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="acciones-botones">
                <!-- Botón Importar Excel -->
                <button type="button" class="btn-excel" id="btnAbrirModalImportar">
                    📂 Importar Excel
                </button>
                <a href="producto_nuevo.php" class="btn-nuevo">+ Nuevo Producto</a>
            </div>
        </div>

        <?php if (!empty($busqueda) && $result->num_rows === 0): ?>
            <div class="alert-warning">
                🔍 No se encontraron productos con "<strong><?php echo htmlspecialchars($busqueda); ?></strong>"
            </div>
        <?php endif; ?>

        <?php if ($tab === 'criticos' && $result->num_rows === 0 && empty($busqueda)): ?>
            <div class="alert-success">
                ¡No hay productos con stock crítico o por vencer!
            </div>
        <?php endif; ?>

        <div class="tabla-scroll-container">
            <table class="productos-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <?php if ($tab === 'criticos'): ?>
                            <th>Stock Mínimo</th>
                            <th>Vencimiento</th>
                        <?php endif; ?>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($producto = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $producto['id']; ?></td>
                            <td>
                                <?php if(!empty($producto['imagen']) && file_exists('../' . $producto['imagen'])): ?>
                                    <img src="../<?php echo $producto['imagen']; ?>" class="imagen-preview" alt="<?php echo htmlspecialchars($producto['nombre']); ?>">
                                <?php else: ?>
                                    <span class="sin-imagen">📦</span>
                                <?php endif; ?>
                             </span>
                            <td><strong><?php echo htmlspecialchars($producto['nombre']); ?></strong></td>
                            <td><?php echo $producto['categoria_nombre'] ?? $producto['categoria']; ?></td>
                            <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                            <td>
                                <?php 
                                $stock_minimo = $producto['stock_minimo'] ?? 5;
                                if ($producto['stock_actual'] <= 0): ?>
                                    <span class="stock-critico">AGOTADO</span>
                                <?php elseif ($producto['stock_actual'] <= $stock_minimo): ?>
                                    <span class="stock-bajo"><?php echo $producto['stock_actual']; ?> unidades</span>
                                <?php else: ?>
                                    <span class="stock-normal"><?php echo $producto['stock_actual']; ?> unidades</span>
                                <?php endif; ?>
                             </span>
                            <?php if ($tab === 'criticos'): ?>
                                <td><?php echo $producto['stock_minimo']; ?> unidades</span></td>
                                <td class="<?php echo ($producto['dias_vencimiento'] ?? 999) <= 30 ? 'vencimiento-critico' : 'vencimiento-normal'; ?>">
                                    <?php 
                                    if ($producto['fecha_vencimiento']) {
                                        echo date('d/m/Y', strtotime($producto['fecha_vencimiento']));
                                        if (isset($producto['dias_vencimiento']) && $producto['dias_vencimiento'] <= 30) {
                                            echo " <small>({$producto['dias_vencimiento']} días)</small>";
                                        }
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                 </span>
                            <?php endif; ?>
                            <td>
                                <?php if ($producto['activo'] ?? 1): ?>
                                    <span class="estado-activo">✅ Activo</span>
                                <?php else: ?>
                                    <span class="estado-inactivo">❌ Inactivo</span>
                                <?php endif; ?>
                             </span>
                            <td class="acciones">
                                <a href="producto_editar.php?id=<?php echo $producto['id']; ?>" class="btn-editar">✏️ Editar</a>
                                <a href="producto_eliminar.php?id=<?php echo $producto['id']; ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar este producto?')">🗑️ Eliminar</a>
                                <button onclick="verMovimientos(<?php echo $producto['id']; ?>, '<?php echo htmlspecialchars(addslashes($producto['nombre'])); ?>')" class="btn-movimientos">📦 Movimientos</button>
                            </span>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($tab === 'criticos') ? '9' : '8'; ?>" style="text-align: center; padding: 40px; color: #999;">
                                No hay productos para mostrar
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de Importación de Excel -->
    <div id="modalImportar" class="modal-import">
        <div class="modal-import-content">
            <h3>📂 Importar productos desde Excel</h3>
            <p>Selecciona un archivo Excel (.xlsx, .xls) con los siguientes campos:</p>
            <ul style="margin: 10px 0 15px 20px; color: #666;">
                <li>nombre *</li>
                <li>descripcion</li>
                <li>codigo_barras</li>
                <li>id_categoria *</li>
                <li>precio_compra</li>
                <li>precio_venta *</li>
                <li>stock_actual</li>
                <li>stock_minimo</li>
                <li>unidad_medida</li>
                <li>ubicacion</li>
                <li>fecha_vencimiento (formato YYYY-MM-DD)</li>
            </ul>
            <form id="formImportar" action="productos_importar.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="archivo_excel" accept=".xlsx, .xls" required>
                <div class="modal-buttons">
                    <button type="button" class="btn-cancelar-modal" id="btnCerrarModal">Cancelar</button>
                    <button type="submit" class="btn-importar">📤 Importar</button>
                </div>
            </form>
            <div style="margin-top: 15px; text-align: center;">
                <a href="plantilla_productos.xlsx" class="btn-descargar" style="color: var(--primary);">📎 Descargar plantilla ejemplo</a>
            </div>
        </div>
    </div>

    <!-- Modal de Movimientos de Inventario -->
    <div id="modalMovimientos" class="modal">
        <div class="modal-content modal-grande">
            <div class="modal-header" style="background: #2196f3;">
                <h2>📦 Movimientos de Inventario</h2>
                <span class="close-movimientos" style="color: white; font-size: 28px; cursor: pointer;">&times;</span>
            </div>
            <div class="modal-body" id="modalMovimientosBody">
                <div style="text-align: center; padding: 40px;">
                    Cargando...
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Modal de importación
        const modalImportar = document.getElementById('modalImportar');
        const btnAbrirModal = document.getElementById('btnAbrirModalImportar');
        const btnCerrarModal = document.getElementById('btnCerrarModal');
        
        if (btnAbrirModal) {
            btnAbrirModal.addEventListener('click', () => {
                modalImportar.classList.add('active');
            });
        }
        
        if (btnCerrarModal) {
            btnCerrarModal.addEventListener('click', () => {
                modalImportar.classList.remove('active');
            });
        }
        
        // Cerrar modal al hacer clic fuera
        modalImportar.addEventListener('click', (e) => {
            if (e.target === modalImportar) {
                modalImportar.classList.remove('active');
            }
        });
    </script>
    <script src="../assets/js/productos.js"></script>
</body>
</html>