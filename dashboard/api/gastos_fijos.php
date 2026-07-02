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
        $query = "SELECT id_gasto_fijo as id, nombre as name, monto as amount, dia_vencimiento as day_of_month, icono as emoji FROM gastos_fijos WHERE id_usuario = :id_usuario ORDER BY dia_vencimiento ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id_usuario", $id_usuario);
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->name) && !empty($data->amount) && !empty($data->day_of_month)) {
            if(isset($data->id) && !empty($data->id)) {
                $query = "UPDATE gastos_fijos SET nombre=:name, monto=:amount, dia_vencimiento=:day, icono=:emoji WHERE id_gasto_fijo=:id AND id_usuario=:id_usuario";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":id", $data->id);
            } else {
                $query = "INSERT INTO gastos_fijos SET id_usuario=:id_usuario, nombre=:name, monto=:amount, dia_vencimiento=:day, icono=:emoji";
                $stmt = $db->prepare($query);
            }
            

            $emoji = isset($data->emoji) && !empty($data->emoji) ? $data->emoji : '🏠';

            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":amount", $data->amount);
            $stmt->bindParam(":day", $data->day_of_month);

            $stmt->bindParam(":emoji", $emoji);
            
            if($stmt->execute()) {
                echo json_encode(array("message" => "Gasto guardado."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "No se pudo guardar."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Datos incompletos."));
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->id)) {
            $query = "DELETE FROM gastos_fijos WHERE id_gasto_fijo = :id AND id_usuario = :id_usuario";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $data->id);
            $stmt->bindParam(":id_usuario", $id_usuario);
            if($stmt->execute()) {
                echo json_encode(array("message" => "Eliminado."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Error."));
            }
        }
        break;
}
?>
