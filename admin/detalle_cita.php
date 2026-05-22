<?php
// admin/detalle_cita.php
require_once __DIR__ . '/detalle_cita_back.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle Cita #<?php echo $id_cita; ?> - BIOSPET</title>
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/detalle_cita.css">
</head>
<body>
    <div class="admin-header">
        <h1>🐾 BIOSPET - Detalle de Cita #<?php echo $id_cita; ?></h1>
        <div>
            <a href="dashboard.php">← Volver</a>
            <a href="calendario.php">📅 Calendario</a>
            <a href="../index.php" target="_blank">🌐 Ver Sitio</a>
            <a href="logout.php">🚪 Cerrar Sesión</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="alert-success" style="background: #d4edda; color: #155724; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $_SESSION['mensaje']; unset($_SESSION['mensaje']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-error" style="background: #f8d7da; color: #721c24; padding: 12px; border-radius: 8px; margin-bottom: 20px;"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Información de la Cita -->
        <div class="section">
            <h3>📋 Información de la Cita</h3>
            <div class="info-row">
                <div class="info-label">Fecha:</div>
                <div class="info-value"><?php echo date('d/m/Y', strtotime($cita['fecha_cita'])); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Hora:</div>
                <div class="info-value"><?php echo $cita['hora_cita']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value"><span class="estado estado-<?php echo $cita['estado']; ?>"><?php echo ucfirst($cita['estado']); ?></span></div>
            </div>
            <?php if ($pago_existente): ?>
            <div class="info-row">
                <div class="info-label">Estado de pago:</div>
                <div class="info-value"><span style="background: #4caf50; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px;">✅ Pagado</span></div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Dueño -->
        <div class="section">
            <h3>👤 Dueño</h3>
            <div class="info-row">  
                <div class="info-label">Nombre:</div>
                <div class="info-value"><?php echo htmlspecialchars($cita['nombre_dueno'] . ' ' . $cita['ape_pat'] . ' ' . $cita['ape_mat']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Teléfono:</div>
                <div class="info-value"><?php echo $cita['telefono']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value"><?php echo $cita['email'] ?: 'No registrado'; ?></div>
            </div>
        </div>

        <!-- Mascota -->
        <div class="section">
            <h3>🐕 Mascota</h3>
            
            <?php if (!empty($cita['foto']) && file_exists('../' . $cita['foto'])): ?>
                <div class="foto-mascota">
                    <img src="../<?php echo $cita['foto']; ?>" alt="Foto de <?php echo htmlspecialchars($cita['nombre_mascota']); ?>">
                </div>
            <?php endif; ?>
            
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value"><?php echo htmlspecialchars($cita['nombre_mascota']); ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Especie:</div>
                <div class="info-value"><?php echo $cita['especie']; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Raza:</div>
                <div class="info-value"><?php echo $cita['raza'] ?: 'No especificada'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Género:</div>
                <div class="info-value">
                    <?php 
                    if ($cita['genero'] == 'MACHO') echo '♂️ Macho';
                    elseif ($cita['genero'] == 'HEMBRA') echo '♀️ Hembra';
                    else echo 'No registrado';
                    ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha Nac.:</div>
                <div class="info-value"><?php echo $cita['fecha_nacimiento'] ?: 'No registrada'; ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Edad:</div>
                <div class="info-value"><?php echo $edad_mascota !== null ? $edad_mascota . ' años' : 'No registrada'; ?></div>
            </div>
            
            <div class="info-row" style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 15px;">
                <div class="info-label">Carnet:</div>
                <div class="info-value">
                    <a href="../carnet_mascota.php?id=<?php echo $cita['id_mascota']; ?>" class="btn-small" target="_blank" style="background: var(--primary);">📄 Ver Carnet Digital</a>
                    <a href="../carnet_pdf.php?id=<?php echo $cita['id_mascota']; ?>" class="btn-small" target="_blank" style="background: #4caf50;">📑 Descargar PDF</a>
                </div>
            </div>
        </div>

        <!-- Motivo de Consulta -->
        <div class="section">
            <h3>📋 Motivo de Consulta / Síntomas</h3>
            <div class="sintomas-box">
                <?php echo nl2br(htmlspecialchars($cita['notas'] ?: 'No se especificaron síntomas o motivo de consulta.')); ?>
            </div>
        </div>

        <!-- Asignar Veterinario (solo admin/super_admin) -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
        <div class="section">
            <h3>👨‍⚕️ Asignar Veterinario</h3>
            <?php if ($asignado): ?>
                <div class="info-row" style="margin-bottom: 15px;">
                    <div class="info-label">Veterinario asignado:</div>
                    <div class="info-value">🩺 Dr/a. <?php echo $asignado['nombre'] . ' ' . $asignado['ape_pat']; ?></div>
                </div>
            <?php endif; ?>
            <form action="asignar_veterinario.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row">
                    <select name="id_veterinario" required style="flex:2; padding:8px;">
                        <option value="">Seleccionar...</option>
                        <?php echo $veterinarios_options; ?>
                            
                    </select>
                    <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
                </div>
            </form>
            <?php if (!$asignado && $cita['estado'] == 'confirmada'): ?>
                <p style="color: #ff9800; font-size: 12px; margin-top: 10px;">⚠️ Esta cita está confirmada pero no tiene veterinario asignado.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Asignar Asistente (solo admin/super_admin) -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
        <div class="section">
            <h3>🩺 Asignar Asistente</h3>
            <?php if ($asignado_asistente): ?>
                <div class="info-row" style="margin-bottom: 15px;">
                    <div class="info-label">Asistente asignado:</div>
                    <div class="info-value">🩺 <?php echo $asignado_asistente['nombre'] . ' ' . $asignado_asistente['ape_pat']; ?></div>
                </div>
            <?php endif; ?>
            <form action="asignar_asistente.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row">
                    <select name="id_asistente" required style="flex:2; padding:8px;">
                        <option value="">Seleccionar...</option>
                        <?php echo $asistentes_options; ?>
                    </select>
                    <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
                </div>
            </form>
        </div>
        <?php endif; ?>
        <!-- Seccion de grooming -->
        <!-- Asignar Groomer (Estética) - Solo si hay servicios de estética -->
<?php if (in_array($_SESSION['rol'], ['super_admin', 'admin']) && $tiene_servicios_estetica): ?>
<div class="section">
    <h3>✂️ Asignar Groomer (Estética)</h3>
    <?php if ($asignado_groomer): ?>
        <div class="info-row" style="margin-bottom: 15px;">
            <div class="info-label">Groomer asignado:</div>
            <div class="info-value">✂️ <?php echo $asignado_groomer['nombre'] . ' ' . $asignado_groomer['ape_pat']; ?></div>
        </div>
    <?php endif; ?>
    <form action="asignar_groomer.php" method="POST">
        <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
        <div class="form-row">
            <select name="id_groomer" required style="flex:2; padding:8px;">
                <option value="">Seleccionar...</option>
                <?php echo $groomers_options; ?>
            </select>
            <button type="submit" class="btn-small" style="background: var(--primary);">Asignar</button>
        </div>
    </form>
    <?php if (!$asignado_groomer && $cita['estado'] == 'confirmada' && $tiene_servicios_estetica): ?>
        <p style="color: #ff9800; font-size: 12px; margin-top: 10px;">⚠️ Esta cita tiene servicios de estética pero no tiene groomer asignado.</p>
    <?php endif; ?>
</div>
<?php elseif ($tiene_servicios_estetica && !in_array($_SESSION['rol'], ['super_admin', 'admin'])): ?>
<div class="section">
    <h3>✂️ Groomer (Estética)</h3>
    <?php if ($asignado_groomer): ?>
        <div class="info-row">
            <div class="info-label">Groomer asignado:</div>
            <div class="info-value">✂️ <?php echo $asignado_groomer['nombre'] . ' ' . $asignado_groomer['ape_pat']; ?></div>
        </div>
    <?php else: ?>
        <div class="info-row">
            <div class="info-label">Groomer:</div>
            <div class="info-value"><span style="color: #ff9800;">⚠️ Sin asignar</span></div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
        <!-- FIN DE Seccion de grooming -->
        <!-- Servicios Solicitados -->
        <div class="section">
            <h3>💊 Servicios Solicitados</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value">
                    <?php echo !empty($cita['servicios']) ? $cita['servicios'] : '<span class="sin-servicios">(Sin servicios asignados)</span>'; ?>
                    <?php if ($total_servicios_cita > 0): ?>
                        <span class="badge-servicios">📋 <?php echo $total_servicios_cita; ?> servicio(s)</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Total Servicios:</div>
                <div class="info-value"><strong>$<?php echo number_format($cita['total_servicios'], 2); ?></strong></div>
            </div>
        </div>

        <!-- Productos Agregados a la Cita -->
        <div class="section">
            <h3>🛒 Productos de la Cita</h3>
            <?php
            $sql_productos_lista = "SELECT dv.*, p.nombre, p.precio_venta 
                       FROM DETALLE_VENTA dv
                       JOIN PRODUCTO p ON dv.id_producto = p.id
                       JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                       WHERE vc.id_cita = ?";
            $stmt_lista = $conn->prepare($sql_productos_lista);
            $stmt_lista->bind_param("i", $id_cita);
            $stmt_lista->execute();
            $productos_cita = $stmt_lista->get_result();
            
            if ($productos_cita->num_rows > 0):
            ?>
            <table class="productos-cita-table">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($prod = $productos_cita->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prod['nombre']); ?></span>
                            <td><?php echo $prod['cantidad']; ?></span>
                            <td>$<?php echo number_format($prod['precio_unitario'], 2); ?></span>
                            <td>$<?php echo number_format($prod['subtotal'], 2); ?></span>
                            <td style="display: flex; gap: 5px; align-items: center;">
                                <!-- Botón para quitar UNA unidad -->
                                <button type="button" class="btn-quitar-uno"
                                onclick="quitarUnidadProducto(<?php echo $id_cita; ?>, <?php echo $prod['id_producto']; ?>, '<?php echo addslashes($prod['nombre']); ?>', <?php echo $prod['cantidad']; ?>)"
                                style="background: #ff9800; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                                ➖ Quitar 1
                                </button>
                                <!-- Botón para eliminar TODAS las unidades -->
                                <button type="button" class="btn-eliminar-producto"
                                onclick="eliminarProductoDeCita(<?php echo $id_cita; ?>, <?php echo $prod['id_producto']; ?>, '<?php echo addslashes($prod['nombre']); ?>')"
                                style="background: #f44336; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">
                                🗑️ Eliminar todo
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="sin-productos">No hay productos agregados a esta cita.</p>
            <?php endif; ?>
        </div>

        <!-- Agregar Servicio -->
        <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'veterinario'])): ?>
        <div class="section">
            <h3>➕ Agregar Servicio</h3>
            <form action="agregar_servicio_cita.php" method="POST">
                <input type="hidden" name="id_cita" value="<?php echo $id_cita; ?>">
                <div class="form-row">
                    <select name="id_servicio" id="select_servicio" required style="flex:2; padding:8px;">
                        <option value="">Seleccionar...</option>
                        <?php echo $servicios_options; ?>
                    </select>
                    <input type="number" step="0.01" name="precio_fijado" id="precio_fijado" required readonly style="background:#f5f5f5; width:120px; padding:8px;">
                    <button type="submit" class="btn-small" style="background:#4caf50;">+ Agregar</button>
                </div>
            </form>
        </div>
        <script>
            document.getElementById('select_servicio').addEventListener('change', function() {
                const precio = this.options[this.selectedIndex].dataset.precio;
                document.getElementById('precio_fijado').value = precio || '';
            });
        </script>
        <?php endif; ?>

        <!-- SECCIÓN DE PAGO (unificada) -->
        <div class="section">
            <h3>💰 Pago Total</h3>
            <div class="info-row">
                <div class="info-label">Servicios:</div>
                <div class="info-value">$<?php echo number_format($cita['total_servicios'], 2); ?></div>
            </div>
            <?php if ($total_productos > 0): ?>
            <div class="info-row">
                <div class="info-label">Productos:</div>
                <div class="info-value">$<?php echo number_format($total_productos, 2); ?></div>
            </div>
            <?php endif; ?>
            <div class="info-row total-row">
                <div class="info-label"><strong>TOTAL A PAGAR:</strong></div>
                <div class="info-value"><strong>$<?php echo number_format($total_general, 2); ?></strong></div>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px;">
                <?php if ($pago_existente): ?>
                    <span style="background: #4caf50; color: white; padding: 10px 15px; border-radius: 5px;">✅ Cita pagada</span>
                <?php else: ?>
                    <button onclick="abrirModalProductos(<?php echo $id_cita; ?>)" class="btn-producto">
                        🛒 Agregar Productos
                    </button>
                    <button onclick="abrirModalPago(<?php echo $id_cita; ?>, <?php echo $total_general; ?>, <?php echo $cita['total_servicios']; ?>, <?php echo $total_productos; ?>)" class="btn-pago">
                        💰 Pagar Todo
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Botones de acción -->
        <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
            <?php if (in_array($_SESSION['rol'], ['super_admin', 'admin', 'recepcionista'])): ?>
                <?php if ($cita['estado'] == 'pendiente'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=confirmada" class="btn-back" style="background:#4caf50;">✅ Confirmar Cita</a>
                <?php endif; ?>
                <?php if ($cita_cancelable && $cita['estado'] != 'cancelada' && $cita['estado'] != 'completada'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=cancelada" class="btn-back" style="background:#f44336;" onclick="return confirm('¿Cancelar esta cita?')">❌ Cancelar Cita</a>
                <?php endif; ?>
                <?php if ($cita['estado'] == 'confirmada'): ?>
                    <a href="actualizar_estado.php?id=<?php echo $id_cita; ?>&estado=completada" class="btn-back" style="background:#2196f3;">✓ Marcar Completada</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal para Registrar Pago (unificado) -->
<div id="modalPago" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header" style="background: #4caf50;">
            <h2>💰 Pagar Cita</h2>
            <span class="close-modal" onclick="cerrarModal('modalPago')">&times;</span>
        </div>
        <div class="modal-body">
            <form action="actualizar_pago_cita.php" method="POST">
                <input type="hidden" name="id_cita" id="pago_cita_id">
                
                <!-- Servicios Solicitados -->
                <div class="form-group">
                    <label>📋 Servicios Solicitados:</label>
                    <div id="lista_servicios_pago" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 5px;">
                        <?php
                        // Obtener servicios de la cita
                        $sql_servicios_pago = "SELECT s.nombre_servicio, dc.precio_fijado 
                                               FROM DETALLE_CITA dc
                                               JOIN SERVICIO s ON dc.id_servicio = s.id
                                               WHERE dc.id_cita = ?";
                        $stmt_serv = $conn->prepare($sql_servicios_pago);
                        $stmt_serv->bind_param("i", $id_cita);
                        $stmt_serv->execute();
                        $servicios_pago = $stmt_serv->get_result();
                        
                        if ($servicios_pago->num_rows > 0):
                        ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--primary); color: white;">
                                        <th style="padding: 8px; text-align: left;">Servicio</th>
                                        <th style="padding: 8px; text-align: right;">Precio</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($serv = $servicios_pago->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($serv['nombre_servicio']); ?></td>
                                        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$<?php echo number_format($serv['precio_fijado'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f9f9f9; font-weight: bold;">
                                        <td style="padding: 8px;">Total Servicios:</td>
                                        <td style="padding: 8px; text-align: right;">$<?php echo number_format($cita['total_servicios'], 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>No hay servicios solicitados</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Productos Agregados -->
                <div class="form-group" id="productos_pago_group">
                    <label>🛒 Productos Agregados:</label>
                    <div id="lista_productos_pago" style="background: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 5px;">
                        <?php
                        // Obtener productos de la cita
                        $sql_productos_pago = "SELECT p.nombre, dv.cantidad, dv.precio_unitario, dv.subtotal 
                                               FROM DETALLE_VENTA dv
                                               JOIN PRODUCTO p ON dv.id_producto = p.id
                                               JOIN VENTA_CITA vc ON vc.id_venta = dv.id_venta
                                               WHERE vc.id_cita = ?";
                        $stmt_prod_pago = $conn->prepare($sql_productos_pago);
                        $stmt_prod_pago->bind_param("i", $id_cita);
                        $stmt_prod_pago->execute();
                        $productos_pago = $stmt_prod_pago->get_result();
                        
                        if ($productos_pago->num_rows > 0):
                        ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: var(--primary); color: white;">
                                        <th style="padding: 8px; text-align: left;">Producto</th>
                                        <th style="padding: 8px; text-align: center;">Cantidad</th>
                                        <th style="padding: 8px; text-align: right;">Precio</th>
                                        <th style="padding: 8px; text-align: right;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($prod = $productos_pago->fetch_assoc()): ?>
                                    <tr>
                                        <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($prod['nombre']); ?></td>
                                        <td style="padding: 8px; text-align: center; border-bottom: 1px solid #eee;"><?php echo $prod['cantidad']; ?></td>
                                        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                                        <td style="padding: 8px; text-align: right; border-bottom: 1px solid #eee;">$<?php echo number_format($prod['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: #f9f9f9; font-weight: bold;">
                                        <td colspan="3" style="padding: 8px;">Total Productos:</td>
                                        <td style="padding: 8px; text-align: right;">$<?php echo number_format($total_productos, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>No hay productos agregados</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Total General -->
                <div class="form-group total-row">
                    <label><strong>💰 TOTAL A PAGAR:</strong></label>
                    <input type="text" id="monto_total_pago" readonly style="background:#f5f5f5; font-size: 1.2rem; font-weight: bold; color: var(--primary);">
                </div>
                
                <div class="form-group">
                    <label>Método de pago *</label>
                    <select name="metodo_pago" required>
                        <option value="">Seleccionar...</option>
                        <option value="efectivo">💵 Efectivo</option>
                        <option value="tarjeta">💳 Tarjeta</option>
                        <option value="transferencia">🏦 Transferencia</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Referencia (opcional)</label>
                    <input type="text" name="referencia" placeholder="Número de transferencia, último 4 dígitos de tarjeta">
                </div>
                
                <button type="submit" class="btn-guardar">Registrar Pago</button>
            </form>
        </div>
    </div>
</div>

    <!-- Modal para Agregar Productos al Carrito -->
    <div id="modalProductos" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header" style="background: #ff9800;">
                <h2>🛒 Agregar Productos</h2>
                <span class="close-modal" onclick="cerrarModal('modalProductos')">&times;</span>
            </div>
            <div class="modal-body">
                <!-- Buscador de productos -->
                <div class="buscador-productos" style="margin-bottom: 20px; padding: 10px; background: #f5f5f5; border-radius: 8px;">
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="text"
                                id="buscador_producto_input"
                                placeholder="🔍 Buscar por nombre o código de barras..."
                                style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px;">
                        <button type="button" id="btnBuscarProductos" class="btn-small" style="background: #2196f3; padding: 10px 20px;">Buscar</button>
                        <button type="button" id="btnLimpiarBusqueda" class="btn-small" style="background: #666; padding: 10px 20px;">Limpiar</button>
                    </div>
                    <div id="resultado_busqueda_info" style="margin-top: 8px; font-size: 12px; color: #666; display: none;"></div>
                </div>
                <!-- Lista de productos - USAR $lista_productos en lugar de while -->
                <div id="productosLista">
                    <?php echo $lista_productos; ?>
                </div>
                <div class="productos-seleccionados" id="productosSeleccionados">
                    <h4>Productos seleccionados:</h4>
                    <div id="listaProductos"></div>
                    <div class="total-recibo" id="totalProductos">Total: $0.00</div>
                </div>
                <button type="button" id="btnAgregarCita" class="btn-guardar" style="background: #ff9800; margin-top: 15px;">✅ Agregar a la cita</button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/detalle_cita.js"></script>
</body>
</html>