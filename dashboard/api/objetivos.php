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
        $query = "SELECT id_objetivo as id, nombre as name, monto_objetivo as target_amount, monto_actual as current_amount, fecha_limite as deadline, icono as emoji FROM objetivos WHERE id_usuario = :id_usuario ORDER BY fecha_limite ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":id_usuario", $id_usuario);
        $stmt->execute();
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        if(!empty($data->name) && !empty($data->target_amount) && !empty($data->deadline)) {
            if(isset($data->id) && !empty($data->id)) {
                $query = "UPDATE objetivos SET nombre=:name, monto_objetivo=:target, fecha_limite=:deadline, icono=:emoji, monto_actual=:current WHERE id_objetivo=:id AND id_usuario=:id_usuario";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":id", $data->id);
                $current = isset($data->current_amount) ? $data->current_amount : 0;
                $stmt->bindParam(":current", $current);
            } else {
                $query = "INSERT INTO objetivos SET id_usuario=:id_usuario, nombre=:name, monto_objetivo=:target, fecha_limite=:deadline, icono=:emoji";
                $stmt = $db->prepare($query);
            }
            
            $emoji = isset($data->emoji) && !empty($data->emoji) ? $data->emoji : '🎯';

            $stmt->bindParam(":id_usuario", $id_usuario);
            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":target", $data->target_amount);
            $stmt->bindParam(":deadline", $data->deadline);
            $stmt->bindParam(":emoji", $emoji);
            
            if($stmt->execute()) {
                echo json_encode(array("message" => "Objetivo guardado."));
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
            $query = "DELETE FROM objetivos WHERE id_objetivo = :id AND id_usuario = :id_usuario";
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
