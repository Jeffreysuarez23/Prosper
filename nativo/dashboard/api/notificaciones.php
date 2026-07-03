<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
file_put_contents(__DIR__ . '/debug_hit.txt', 'API HIT at ' . date('Y-m-d H:i:s'));
$dbClass = new Database();
$db = $dbClass->getConnection();
$id_usuario = (int)$_SESSION['id_usuario'];

$action = $_GET['action'] ?? 'get';

try {
    if ($action === 'get') {
        // Fetch only the latest 5 notifications
        $stmt = $db->prepare("SELECT * FROM notificaciones WHERE id_usuario = :id ORDER BY leida ASC, fecha_creacion DESC LIMIT 5");
        $stmt->execute([':id' => $id_usuario]);
        $notificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmtCount = $db->prepare("SELECT COUNT(*) as unread_count FROM notificaciones WHERE id_usuario = :id AND leida = 0");
        $stmtCount->execute([':id' => $id_usuario]);
        $unreadCount = (int)$stmtCount->fetch(PDO::FETCH_ASSOC)['unread_count'];

        $json = json_encode(['success' => true, 'data' => $notificaciones, 'unread_count' => $unreadCount], JSON_INVALID_UTF8_SUBSTITUTE);
        file_put_contents(__DIR__ . '/debug.txt', $json);
        echo $json;
    } 
    elseif ($action === 'mark_read') {
        $id_notificacion = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id_notificacion > 0) {
            $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id_notificacion = :id_notif AND id_usuario = :id_usuario");
            $stmt->execute([':id_notif' => $id_notificacion, ':id_usuario' => $id_usuario]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
        }
    }
    elseif ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id_usuario = :id_usuario AND leida = 0");
        $stmt->execute([':id_usuario' => $id_usuario]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete') {
        $id_notificacion = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id_notificacion > 0) {
            $stmt = $db->prepare("DELETE FROM notificaciones WHERE id_notificacion = :id_notif AND id_usuario = :id_usuario");
            $stmt->execute([':id_notif' => $id_notificacion, ':id_usuario' => $id_usuario]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
        }
    }
    elseif ($action === 'delete_all') {
        $stmt = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario");
        $stmt->execute([':id_usuario' => $id_usuario]);
        echo json_encode(['success' => true]);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
