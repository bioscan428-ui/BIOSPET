<?php
// admin/verificar_login.php
session_start();
require_once '../includes/conexion.php'; // Sube un nivel para llegar a includes

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($usuario) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
        exit;
    }
    
    $sql = "SELECT u.*, e.nombre, e.ape_pat, e.puesto 
            FROM USUARIO u
            JOIN EMPLEADO e ON u.id_empleado = e.id
            WHERE u.nombre_usuario = ? AND u.activo = 1 AND e.activo = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['contrasena'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['empleado_id'] = $user['id_empleado'];
            $_SESSION['usuario_nombre'] = $user['nombre'] . ' ' . $user['ape_pat'];
            $_SESSION['usuario_rol'] = $user['rol'];
            $_SESSION['usuario'] = $user['nombre_usuario'];
            $_SESSION['logueado'] = true;
            
            // Actualizar último acceso
            $update = "UPDATE USUARIO SET ultimo_acceso = NOW() WHERE id = ?";
            $stmt_up = $conn->prepare($update);
            $stmt_up->bind_param("i", $user['id']);
            $stmt_up->execute();
            
            // Determinar redirección según rol
            $redirect = 'admin/dashboard.php';
            switch ($user['rol']) {
                case 'super_admin':
                case 'admin':
                    $redirect = 'admin/dashboard.php';
                    break;
                case 'veterinario':
                    $redirect = 'admin/veterinario_dashboard.php';
                    break;
                case 'asistente':
                    $redirect = 'admin/asistente_dashboard.php';
                    break;
                case 'recepcionista':
                    $redirect = 'admin/recepcionista_dashboard.php';
                    break;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'nombre' => $user['nombre'] . ' ' . $user['ape_pat'],
                'rol' => $user['rol'],
                'redirect' => $redirect
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado o inactivo']);
    }
    
    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>