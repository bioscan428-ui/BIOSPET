<?php
// controllers/CitaController.php
require_once __DIR__ . '/../includes/conexion.php';

class CitaController {
    
    public function guardar() {
        global $conn;  // ← IMPORTANTE: traer $conn al ámbito del método
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ../citas.php');
            exit;
        }

        // 1. Obtener y validar datos
        $nombre_dueno = trim($_POST['nombre_dueno'] ?? '');
        $ape_pat = trim($_POST['ape_pat'] ?? '');
        $ape_mat = trim($_POST['ape_mat'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nombre_mascota = trim($_POST['nombre_mascota'] ?? '');
        $especie = $_POST['especie'] ?? '';
        $raza = trim($_POST['raza'] ?? '');
        $fecha_nac = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
        $genero = $_POST['genero'] ?? null;
        
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $hora_cita = $_POST['hora_cita'] ?? '';
        $notas = trim($_POST['notas'] ?? '');

        // Validaciones de Formato
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Error: El email no tiene un formato válido.");
        }
        if (!preg_match('/^[0-9]{10,15}$/', $telefono)) {
            die("Error: El teléfono debe contener solo números (10-15 dígitos).");
        }
        if ($fecha_cita < date('Y-m-d')) {
            die("Error: La fecha no puede ser anterior a hoy.");
        }
        
        // ========== VALIDACIÓN DE DISPONIBILIDAD ==========
        // Usar la función total_citas_dia()
        $sql_disponibilidad = "SELECT total_citas_dia(?) as total_citas";
        $stmt_disp = $conn->prepare($sql_disponibilidad);
        $stmt_disp->bind_param("s", $fecha_cita);
        $stmt_disp->execute();
        $result_disp = $stmt_disp->get_result();
        $row_disp = $result_disp->fetch_assoc();
        $citas_ese_dia = $row_disp['total_citas'];
        
        $limite_citas_dia = 20;
        
        if ($citas_ese_dia >= $limite_citas_dia) {
            die("Error: No hay disponibilidad para la fecha seleccionada. Por favor, elige otro día.");
        }
        // ========== FIN VALIDACIÓN ==========
        
        // Validar hora
        $hora_valida = preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hora_cita);
        if (!$hora_valida) {
            die("Error: Formato de hora inválido.");
        }
        
        $hora_num = (int)substr($hora_cita, 0, 2);
        if ($hora_num < 8 || $hora_num > 20) {
            die("Error: El horario de atención es de 8:00 a 20:00 horas.");
        }
        
        if (!preg_match('/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/', $nombre_dueno)) {
            die("Error: El nombre solo debe contener letras.");
        }
        if (!preg_match('/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/', $nombre_mascota)) {
            die("Error: El nombre de la mascota solo debe contener letras.");
        }

        if (empty($nombre_dueno) || empty($telefono) || empty($nombre_mascota) || empty($fecha_cita) || empty($hora_cita)) {
            die("Error: Campos requeridos vacíos.");
        }

        $conn->begin_transaction();

        try {
            // 2. Insertar en CLIENTE
            $sql_cliente = "INSERT INTO CLIENTE (nombre, ape_pat, ape_mat, telefono, email) 
                            VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_cliente);
            $stmt->bind_param("sssss", $nombre_dueno, $ape_pat, $ape_mat, $telefono, $email);
            $stmt->execute();
            $id_cliente = $conn->insert_id;

            // 3. Insertar en MASCOTA
            $foto_ruta = null;
            if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $archivo = $_FILES['foto'];
                $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
                $extensiones_validas = ['jpg', 'jpeg', 'png'];
                
                if (in_array($extension, $extensiones_validas)) {
                    $nombre_archivo = 'mascota_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                    $ruta_destino = __DIR__ . '/../assets/images/mascotas/' . $nombre_archivo;
                    
                    if (!file_exists(__DIR__ . '/../assets/images/mascotas/')) {
                        mkdir(__DIR__ . '/../assets/images/mascotas/', 0777, true);
                    }
                    
                    if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
                        $foto_ruta = 'assets/images/mascotas/' . $nombre_archivo;
                    }
                }
            }
            
            $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, fecha_nacimiento, genero, foto) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_mascota);
            $stmt->bind_param("issssss", $id_cliente, $nombre_mascota, $especie, $raza, $fecha_nac, $genero, $foto_ruta);
            $stmt->execute();
            $id_mascota = $conn->insert_id;

            // 4. Insertar en CITA
            $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, notas, estado) 
                        VALUES (?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql_cita);
            $stmt->bind_param("ssis", $fecha_cita, $hora_cita, $id_mascota, $notas);
            $stmt->execute();
            $id_cita = $conn->insert_id;

            $conn->commit();
            
            header('Location: ../gracias.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            die("Error al guardar la cita: " . $e->getMessage());
        }
    }
}

$controller = new CitaController();
$controller->guardar();
?>