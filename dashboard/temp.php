<?php
require 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query('SELECT id_movimiento as id, tipo as type, monto as amount, fecha as date, categoria as category, descripcion as description, metodo_pago as payment_method FROM movimientos');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
