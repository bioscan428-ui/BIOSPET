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
        
        // CAMBIO: Ahora recibimos un array de servicios desde los checkboxes
        $servicios_seleccionados = $_POST['servicios'] ?? []; 
        
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $hora_cita = $_POST['hora_cita'] ?? '';
        $notas = trim($_POST['notas'] ?? '');

        //Validaciones de Formato
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

        // Validaciones básicas (Verificamos que haya al menos un servicio)
        if (empty($nombre_dueno) || empty($telefono) || empty($nombre_mascota) || empty($fecha_cita) || empty($hora_cita) || empty($servicios_seleccionados)) {
            die("Error: Campos requeridos vacíos o no seleccionó ningún servicio.");
        }

        global $conn;
        $conn->begin_transaction();

        try {
            // 2. Insertar en CLIENTE (Igual)
            $sql_cliente = "INSERT INTO CLIENTE (nombre, ape_pat, ape_mat, telefono, email) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_cliente);
            $stmt->bind_param("sssss", $nombre_dueno, $ape_pat, $ape_mat, $telefono, $email);
            $stmt->execute();
            $id_cliente = $conn->insert_id;

            // 3. Insertar en MASCOTA (Igual)
            $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, fecha_nacimiento) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_mascota);
            $stmt->bind_param("issss", $id_cliente, $nombre_mascota, $especie, $raza, $fecha_nac);
            $stmt->execute();
            $id_mascota = $conn->insert_id;

            // 4. Insertar en CITA (CAMBIO: Ya no guardamos id_servicio ni precio aquí)
            $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, notas) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql_cita);
            $stmt->bind_param("ssis", $fecha_cita, $hora_cita, $id_mascota, $notas);
            $stmt->execute();
            $id_cita = $conn->insert_id;

            // 5. NUEVO: Insertar cada servicio en DETALLE_CITA
            $sql_detalle = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) VALUES (?, ?, ?)";
            $stmt_det = $conn->prepare($sql_detalle);

            // Validar que los servicios existen y están activos
            if (empty($servicios_seleccionados)) {
                throw new Exception("No se seleccionaron servicios.");
            }

            // Verificar que todos los servicios existen
            $placeholders = implode(',', array_fill(0, count($servicios_seleccionados), '?'));
            $sql_validar = "SELECT id FROM SERVICIO WHERE id IN ($placeholders) AND activo = 1";
            $stmt_validar = $conn->prepare($sql_validar);
            $stmt_validar->bind_param(str_repeat('i', count($servicios_seleccionados)), ...$servicios_seleccionados);
            $stmt_validar->execute();
            $result_validar = $stmt_validar->get_result();

            $ids_validos = [];
            while ($row = $result_validar->fetch_assoc()) {
                $ids_validos[] = $row['id'];
            }

            if (count($ids_validos) != count($servicios_seleccionados)) {
                throw new Exception("Uno o más servicios seleccionados no son válidos.");
            }

            foreach ($servicios_seleccionados as $id_servicio) {
                // Buscamos el precio actual del servicio para "congelarlo" en el detalle
                $sql_p = "SELECT precio FROM SERVICIO WHERE id = ?";
                $stmt_p = $conn->prepare($sql_p);
                $stmt_p->bind_param("i", $id_servicio);
                $stmt_p->execute();
                $res_p = $stmt_p->get_result();
                $row_p = $res_p->fetch_assoc();
                $precio_fijado = $row_p['precio'];

                // Insertamos en la tabla intermedia
                $stmt_det->bind_param("iid", $id_cita, $id_servicio, $precio_fijado);
                $stmt_det->execute();
            }

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