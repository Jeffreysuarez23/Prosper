<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

session_start();
$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if (!$id_usuario) {
    header("Location: ../../login.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = $_POST['id'] ?? null;
        $type = $_POST['type'] ?? '';
        $amount = $_POST['amount'] ?? '';
        // Limpiar formato COP (1.500.000,50 -> 1500000.50)
        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '.', $amount);
        $date = $_POST['date'] ?? '';
        $category = $_POST['category'] ?? '';
        $description = $_POST['description'] ?? '';
        
        $payment_method = $_POST['payment_method'] ?? '';
        if ($payment_method === 'otro') {
            $payment_method = $_POST['payment_method_other'] ?? '';
        }

        if (!empty($type) && !empty($amount) && !empty($date) && !empty($category)) {
            if (!empty($id)) {
                // Editar
                $query = "UPDATE movimientos SET tipo=:tipo, monto=:monto, fecha=:fecha, categoria=:categoria, descripcion=:descripcion, metodo_pago=:metodo_pago WHERE id_movimiento=:id AND id_usuario=:id_usuario";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":id", $id);
                $msg = 'edited';
            } else {
                // Crear
                $query = "INSERT INTO movimientos SET id_usuario=:id_usuario, tipo=:tipo, monto=:monto, fecha=:fecha, categoria=:categoria, descripcion=:descripcion, metodo_pago=:metodo_pago";
                $stmt = $db->prepare($query);
                $msg = 'created';
            }
            
            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":tipo", $type);
            $stmt->bindParam(":monto", $amount);
            $stmt->bindParam(":fecha", $date);
            $stmt->bindParam(":categoria", $category);
            $stmt->bindParam(":descripcion", $description);
            $stmt->bindParam(":metodo_pago", $payment_method);
            
            $stmt->execute();
        }
    } else if ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if (!empty($id)) {
            $query = "DELETE FROM movimientos WHERE id_movimiento = :id AND id_usuario = :id_usuario";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $id);
            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->execute();
            $msg = 'deleted';
        }
    }
}

header("Location: ../index.php?mod=movimientos" . ($msg ? "&msg=$msg" : ""));
exit;
?>
