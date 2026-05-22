<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    exit('No autorizado');
}

require_once __DIR__ . '/../includes/conexion.php';

$id_empleado = (int)($_GET['id'] ?? 0);
if (!$id_empleado) {
    exit('Empleado no encontrado');
}

// Obtener documentos del empleado
$sql = "SELECT * FROM EXPEDIENTE_EMPLEADO 
        WHERE id_empleado = ? AND activo = 1 
        ORDER BY fecha_subida DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_empleado);
$stmt->execute();
$documentos = $stmt->get_result();

$tipos = [
    'contrato' => '📄 Contrato',
    'identificacion' => '🆔 Identificación',
    'comprobante_domicilio' => '🏠 Comprobante de domicilio',
    'cedula_profesional' => '🎓 Cédula profesional',
    'certificado_estudios' => '📜 Certificado de estudios',
    'carta_recomendacion' => '✉️ Carta de recomendación',
    'constancia_salud' => '🏥 Constancia de salud',
    'otro' => '📎 Otro'
];
?>
<div class="documentos-lista">
    <?php if ($documentos->num_rows > 0): ?>
        <?php while($doc = $documentos->fetch_assoc()): ?>
        <div class="documento-item">
            <div class="documento-info">
                <div class="documento-nombre">
                    <?php echo $tipos[$doc['tipo_documento']] ?? $doc['tipo_documento']; ?>
                </div>
                <div class="documento-nombre" style="font-size: 14px;">
                    📄 <?php echo htmlspecialchars($doc['nombre_archivo']); ?>
                </div>
                <?php if($doc['descripcion']): ?>
                <div class="documento-tipo"><?php echo htmlspecialchars($doc['descripcion']); ?></div>
                <?php endif; ?>
                <div class="documento-fecha">Subido: <?php echo date('d/m/Y H:i', strtotime($doc['fecha_subida'])); ?></div>
            </div>
            <div>
                <a href="<?php echo $doc['ruta_archivo']; ?>" target="_blank" class="btn-ver-documento">👁️ Ver</a>
                <button onclick="eliminarDocumento(<?php echo $doc['id']; ?>)" class="btn-eliminar-documento">🗑️ Eliminar</button>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p style="text-align: center; color: #999;">No hay documentos subidos</p>
    <?php endif; ?>
    
    <div class="subir-documento">
        <h4>📤 Subir nuevo documento</h4>
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
            <div style="flex: 2;">
                <label>Tipo de documento</label>
                <select id="tipo_documento" style="width: 100%;">
                    <?php foreach($tipos as $valor => $nombre): ?>
                        <option value="<?php echo $valor; ?>"><?php echo $nombre; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 2;">
                <label>Descripción (opcional)</label>
                <input type="text" id="descripcion_documento" placeholder="Ej: Contrato 2024" style="width: 100%;">
            </div>
            <div style="flex: 2;">
                <label>Archivo (PDF, JPG, PNG)</label>
                <input type="file" id="archivo_documento" accept=".pdf,.jpg,.jpeg,.png" style="width: 100%;">
            </div>
            <div>
                <button onclick="subirDocumento()" class="btn-subir">📤 Subir</button>
            </div>
        </div>
    </div>
</div>