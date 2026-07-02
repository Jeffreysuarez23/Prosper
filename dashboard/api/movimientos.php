<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
session_start();
$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if (!$id_usuario) {
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

switch ($method) {
    case 'GET':
        $query = "SELECT id_movimiento as id, tipo as type, monto as amount, fecha as date, categoria as category, descripcion as description, metodo_pago as payment_method FROM movimientos WHERE id_usuario = :id_usuario ORDER BY fecha DESC, id_movimiento DESC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id_usuario", $id_usuario);
        $stmt->execute();
        $result = $stmt->fetchAll();
        echo json_encode($result);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->type) && !empty($data->amount) && !empty($data->date) && !empty($data->category)) {
            if(isset($data->id) && !empty($data->id)) {
                // Editar
                $query = "UPDATE movimientos SET tipo=:tipo, monto=:monto, fecha=:fecha, categoria=:categoria, descripcion=:descripcion, metodo_pago=:metodo_pago WHERE id_movimiento=:id AND id_usuario=:id_usuario";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":id", $data->id);
            } else {
                // Crear
                $query = "INSERT INTO movimientos SET id_usuario=:id_usuario, tipo=:tipo, monto=:monto, fecha=:fecha, categoria=:categoria, descripcion=:descripcion, metodo_pago=:metodo_pago";
                $stmt = $db->prepare($query);
            }
            
            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":tipo", $data->type);
            $stmt->bindParam(":monto", $data->amount);
            $stmt->bindParam(":fecha", $data->date);
            $stmt->bindParam(":categoria", $data->category);
            $stmt->bindParam(":descripcion", $data->description);
            $stmt->bindParam(":metodo_pago", $data->payment_method);
            
            if($stmt->execute()) {
                echo json_encode(array("message" => "Movimiento guardado."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "No se pudo guardar el movimiento."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Datos incompletos."));
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->id)) {
            $query = "DELETE FROM movimientos WHERE id_movimiento = :id AND id_usuario = :id_usuario";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $data->id);
            $stmt->bindParam(":id_usuario", $id_usuario);
            
            if($stmt->execute()) {
                echo json_encode(array("message" => "Movimiento eliminado."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "No se pudo eliminar el movimiento."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID no proporcionado."));
        }
        break;
}
?>
