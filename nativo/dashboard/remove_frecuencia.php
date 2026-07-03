<?php
require_once 'config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
try {
    $db->exec("ALTER TABLE gastos_fijos DROP COLUMN frecuencia");
    echo "Column 'frecuencia' dropped successfully.";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "check that column/key exists") !== false || strpos($e->getMessage(), "Can't DROP") !== false) {
        echo "Column 'frecuencia' does not exist or already dropped.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
