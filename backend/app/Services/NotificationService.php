<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    public function generateForUser(User $user)
    {
        $this->checkGastosFijos($user);
        $this->checkObjetivosLogrados($user);
        $this->checkObjetivosPorVencer($user);
    }

    private function checkGastosFijos(User $user)
    {
        $hoy = Carbon::today();
        $mes_actual = $hoy->month;
        $anio_actual = $hoy->year;
        $dias_del_mes = $hoy->daysInMonth;

        $gastos = $user->gastoFijos()
            ->where(function($query) {
                $query->whereRaw('monto_pagado_mes < monto')
                      ->orWhereNull('fecha_ultimo_pago')
                      ->orWhereRaw('DATE_FORMAT(fecha_ultimo_pago, "%Y-%m") != DATE_FORMAT(CURRENT_DATE, "%Y-%m")');
            })->get();

        foreach ($gastos as $gasto) {
            $dia_venc = $gasto->dia_vencimiento;
            if ($dia_venc > $dias_del_mes) {
                $dia_venc = $dias_del_mes;
            }

            $fecha_vencimiento = Carbon::createFromDate($anio_actual, $mes_actual, $dia_venc)->startOfDay();
            $dias_diferencia = (int) $hoy->diffInDays($fecha_vencimiento, false);

            $tipo = '';
            $titulo = '';
            $mensaje = '';

            if ($dias_diferencia < 0) {
                if ($dias_diferencia == -1) {
                    $tipo = 'urgent';
                    $titulo = "Venció ayer {$dia_venc}: {$gasto->nombre}";
                    $mensaje = "El pago de {$gasto->icono} {$gasto->nombre} por $" . number_format($gasto->monto, 2) . " venció ayer.";
                } else {
                    $tipo = 'urgent';
                    $titulo = "Pago vencido: {$gasto->nombre}";
                    $mensaje = "El pago de {$gasto->icono} {$gasto->nombre} por $" . number_format($gasto->monto, 2) . " venció hace " . abs((int)$dias_diferencia) . " días.";
                }
            } elseif ($dias_diferencia == 0) {
                $tipo = 'warning';
                $titulo = "Vence hoy {$dia_venc}: {$gasto->nombre}";
                $mensaje = "Recuerda que hoy es el último día para pagar {$gasto->icono} {$gasto->nombre} ($" . number_format($gasto->monto, 2) . ").";
            } elseif ($dias_diferencia == 1) {
                $tipo = 'info';
                $titulo = "Vence mañana {$dia_venc}: {$gasto->nombre}";
                $mensaje = "Mañana es el último día para pagar {$gasto->icono} {$gasto->nombre} ($" . number_format($gasto->monto, 2) . ").";
            } elseif ($dias_diferencia > 1 && $dias_diferencia <= 3) {
                $tipo = 'info';
                $titulo = "Próximo vencimiento: {$gasto->nombre}";
                $mensaje = "Faltan " . (int)$dias_diferencia . " días para el pago de {$gasto->icono} {$gasto->nombre}.";
            }

            if ($tipo !== '') {
                $exists = $user->notificaciones()
                    ->where('categoria', 'gasto_fijo')
                    ->where('titulo', $titulo)
                    ->where('created_at', '>=', Carbon::now()->subDays(5))
                    ->exists();

                if (!$exists) {
                    $user->notificaciones()->create([
                        'tipo' => $tipo,
                        'icono' => '💸',
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                        'categoria' => 'gasto_fijo',
                        'accion_texto' => 'Ver Gastos',
                        'accion_url' => '/gastos-fijos'
                    ]);
                }
            }
        }
    }

    private function checkObjetivosLogrados(User $user)
    {
        $objetivos = $user->objetivos()->whereRaw('monto_actual >= monto_objetivo')->get();

        foreach ($objetivos as $obj) {
            $titulo = "¡Objetivo completado!";
            $mensaje = "Has alcanzado el 100% de tu meta para {$obj->icono} {$obj->nombre}. ¡Felicidades!";
            $url = "/objetivos?id={$obj->id}";

            $exists = $user->notificaciones()
                ->where('categoria', 'objetivo_logrado')
                ->where('accion_url', 'LIKE', "%id={$obj->id}%")
                ->exists();

            if (!$exists) {
                $user->notificaciones()->create([
                    'tipo' => 'success',
                    'icono' => '🏆',
                    'titulo' => $titulo,
                    'mensaje' => $mensaje,
                    'categoria' => 'objetivo_logrado',
                    'accion_texto' => 'Ver Objetivos',
                    'accion_url' => $url
                ]);
            }
        }
    }

    private function checkObjetivosPorVencer(User $user)
    {
        $hoy = Carbon::today();
        $objetivos = $user->objetivos()
            ->whereRaw('monto_actual < monto_objetivo')
            ->whereNotNull('fecha_limite')
            ->get();

        foreach ($objetivos as $obj) {
            $fecha_limite = Carbon::parse($obj->fecha_limite)->startOfDay();
            $dias_diferencia = (int) $hoy->diffInDays($fecha_limite, false);

            $tipo = '';
            $titulo = '';
            $mensaje = '';

            if ($dias_diferencia < 0) {
                if ($dias_diferencia == -1) {
                    $tipo = 'urgent';
                    $titulo = "Objetivo venció ayer {$fecha_limite->day}: {$obj->nombre}";
                    $mensaje = "La fecha límite para tu meta {$obj->icono} {$obj->nombre} venció ayer y aún no se ha cumplido.";
                } else {
                    $tipo = 'urgent';
                    $titulo = "Objetivo vencido: {$obj->nombre}";
                    $mensaje = "La fecha límite para tu meta {$obj->icono} {$obj->nombre} venció hace " . abs((int)$dias_diferencia) . " días y aún no se ha cumplido.";
                }
            } elseif ($dias_diferencia == 0) {
                $tipo = 'warning';
                $titulo = "Vence hoy {$fecha_limite->day}: {$obj->nombre}";
                $mensaje = "Hoy es la fecha límite para completar tu meta {$obj->icono} {$obj->nombre}.";
            } elseif ($dias_diferencia == 1) {
                $tipo = 'warning';
                $titulo = "Vence mañana {$fecha_limite->day}: {$obj->nombre}";
                $mensaje = "Mañana es la fecha límite para completar tu meta {$obj->icono} {$obj->nombre}.";
            } elseif ($dias_diferencia > 1 && $dias_diferencia <= 5) {
                $tipo = 'warning';
                $titulo = "Objetivo próximo a vencer: {$obj->nombre}";
                $mensaje = "Faltan solo " . (int)$dias_diferencia . " días para la fecha límite de tu meta {$obj->icono} {$obj->nombre}.";
            }

            if ($tipo !== '') {
                $exists = $user->notificaciones()
                    ->where('categoria', 'objetivo_vencer')
                    ->where('accion_url', 'LIKE', "%id={$obj->id}%")
                    ->where('created_at', '>=', Carbon::now()->subDays(5))
                    ->exists();

                if (!$exists) {
                    $user->notificaciones()->create([
                        'tipo' => $tipo,
                        'icono' => '⏱️',
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                        'categoria' => 'objetivo_vencer',
                        'accion_texto' => 'Ver Objetivo',
                        'accion_url' => "/objetivos?id={$obj->id}"
                    ]);
                }
            }
        }
    }
}
