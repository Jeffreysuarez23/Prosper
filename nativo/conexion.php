<?php
// conexion.php (Wrapper for dashboard/config/database.php)
require_once __DIR__ . '/dashboard/config/database.php';

$dbClass = new Database();
$db = $dbClass->getConnection();
?>
