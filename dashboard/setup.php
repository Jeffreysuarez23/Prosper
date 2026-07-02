<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents(__DIR__ . '/db/database.sql');
    
    // Ejecutar múltiples sentencias
    $pdo->exec($sql);
    
    echo "Base de datos y tablas creadas con exito.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
