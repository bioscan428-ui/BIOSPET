<?php
// controllers/CitaController.php
require_once __DIR__ . '/../includes/conexion.php';

class CitaController {
    
    public function guardar() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ../public/citas.php');
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
        $id_servicio = (int)($_POST['id_servicio'] ?? 0);
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $hora_cita = $_POST['hora_cita'] ?? '';
        $notas = trim($_POST['notas'] ?? '');

        // Validaciones básicas
        if (empty($nombre_dueno) || empty($telefono) || empty($nombre_mascota) || empty($fecha_cita) || empty($hora_cita)) {
            die("Error: Campos requeridos vacíos");
        }

        global $conn;
        
        // Iniciar transacción
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
            $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie) 
                           VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql_mascota);
            $stmt->bind_param("iss", $id_cliente, $nombre_mascota, $especie);
            $stmt->execute();
            $id_mascota = $conn->insert_id;

            // 4. Obtener precio del servicio
            $sql_precio = "SELECT precio FROM SERVICIO WHERE id = ?";
            $stmt = $conn->prepare($sql_precio);
            $stmt->bind_param("i", $id_servicio);
            $stmt->execute();
            $result = $stmt->get_result();
            $servicio = $result->fetch_assoc();
            $precio = $servicio['precio'];

            // 5. Insertar en CITA
            $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, id_servicio, monto_cobrado, notas) 
                        VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_cita);
            $stmt->bind_param("ssiids", $fecha_cita, $hora_cita, $id_mascota, $id_servicio, $precio, $notas);
            $stmt->execute();

            // Confirmar transacción
            $conn->commit();

            // Redirigir a éxito
            header('Location: ../public/gracias.php');
            exit;

        } catch (Exception $e) {
            // Revertir cambios si hay error
            $conn->rollback();
            die("Error al guardar la cita: " . $e->getMessage());
        }
    }
}

// Ejecutar el controlador
$controller = new CitaController();
$controller->guardar();
?>