<?php
session_id('test');
session_start();
$_SESSION['id_usuario'] = 7;
$_GET['action'] = 'get';
include 'dashboard/api/notificaciones.php';
?>
