<?php
session_start();
session_destroy();
header('Location: login.php');  // ← Esto busca en la misma carpeta (admin/)
exit;
?>