<?php
function generarNotificacionesAutomaticas($id_usuario, $db) {
    $hoy = new DateTime();
    $mes_actual = (int)$hoy->format('m');
    $anio_actual = (int)$hoy->format('Y');

    // --- 1. NOTIFICACIONES DE GASTOS FIJOS ---
    $stmt = $db->prepare("SELECT id_gasto_fijo, nombre, monto, monto_pagado_mes, dia_vencimiento, icono FROM gastos_fijos WHERE id_usuario = :id_usuario");
    $stmt->execute([':id_usuario' => $id_usuario]);
    $gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($gastos as $gasto) {
        if ($gasto['monto_pagado_mes'] < $gasto['monto']) {
            // Calcular la fecha de vencimiento de este mes
            $dia_venc = (int)$gasto['dia_vencimiento'];
            
            // Si el día de vencimiento es mayor que los días del mes actual, lo ajustamos al último día del mes
            $dias_del_mes = cal_days_in_month(CAL_GREGORIAN, $mes_actual, $anio_actual);
            if ($dia_venc > $dias_del_mes) {
                $dia_venc = $dias_del_mes;
            }

            $fecha_vencimiento = new DateTime("$anio_actual-$mes_actual-$dia_venc");
            
            // Si el día de vencimiento ya pasó este mes, y no ha pagado, está VENCIDO
            // (Si queremos avisar del mes siguiente, cambiaríamos la lógica, pero lo normal es que deba el de este mes)
            $interval = $hoy->diff($fecha_vencimiento);
            $dias_diferencia = (int)$interval->format('%r%a');

            $tipo = '';
            $titulo = '';
            $mensaje = '';
            
            if ($dias_diferencia < 0) {
                $tipo = 'urgent';
                $titulo = "Pago vencido: {$gasto['nombre']}";
                $mensaje = "El pago de {$gasto['icono']} {$gasto['nombre']} por $" . number_format($gasto['monto'], 2) . " venció hace " . abs($dias_diferencia) . " días.";
            } elseif ($dias_diferencia === 0) {
                $tipo = 'warning';
                $titulo = "Vence hoy: {$gasto['nombre']}";
                $mensaje = "Recuerda que hoy es el último día para pagar {$gasto['icono']} {$gasto['nombre']} ($" . number_format($gasto['monto'], 2) . ").";
            } elseif ($dias_diferencia <= 3) {
                $tipo = 'info';
                $titulo = "Próximo vencimiento: {$gasto['nombre']}";
                $mensaje = "Faltan $dias_diferencia días para el pago de {$gasto['icono']} {$gasto['nombre']}.";
            }

            if ($tipo !== '') {
                // Verificar si ya notificamos esto recientemente (en los últimos 5 días)
                $stmtCheck = $db->prepare("SELECT id_notificacion FROM notificaciones 
                                           WHERE id_usuario = :id 
                                           AND categoria = 'gasto_fijo' 
                                           AND titulo = :titulo 
                                           AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 5 DAY) 
                                           LIMIT 1");
                $stmtCheck->execute([':id' => $id_usuario, ':titulo' => $titulo]);
                
                if (!$stmtCheck->fetch()) {
                    // Crear notificación
                    $stmtInsert = $db->prepare("INSERT INTO notificaciones (id_usuario, tipo, icono, titulo, mensaje, categoria, accion_texto, accion_url) 
                                                VALUES (:id, :tipo, :icono, :titulo, :mensaje, 'gasto_fijo', 'Ver Gastos', 'index.php?mod=gastos_fijos')");
                    $stmtInsert->execute([
                        ':id' => $id_usuario,
                        ':tipo' => $tipo,
                        ':icono' => '💸',
                        ':titulo' => $titulo,
                        ':mensaje' => $mensaje
                    ]);
                }
            }
        }
    }

    // --- 2. NOTIFICACIONES DE OBJETIVOS COMPLETADOS ---
    $stmtObj = $db->prepare("SELECT id_objetivo, nombre, icono FROM objetivos WHERE id_usuario = :id_usuario AND monto_actual >= monto_objetivo");
    $stmtObj->execute([':id_usuario' => $id_usuario]);
    $objetivos = $stmtObj->fetchAll(PDO::FETCH_ASSOC);

    foreach ($objetivos as $obj) {
        $tituloObj = "¡Objetivo completado!";
        $mensajeObj = "Has alcanzado el 100% de tu meta para {$obj['icono']} {$obj['nombre']}. ¡Felicidades!";
        
        // Verificar si ya se notificó
        $stmtCheckObj = $db->prepare("SELECT id_notificacion FROM notificaciones 
                                      WHERE id_usuario = :id 
                                      AND categoria = 'objetivo_logrado' 
                                      AND accion_url LIKE :url 
                                      LIMIT 1");
        $stmtCheckObj->execute([':id' => $id_usuario, ':url' => "%id={$obj['id_objetivo']}%"]);

        if (!$stmtCheckObj->fetch()) {
            $stmtInsertObj = $db->prepare("INSERT INTO notificaciones (id_usuario, tipo, icono, titulo, mensaje, categoria, accion_texto, accion_url) 
                                           VALUES (:id, 'success', :icono, :titulo, :mensaje, 'objetivo_logrado', 'Ver Objetivos', :url)");
            $stmtInsertObj->execute([
                ':id' => $id_usuario,
                ':icono' => '🏆',
                ':titulo' => $tituloObj,
                ':mensaje' => $mensajeObj,
                ':url' => "index.php?mod=objetivos&id={$obj['id_objetivo']}"
            ]);
        }
    }

    // --- 3. NOTIFICACIONES DE OBJETIVOS POR VENCER ---
    $stmtObjVencer = $db->prepare("SELECT id_objetivo, nombre, icono, fecha_limite FROM objetivos WHERE id_usuario = :id_usuario AND monto_actual < monto_objetivo AND fecha_limite IS NOT NULL");
    $stmtObjVencer->execute([':id_usuario' => $id_usuario]);
    $objetivos_vencer = $stmtObjVencer->fetchAll(PDO::FETCH_ASSOC);

    foreach ($objetivos_vencer as $obj) {
        $fecha_limite = new DateTime($obj['fecha_limite']);
        $interval = $hoy->diff($fecha_limite);
        $dias_diferencia = (int)$interval->format('%r%a');

        $tipo = '';
        $tituloObj = '';
        $mensajeObj = '';

        if ($dias_diferencia < 0) {
            $tipo = 'urgent';
            $tituloObj = "Objetivo vencido: {$obj['nombre']}";
            $mensajeObj = "La fecha límite para tu meta {$obj['icono']} {$obj['nombre']} venció hace " . abs($dias_diferencia) . " días y aún no se ha cumplido.";
        } elseif ($dias_diferencia === 0) {
            $tipo = 'warning';
            $tituloObj = "Vence hoy: {$obj['nombre']}";
            $mensajeObj = "Hoy es la fecha límite para completar tu meta {$obj['icono']} {$obj['nombre']}.";
        } elseif ($dias_diferencia > 0 && $dias_diferencia <= 5) {
            $tipo = 'warning';
            $tituloObj = "Objetivo próximo a vencer: {$obj['nombre']}";
            $mensajeObj = "Faltan solo $dias_diferencia días para la fecha límite de tu meta {$obj['icono']} {$obj['nombre']}.";
        }

        if ($tipo !== '') {
            $stmtCheckObjVencer = $db->prepare("SELECT id_notificacion FROM notificaciones 
                                                WHERE id_usuario = :id 
                                                AND categoria = 'objetivo_vencer' 
                                                AND accion_url LIKE :url 
                                                AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 5 DAY) 
                                                LIMIT 1");
            $stmtCheckObjVencer->execute([':id' => $id_usuario, ':url' => "%id={$obj['id_objetivo']}%"]);

            if (!$stmtCheckObjVencer->fetch()) {
                $stmtInsertObjVencer = $db->prepare("INSERT INTO notificaciones (id_usuario, tipo, icono, titulo, mensaje, categoria, accion_texto, accion_url) 
                                                     VALUES (:id, :tipo, :icono, :titulo, :mensaje, 'objetivo_vencer', 'Ver Objetivo', :url)");
                $stmtInsertObjVencer->execute([
                    ':id' => $id_usuario,
                    ':tipo' => $tipo,
                    ':icono' => '⏱️',
                    ':titulo' => $tituloObj,
                    ':mensaje' => $mensajeObj,
                    ':url' => "index.php?mod=objetivos&id={$obj['id_objetivo']}"
                ]);
            }
        }
    }
}
?>
