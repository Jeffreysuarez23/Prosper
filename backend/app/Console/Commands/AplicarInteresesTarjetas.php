<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TarjetaCredito;
use Carbon\Carbon;

class AplicarInteresesTarjetas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tarjetas:aplicar-intereses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aplica intereses mensuales a las tarjetas de crédito con pago atrasado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        // Obtener TODAS las tarjetas para limpiar notificaciones obsoletas incluso si se pagaron
        $tarjetas = TarjetaCredito::all();
                                  
        $countIntereses = 0;
        $countNotificaciones = 0;

        foreach ($tarjetas as $tarjeta) {
            $tituloNotif = '';
            $mensajeNotif = '';
            $categoriaNotif = 'info';

            if ($tarjeta->deuda_actual > 0) {
                $todayDay = $today->day;
                $diffPago = $tarjeta->dia_pago - $todayDay;

                if ($diffPago < 0) {
                    // Atrasado
                    $ultimoCobro = $tarjeta->fecha_ultimo_interes ? Carbon::parse($tarjeta->fecha_ultimo_interes) : null;
                    $cobrarInteres = (!$ultimoCobro || $ultimoCobro->format('Y-m') !== $today->format('Y-m'));
                    
                    if ($cobrarInteres && $tarjeta->tasa_interes > 0) {
                        $tasaMensual = $tarjeta->tasa_interes / 12 / 100;
                        $interes = round($tarjeta->deuda_actual * $tasaMensual, 2);
                        
                        if ($interes > 0) {
                            $tarjeta->update([
                                'deuda_actual' => $tarjeta->deuda_actual + $interes,
                                'fecha_ultimo_interes' => $today->toDateString()
                            ]);
                            
                            $tituloNotif = 'Intereses aplicados: ' . $tarjeta->nombre;
                            $mensajeNotif = 'Tu pago está atrasado. Se ha aplicado un cargo por intereses de $' . number_format($interes, 2) . '.';
                            $categoriaNotif = 'alerta';
                            $countIntereses++;
                        }
                    } else {
                        $tituloNotif = 'Pago atrasado: ' . $tarjeta->nombre;
                        $mensajeNotif = 'Tu fecha de pago ha pasado y aún tienes deuda pendiente.';
                        $categoriaNotif = 'alerta';
                    }
                } elseif ($diffPago == 0) {
                    $tituloNotif = 'Pago Hoy: ' . $tarjeta->nombre;
                    $mensajeNotif = 'Recuerda que hoy es tu día de pago. Tienes una deuda de $' . number_format($tarjeta->deuda_actual, 2) . '.';
                    $categoriaNotif = 'alerta';
                } elseif ($diffPago == 1) {
                    $tituloNotif = 'Pago Mañana: ' . $tarjeta->nombre;
                    $mensajeNotif = 'Tienes hasta mañana para pagar la tarjeta ' . $tarjeta->nombre . ' sino se te cobrarán intereses.';
                    $categoriaNotif = 'warning';
                } elseif ($diffPago == 4) {
                    $tituloNotif = 'Pago Pronto: ' . $tarjeta->nombre;
                    $mensajeNotif = 'Faltan 4 días para tu fecha de pago de la tarjeta ' . $tarjeta->nombre . '.';
                    $categoriaNotif = 'info';
                }
            }

            // LIMPIEZA DE NOTIFICACIONES OBSOLETAS
            $titulosLimpiar = [
                'Pago atrasado: ' . $tarjeta->nombre,
                'Pago Hoy: ' . $tarjeta->nombre,
                'Pago Mañana: ' . $tarjeta->nombre,
                'Pago Pronto: ' . $tarjeta->nombre,
            ];
            
            if ($tituloNotif) {
                $titulosLimpiar = array_diff($titulosLimpiar, [$tituloNotif]);
            }

            // Eliminar las notificaciones no leídas que ya no aplican para esta tarjeta
            $tarjeta->user->notificaciones()
                ->where('leida', false)
                ->whereIn('titulo', $titulosLimpiar)
                ->delete();

            if ($tituloNotif) {
                // Evitar notificaciones duplicadas el mismo día
                $existeNotif = $tarjeta->user->notificaciones()
                    ->where('titulo', $tituloNotif)
                    ->whereDate('created_at', $today->toDateString())
                    ->exists();

                if (!$existeNotif) {
                    $tarjeta->user->notificaciones()->create([
                        'titulo' => $tituloNotif,
                        'mensaje' => $mensajeNotif,
                        'categoria' => $categoriaNotif,
                        'leida' => false,
                        'accion_url' => '/tarjetas-credito'
                    ]);
                    $countNotificaciones++;
                }
            }
        }
        
        $this->info("Se han aplicado intereses a {$countIntereses} tarjetas y generado {$countNotificaciones} notificaciones.");
    }
}
