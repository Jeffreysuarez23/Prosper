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
$id_gasto_fijo = $_POST['id_gasto_fijo'] ?? '';

if ($action === 'save') {
    $nombre = $_POST['nombre'] ?? '';
    // Limpiamos el formato de moneda si viene con puntos o comas
    $monto_post = $_POST['monto'] ?? '0';
    $monto = str_replace(['.', ','], ['', '.'], $monto_post);
    
    $dia_vencimiento = (int)($_POST['dia_vencimiento'] ?? 1);

    $icono = $_POST['icono'] ?? '🏠';
    
    if (empty($id_gasto_fijo)) {
        // Insert
        $query = "INSERT INTO gastos_fijos (id_usuario, nombre, monto, dia_vencimiento, icono, fecha_ultimo_pago) VALUES (:id_usuario, :nombre, :monto, :dia_vencimiento, :icono, CURDATE())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':nombre' => $nombre,
            ':monto' => $monto,
            ':dia_vencimiento' => $dia_vencimiento,

            ':icono' => $icono
        ]);
    } else {
        // Update
        $query = "UPDATE gastos_fijos SET nombre = :nombre, monto = :monto, dia_vencimiento = :dia_vencimiento, icono = :icono WHERE id_gasto_fijo = :id_gasto_fijo AND id_usuario = :id_usuario";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':nombre' => $nombre,
            ':monto' => $monto,
            ':dia_vencimiento' => $dia_vencimiento,

            ':icono' => $icono,
            ':id_gasto_fijo' => $id_gasto_fijo,
            ':id_usuario' => $id_usuario
        ]);
    }
} else if ($action === 'pay_partial') {
    $abono_post = $_POST['abono'] ?? '0';
    $abono = (float)str_replace(['.', ','], ['', '.'], $abono_post);
    
    if (!empty($id_gasto_fijo) && $abono > 0) {
        // Get the expense details
        $stmt_get = $db->prepare("SELECT nombre, monto, monto_pagado_mes, fecha_ultimo_pago FROM gastos_fijos WHERE id_gasto_fijo = :id_gasto_fijo AND id_usuario = :id_usuario");
        $stmt_get->execute([':id_gasto_fijo' => $id_gasto_fijo, ':id_usuario' => $id_usuario]);
        $gasto = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($gasto) {
            $current_month = date('Y-m');
            $last_paid_month = $gasto['fecha_ultimo_pago'] ? date('Y-m', strtotime($gasto['fecha_ultimo_pago'])) : '';
            
            if ($last_paid_month === $current_month) {
                // Same month, add to the existing pagado
                $nuevo_pagado = $gasto['monto_pagado_mes'] + $abono;
                $max_abono_permitido = $gasto['monto'] - $gasto['monto_pagado_mes'];
            } else {
                // New month, start fresh
                $nuevo_pagado = $abono;
                $max_abono_permitido = $gasto['monto'];
            }
            
            if ($abono > $max_abono_permitido) {
                $abono = $max_abono_permitido;
                $nuevo_pagado = $gasto['monto'];
            }
            
            // Limit to the total expense (fallback)
            if ($nuevo_pagado > $gasto['monto']) {
                $nuevo_pagado = $gasto['monto'];
            }

            // Update the last payment date and paid amount
            $query = "UPDATE gastos_fijos SET fecha_ultimo_pago = CURDATE(), monto_pagado_mes = :pagado WHERE id_gasto_fijo = :id_gasto_fijo AND id_usuario = :id_usuario";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':pagado' => $nuevo_pagado,
                ':id_gasto_fijo' => $id_gasto_fijo,
                ':id_usuario' => $id_usuario
            ]);

            // Insert a movement to deduct from balance
            $desc = "Pago parcial de gasto fijo: " . $gasto['nombre'];
            $mov_query = "INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago) VALUES (:id_usuario, 'gasto', :monto, CURDATE(), 'Servicios', :desc, 'Abono')";
            $stmt_mov = $db->prepare($mov_query);
            $stmt_mov->execute([
                ':id_usuario' => $id_usuario,
                ':monto' => $abono,
                ':desc' => $desc
            ]);
            
            // Si el gasto fijo ha sido pagado en su totalidad, eliminamos sus notificaciones
            if ($nuevo_pagado >= $gasto['monto']) {
                $stmt_del_notif = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario AND categoria = 'gasto_fijo' AND titulo LIKE :titulo");
                $stmt_del_notif->execute([
                    ':id_usuario' => $id_usuario,
                    ':titulo' => "%" . $gasto['nombre'] . "%"
                ]);
            }
        }
    }
} else if ($action === 'delete') {
    if (!empty($id_gasto_fijo)) {
        // Fetch the expense details to refund the money paid this month
        $stmt_get = $db->prepare("SELECT nombre, monto_pagado_mes, fecha_ultimo_pago FROM gastos_fijos WHERE id_gasto_fijo = :id_gasto_fijo AND id_usuario = :id_usuario");
        $stmt_get->execute([':id_gasto_fijo' => $id_gasto_fijo, ':id_usuario' => $id_usuario]);
        $gasto = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($gasto) {
            $current_month = date('Y-m');
            $last_paid_month = $gasto['fecha_ultimo_pago'] ? date('Y-m', strtotime($gasto['fecha_ultimo_pago'])) : '';
            
            if ($last_paid_month === $current_month && $gasto['monto_pagado_mes'] > 0) {
                // Refund money
                $refund_amount = $gasto['monto_pagado_mes'];
                $desc = "Reembolso por eliminación de gasto fijo: " . $gasto['nombre'];
                $mov_query = "INSERT INTO movimientos (id_usuario, tipo, monto, fecha, categoria, descripcion, metodo_pago) VALUES (:id_usuario, 'ingreso', :monto, CURDATE(), 'Otros', :desc, 'Ajuste')";
                $stmt_mov = $db->prepare($mov_query);
                $stmt_mov->execute([
                    ':id_usuario' => $id_usuario,
                    ':monto' => $refund_amount,
                    ':desc' => $desc
                ]);
            }
        }

        $query = "DELETE FROM gastos_fijos WHERE id_gasto_fijo = :id_gasto_fijo AND id_usuario = :id_usuario";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id_gasto_fijo' => $id_gasto_fijo,
            ':id_usuario' => $id_usuario
        ]);
        
        // Eliminar notificaciones asociadas al gasto eliminado
        if ($gasto) {
            $stmt_del_notif = $db->prepare("DELETE FROM notificaciones WHERE id_usuario = :id_usuario AND categoria = 'gasto_fijo' AND titulo LIKE :titulo");
            $stmt_del_notif->execute([
                ':id_usuario' => $id_usuario,
                ':titulo' => "%" . $gasto['nombre'] . "%"
            ]);
        }
    }
}

// Redirect back to Gastos Fijos module
header("Location: ../index.php?mod=gastos_fijos");
exit;
?>
