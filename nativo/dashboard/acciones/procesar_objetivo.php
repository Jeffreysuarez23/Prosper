<?php
require_once '../config/database.php';

$dbClass = new Database();
$db = $dbClass->getConnection();
session_start();
$id_usuario = (int)($_SESSION['id_usuario'] ?? 0);
if (!$id_usuario) {
    header("Location: ../../login.php");
    exit;
}

$action = $_POST['action'] ?? '';
$id_objetivo = $_POST['id_objetivo'] ?? '';

if ($action === 'save') {
    $nombre = $_POST['nombre'] ?? '';
    // Limpiamos el formato de moneda si viene con puntos o comas
    $monto_objetivo = str_replace(['.', ','], ['', '.'], $_POST['monto_objetivo'] ?? '0');
    $fecha_limite = $_POST['fecha_limite'] ?? date('Y-m-d');
    $icono = $_POST['icono'] ?? '🎯';
    $monto_actual_post = $_POST['monto_actual'] ?? '';
    $monto_actual = $monto_actual_post === '' ? 0 : str_replace(['.', ','], ['', '.'], $monto_actual_post);
    if ($monto_actual > $monto_objetivo) $monto_actual = $monto_objetivo;
    
    if (empty($id_objetivo)) {
        // Insert
        $query = "INSERT INTO objetivos (id_usuario, nombre, monto_objetivo, monto_actual, fecha_limite, icono) VALUES (:id_usuario, :nombre, :monto_objetivo, :monto_actual, :fecha_limite, :icono)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':nombre' => $nombre,
            ':monto_objetivo' => $monto_objetivo,
            ':monto_actual' => $monto_actual,
            ':fecha_limite' => $fecha_limite,
            ':icono' => $icono
        ]);
        
        if ($monto_actual > 0) {
            $desc = "Abono inicial a meta: " . $nombre;
            $mov_query = "INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago) VALUES (:id_usuario, 'gasto', :monto, CURDATE(), 'Ahorro', :desc, 'Abono')";
            $stmt_mov = $db->prepare($mov_query);
            $stmt_mov->execute([':id_usuario' => $id_usuario, ':monto' => $monto_actual, ':desc' => $desc]);
        }
    } else {
        // Update
        // Fetch old amount first to calculate the difference
        $stmt_old = $db->prepare("SELECT monto_actual FROM objetivos WHERE id_objetivo = :id_objetivo AND id_usuario = :id_usuario");
        $stmt_old->execute([':id_objetivo' => $id_objetivo, ':id_usuario' => $id_usuario]);
        $old_obj = $stmt_old->fetch(PDO::FETCH_ASSOC);
        $monto_actual_old = $old_obj ? (float)$old_obj['monto_actual'] : 0;
        
        $query = "UPDATE objetivos SET nombre = :nombre, monto_objetivo = :monto_objetivo, monto_actual = :monto_actual, fecha_limite = :fecha_limite, icono = :icono WHERE id_objetivo = :id_objetivo AND id_usuario = :id_usuario";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre,
            ':monto_objetivo' => $monto_objetivo,
            ':monto_actual' => $monto_actual,
            ':fecha_limite' => $fecha_limite,
            ':icono' => $icono,
            ':id_objetivo' => $id_objetivo,
            ':id_usuario' => $id_usuario
        ]);
        
        // Sync balance
        $diff = $monto_actual - $monto_actual_old;
        if ($diff != 0) {
            $tipo_mov = $diff > 0 ? 'gasto' : 'ingreso';
            $desc = ($diff > 0 ? "Abono a objetivo: " : "Retiro de objetivo: ") . $nombre;
            $mov_query = "INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago) VALUES (:id_usuario, :tipo, :monto, CURDATE(), 'Ahorro', :desc, 'Abono')";
            $stmt_mov = $db->prepare($mov_query);
            $stmt_mov->execute([
                ':id_usuario' => $id_usuario,
                ':tipo' => $tipo_mov,
                ':monto' => abs($diff),
                ':desc' => $desc
            ]);
        }
        
        // Si el objetivo ya no está completado, eliminamos la notificación de éxito
        if ($monto_actual < $monto_objetivo) {
            $stmt_del_notif = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario AND categoria = 'objetivo_logrado' AND accion_url LIKE :url");
            $stmt_del_notif->execute([
                ':id_usuario' => $id_usuario,
                ':url' => "%id={$id_objetivo}%"
            ]);
        } else {
            // Si el objetivo se completó, eliminamos las notificaciones de vencimiento
            $stmt_del_notif = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario AND categoria = 'objetivo_vencer' AND accion_url LIKE :url");
            $stmt_del_notif->execute([
                ':id_usuario' => $id_usuario,
                ':url' => "%id={$id_objetivo}%"
            ]);
        }
    }
} else if ($action === 'deposit') {
    $monto_deposit = str_replace(['.', ','], ['', '.'], $_POST['monto_deposit'] ?? '0');
    if (!empty($id_objetivo) && is_numeric($monto_deposit) && $monto_deposit > 0) {
        
        // Fetch current objective state
        $stmt_get = $db->prepare("SELECT monto_actual, monto_objetivo, nombre FROM objetivos WHERE id_objetivo = :id_objetivo AND id_usuario = :id_usuario");
        $stmt_get->execute([
            ':id_objetivo' => $id_objetivo,
            ':id_usuario' => $id_usuario
        ]);
        
        $obj = $stmt_get->fetch(PDO::FETCH_ASSOC);
        if ($obj) {
            $monto_actual = (float)$obj['monto_actual'];
            $monto_objetivo = (float)$obj['monto_objetivo'];
            $nombre_meta = $obj['nombre'];
            
            $faltante = $monto_objetivo - $monto_actual;
            if ($faltante > 0) {
                // Determine the actual deposited amount (capped to the remaining target)
                $monto_real = min((float)$monto_deposit, $faltante);
                
                // 1. Update the objective's saved amount
                $query = "UPDATE objetivos SET monto_actual = monto_actual + :monto_real WHERE id_objetivo = :id_objetivo AND id_usuario = :id_usuario";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':monto_real' => $monto_real,
                    ':id_objetivo' => $id_objetivo,
                    ':id_usuario' => $id_usuario
                ]);
                
                // 2. Insert an expense movement to deduct from global balance
                $desc = "Abono a objetivo: " . $nombre_meta;
                $mov_query = "INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago) VALUES (:id_usuario, 'gasto', :monto, CURDATE(), 'Ahorro', :desc, 'Abono')";
                $stmt_mov = $db->prepare($mov_query);
                $stmt_mov->execute([
                    ':id_usuario' => $id_usuario,
                    ':monto' => $monto_real,
                    ':desc' => $desc
                ]);
                
                // Check if it reached the target after deposit
                if (($monto_actual + $monto_real) >= $monto_objetivo) {
                    $stmt_del_notif = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario AND categoria = 'objetivo_vencer' AND accion_url LIKE :url");
                    $stmt_del_notif->execute([
                        ':id_usuario' => $id_usuario,
                        ':url' => "%id={$id_objetivo}%"
                    ]);
                }
            }
        }
    }
} else if ($action === 'delete') {
    if (!empty($id_objetivo)) {
        // Fetch objective state to return money to balance
        $stmt_old = $db->prepare("SELECT monto_actual, nombre FROM objetivos WHERE id_objetivo = :id_objetivo AND id_usuario = :id_usuario");
        $stmt_old->execute([':id_objetivo' => $id_objetivo, ':id_usuario' => $id_usuario]);
        $old_obj = $stmt_old->fetch(PDO::FETCH_ASSOC);
        
        $query = "DELETE FROM objetivos WHERE id_objetivo = :id_objetivo AND id_usuario = :id_usuario";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id_objetivo' => $id_objetivo,
            ':id_usuario' => $id_usuario
        ]);
        
        if ($old_obj && (float)$old_obj['monto_actual'] > 0) {
            $desc = "Devolución por meta eliminada: " . $old_obj['nombre'];
            $mov_query = "INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago) VALUES (:id_usuario, 'ingreso', :monto, CURDATE(), 'Ahorro', :desc, 'Abono')";
            $stmt_mov = $db->prepare($mov_query);
            $stmt_mov->execute([
                ':id_usuario' => $id_usuario,
                ':monto' => (float)$old_obj['monto_actual'],
                ':desc' => $desc
            ]);
        }
        
        // Eliminar la notificación de éxito o vencimiento si existía
        $stmt_del_notif = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario AND categoria IN ('objetivo_logrado', 'objetivo_vencer') AND accion_url LIKE :url");
        $stmt_del_notif->execute([
            ':id_usuario' => $id_usuario,
            ':url' => "%id={$id_objetivo}%"
        ]);
    }
}

// Redirect back to Objetivos module
header("Location: ../index.php?mod=objetivos");
exit;
?>
