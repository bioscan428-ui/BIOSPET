<?php
// controllers/CitaController.php
require_once __DIR__ . '/../includes/conexion.php';

class CitaController {
    
    public function guardar() {
        global $conn;
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ../citas.php');
            exit;
        }

        // 1. Obtener y validar datos básicos del dueño
        $nombre_dueno = trim($_POST['nombre_dueno'] ?? '');
        $ape_pat = trim($_POST['ape_pat'] ?? '');
        $ape_mat = trim($_POST['ape_mat'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $fecha_cita = $_POST['fecha_cita'] ?? '';
        $hora_cita = $_POST['hora_cita'] ?? '';
        $notas = trim($_POST['notas'] ?? '');
        $origen = $_POST['origen'] ?? 'web';
        $servicios = $_POST['servicios'] ?? [];

        // Normalizar origen
        $origen = ($origen === 'web') ? 'Whatsapp' : 'Presencial';

        // Obtener mascotas del formulario
        $mascotas_nuevas = $_POST['mascotas_nuevas'] ?? [];
        $mascotas_existentes = $_POST['mascotas_existentes'] ?? [];
        $mascotas_tipo = $_POST['mascota_tipo'] ?? [];
        
        // Calcular total de mascotas a procesar
        $total_mascotas = 0;
        foreach($mascotas_tipo as $idx => $tipo) {
            if($tipo === 'nueva' && isset($mascotas_nuevas[$idx])) {
                $total_mascotas++;
            } elseif($tipo === 'existente' && isset($mascotas_existentes[$idx]['id']) && !empty($mascotas_existentes[$idx]['id'])) {
                $total_mascotas++;
            }
        }
        
        if($total_mascotas == 0) {
            die("Error: Debe registrar al menos una mascota.");
        }

        // Validaciones básicas del dueño
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Error: El email no tiene un formato válido.");
        }
        if (!preg_match('/^[0-9]{10,15}$/', $telefono)) {
            die("Error: El teléfono debe contener solo números (10-15 dígitos).");
        }
        if ($fecha_cita < date('Y-m-d')) {
            die("Error: La fecha no puede ser anterior a hoy.");
        }
        
        // Validación de disponibilidad (considerando múltiples citas)
        $sql_disponibilidad = "SELECT total_citas_dia(?) as total_citas";
        $stmt_disp = $conn->prepare($sql_disponibilidad);
        $stmt_disp->bind_param("s", $fecha_cita);
        $stmt_disp->execute();
        $result_disp = $stmt_disp->get_result();
        $row_disp = $result_disp->fetch_assoc();
        $citas_ese_dia = $row_disp['total_citas'];
        $stmt_disp->close();
        
        $limite_citas_dia = 20;
        
        if ($citas_ese_dia + $total_mascotas > $limite_citas_dia) {
            die("Error: No hay suficiente disponibilidad para {$total_mascotas} cita(s) en la fecha seleccionada. Solo quedan " . ($limite_citas_dia - $citas_ese_dia) . " espacios.");
        }
        
        // Validar hora
        $hora_valida = preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hora_cita);
        if (!$hora_valida) {
            die("Error: Formato de hora inválido.");
        }
        
        $hora_num = (int)substr($hora_cita, 0, 2);
        if ($hora_num < 8 || $hora_num > 20) {
            die("Error: El horario de atención es de 8:00 a 20:00 horas.");
        }
        
        // Validar nombre del dueño
        if (!preg_match('/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/', $nombre_dueno)) {
            die("Error: El nombre solo debe contener letras.");
        }

        if (empty($nombre_dueno) || empty($telefono) || empty($fecha_cita) || empty($hora_cita)) {
            die("Error: Campos requeridos vacíos.");
        }

        $conn->begin_transaction();

        try {
            // 1. Registrar o obtener CLIENTE
            $sql_cliente = "CALL registrar_cliente(?, ?, ?, ?, ?, ?, @id_cliente)";
            $stmt_cliente = $conn->prepare($sql_cliente);
            $stmt_cliente->bind_param("ssssss", 
                $nombre_dueno,
                $ape_pat,
                $ape_mat,
                $telefono,
                $email,
                $direccion
            );
            $stmt_cliente->execute();
            $stmt_cliente->close();
            
            $result = $conn->query("SELECT @id_cliente as id_cliente");
            $id_cliente = $result->fetch_assoc()['id_cliente'];
            $conn->next_result(); // Limpiar resultados pendientes
            
            $ids_citas = [];
            $mascotas_registradas = [];
            
            // 2. Procesar cada mascota y crear su cita
            foreach($mascotas_tipo as $idx => $tipo) {
                $id_mascota = null;
                $nombre_mascota_actual = '';
                
                if($tipo === 'nueva' && isset($mascotas_nuevas[$idx])) {
                    // ========== MASCOTA NUEVA ==========
                    $mascota = $mascotas_nuevas[$idx];
                    $nombre_mascota_actual = trim($mascota['nombre'] ?? '');
                    $especie = $mascota['especie'] ?? 'Canino';
                    $raza = trim($mascota['raza'] ?? '');
                    $genero = $mascota['genero'] ?? null;
                    
                    // Validar nombre de mascota
                    if(empty($nombre_mascota_actual)) {
                        throw new Exception("El nombre de la mascota #" . ($idx + 1) . " es requerido");
                    }
                    if (!preg_match('/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s]+$/', $nombre_mascota_actual)) {
                        throw new Exception("El nombre de la mascota solo debe contener letras");
                    }
                    
                    // Procesar foto
                    $foto_ruta = null;
                    if (isset($_FILES['mascotas_fotos']['tmp_name'][$idx]) && $_FILES['mascotas_fotos']['error'][$idx] === UPLOAD_ERR_OK) {
                        $archivo = $_FILES['mascotas_fotos']['tmp_name'][$idx];
                        $nombre_archivo_orig = $_FILES['mascotas_fotos']['name'][$idx];
                        $extension = strtolower(pathinfo($nombre_archivo_orig, PATHINFO_EXTENSION));
                        $extensiones_validas = ['jpg', 'jpeg', 'png'];
                        
                        if (in_array($extension, $extensiones_validas)) {
                            $nombre_archivo = 'mascota_' . time() . '_' . $idx . '_' . rand(1000, 9999) . '.' . $extension;
                            $ruta_destino = __DIR__ . '/../assets/images/mascotas/' . $nombre_archivo;
                            
                            if (!file_exists(__DIR__ . '/../assets/images/mascotas/')) {
                                mkdir(__DIR__ . '/../assets/images/mascotas/', 0777, true);
                            }
                            
                            if (move_uploaded_file($archivo, $ruta_destino)) {
                                $foto_ruta = 'assets/images/mascotas/' . $nombre_archivo;
                            }
                        }
                    }
                    
                    // Insertar mascota
                    $sql_mascota = "INSERT INTO MASCOTA (id_cliente, nombre_mascota, especie, raza, genero, foto) 
                                    VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt_mascota = $conn->prepare($sql_mascota);
                    $stmt_mascota->bind_param("isssss", $id_cliente, $nombre_mascota_actual, $especie, $raza, $genero, $foto_ruta);
                    $stmt_mascota->execute();
                    $id_mascota = $conn->insert_id;
                    $stmt_mascota->close();
                    
                } elseif($tipo === 'existente' && isset($mascotas_existentes[$idx]['id']) && !empty($mascotas_existentes[$idx]['id'])) {
                    // ========== MASCOTA EXISTENTE ==========
                    $id_mascota = (int)$mascotas_existentes[$idx]['id'];
                    
                    // Obtener nombre de la mascota para el mensaje
                    $sql_nombre = "SELECT nombre_mascota FROM MASCOTA WHERE id = ?";
                    $stmt_nombre = $conn->prepare($sql_nombre);
                    $stmt_nombre->bind_param("i", $id_mascota);
                    $stmt_nombre->execute();
                    $result_nombre = $stmt_nombre->get_result();
                    $nombre_mascota_actual = $result_nombre->fetch_assoc()['nombre_mascota'];
                    $stmt_nombre->close();
                }
                
                if($id_mascota) {
                    // Crear CITA para esta mascota
                    $sql_cita = "INSERT INTO CITA (fecha_cita, hora_cita, id_mascota, notas, origen, estado) 
                                VALUES (?, ?, ?, ?, ?, 'pendiente')";
                    $stmt_cita = $conn->prepare($sql_cita);
                    $stmt_cita->bind_param("ssiss", $fecha_cita, $hora_cita, $id_mascota, $notas, $origen);
                    $stmt_cita->execute();
                    $id_cita = $conn->insert_id;
                    $ids_citas[] = $id_cita;
                    $stmt_cita->close();
                    
                    $mascotas_registradas[] = [
                        'id' => $id_mascota,
                        'nombre' => $nombre_mascota_actual,
                        'id_cita' => $id_cita
                    ];
                }
            }
            
            // 3. Agregar los mismos servicios a TODAS las citas creadas
            if (!empty($servicios) && !empty($ids_citas)) {
                foreach($ids_citas as $id_cita) {
                    foreach($servicios as $id_servicio) {
                        // Obtener precio actual del servicio
                        $sql_precio = "SELECT precio FROM SERVICIO WHERE id = ?";
                        $stmt_precio = $conn->prepare($sql_precio);
                        $stmt_precio->bind_param("i", $id_servicio);
                        $stmt_precio->execute();
                        $result_precio = $stmt_precio->get_result();
                        $servicio_data = $result_precio->fetch_assoc();
                        $precio = $servicio_data['precio'];
                        $stmt_precio->close();
                        
                        // Insertar en DETALLE_CITA
                        $sql_detalle = "INSERT INTO DETALLE_CITA (id_cita, id_servicio, precio_fijado) VALUES (?, ?, ?)";
                        $stmt_detalle = $conn->prepare($sql_detalle);
                        $stmt_detalle->bind_param("iid", $id_cita, $id_servicio, $precio);
                        $stmt_detalle->execute();
                        $stmt_detalle->close();
                    }
                }
            }
            
            $conn->commit();
            
            // 4. Preparar mensaje de éxito con todas las mascotas
            $lista_mascotas = [];
            foreach($mascotas_registradas as $m) {
                $lista_mascotas[] = $m['nombre'];
            }
            $mensaje_mascotas = implode(', ', $lista_mascotas);
            $total_citas = count($ids_citas);
            
            $_SESSION['notificacion'] = [
                'tipo' => 'success',
                'titulo' => $total_citas > 1 ? '¡Citas Agendadas!' : '¡Cita Agendada!',
                'mensaje' => $total_citas > 1 
                    ? "Se han agendado {$total_citas} citas para las mascotas: {$mensaje_mascotas}. Fecha: " . date('d/m/Y', strtotime($fecha_cita)) . " a las {$hora_cita}. Te contactaremos para confirmar."
                    : "Tu cita para {$mensaje_mascotas} el " . date('d/m/Y', strtotime($fecha_cita)) . " a las {$hora_cita} ha sido registrada. Te contactaremos para confirmar."
            ];
            
            // Guardar última cita (la primera) para QR
            if(!empty($ids_citas)) {
                $primera_cita = $ids_citas[0];
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'];
                $url_factura = $protocol . $host . "/biospet.bioscan.services/facturar_cita.php?id=" . $primera_cita;
                
                $_SESSION['ultima_cita'] = [
                    'id' => $primera_cita,
                    'fecha' => $fecha_cita,
                    'hora' => $hora_cita,
                    'mascota' => $lista_mascotas[0],
                    'dueno' => $nombre_dueno . ' ' . $ape_pat . ' ' . $ape_mat,
                    'qr_url' => $url_factura,
                    'total_citas' => $total_citas
                ];
            }
            
            header('Location: ../gracias.php');
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            die("Error al guardar la(s) cita(s): " . $e->getMessage());
        }
    }
}

$controller = new CitaController();
$controller->guardar();
?>