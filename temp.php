<?php
session_start();
$_SESSION['id_usuario'] = 4;
header("Location: dashboard/index.php");
exit;
