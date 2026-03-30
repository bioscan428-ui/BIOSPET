<?php
// controllers/CitaController.php
require_once __DIR__ . '/../includes/conexion.php';

class CitaController {
    
    public function guardar() {
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
        
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $hora_cita = $_POST['hora_cita'] ?? '';
        $notas = trim($_POST['notas'] ?? '');

        // Validaciones de Formato
        // Validar email (si se envió)
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Error: El email no tiene un formato válido.");
        }
        // Validar teléfono (solo números, 10-15 dígitos)
        if (!preg_match('/^[0-9]{10,15}$/', $telefono)) {
            die("Error: El teléfono debe contener solo números (10-15 dígitos).");
        }
        // Validar que la fecha no sea pasada
        if ($fecha_cita < date('Y-m-d')) {
            die("Error: La fecha no puede ser anterior a hoy.");
        }
        // Validar que la hora sea razonable (entre 8:00 y 20:00)
        $hora_valida = preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hora_cita);
        if (!$hora_valida) {
            die("Error: Formato de hora inválido.");
        }
        // Validar nombre del dueño (solo letras y espacios)
        if (!preg_match('/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/', $nombre_dueno)) {
            die("Error: El nombre solo debe contener letras.");
        }
        // Validar nombre de mascota
        if (!preg_match('/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/', $nombre_mascota)) {
            die("Error: El nombre de la mascota solo debe contener letras.");
        }

        // Validaciones básicas (ya NO validamos servicios)
        if (empty($nombre_dueno) || empty($telefono) || empty($nombre_mascota) || empty($fecha_cita) || empty($hora_cita)) {
            die("Error: Campos requeridos vacíos.");
        }

        global $conn;
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
            $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, fecha_nacimiento) 
                            VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_mascota);
            $stmt->bind_param("issss", $id_cliente, $nombre_mascota, $especie, $raza, $fecha_nac);
            $stmt->execute();
            $id_mascota = $conn->insert_id;

            // 4. Insertar en CITA (SIN servicios, estado = 'pendiente')
            $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, notas, estado) 
                         VALUES (?, ?, ?, ?, 'pendiente')";
            $stmt = $conn->prepare($sql_cita);
            $stmt->bind_param("ssis", $fecha_cita, $hora_cita, $id_mascota, $notas);
            $stmt->execute();
            $id_cita = $conn->insert_id;

            // Confirmar transacción
            $conn->commit();
            
            // Redirigir a éxito
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