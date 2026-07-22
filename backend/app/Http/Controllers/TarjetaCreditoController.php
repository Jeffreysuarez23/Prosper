<?php

namespace App\Http\Controllers;

use App\Models\TarjetaCredito;
use App\Models\CompraTarjeta;
use App\Models\HistorialTarjetaCredito;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TarjetaCreditoController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->tarjetaCreditos()->orderBy('dia_pago', 'asc');

        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'LIKE', '%' . $request->q . '%')
                  ->orWhere('banco', 'LIKE', '%' . $request->q . '%');
            });
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'banco' => 'nullable|string|max:100',
            'ultimos_digitos' => 'nullable|string|max:4',
            'limite_credito' => 'required|numeric|min:0',
            'deuda_actual' => 'nullable|numeric|min:0',
            'dia_corte' => 'required|integer|min:1|max:31',
            'dia_pago' => 'required|integer|min:1|max:31',
            'tasa_interes' => 'nullable|numeric|min:0|max:100',
            'icono' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        $validated['deuda_actual'] = $validated['deuda_actual'] ?? 0;

        $user = $request->user();
        $plan = $user->plan;
        $tarjetasCount = $user->tarjetaCreditos()->count();

        if ($plan === 'gratis') {
            return response()->json(['message' => 'El plan Gratis no incluye Tarjetas de Crédito.'], 403);
        }

        if ($plan === 'pro' && $tarjetasCount >= 1) {
            return response()->json(['message' => 'Límite de 1 tarjeta de crédito alcanzado para el plan Pro.'], 403);
        }

        $tarjeta = $user->tarjetaCreditos()->create($validated);

        // Ejecutar evaluación de notificaciones inmediatamente
        \Illuminate\Support\Facades\Artisan::call('tarjetas:aplicar-intereses');

        return response()->json($tarjeta->fresh(), 201);
    }

    public function show(TarjetaCredito $tarjetas_credito)
    {
        return response()->json($tarjetas_credito);
    }

    public function update(Request $request, TarjetaCredito $tarjetas_credito)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'banco' => 'nullable|string|max:100',
            'ultimos_digitos' => 'nullable|string|max:4',
            'limite_credito' => 'sometimes|required|numeric|min:0',
            'deuda_actual' => 'nullable|numeric|min:0',
            'dia_corte' => 'sometimes|required|integer|min:1|max:31',
            'dia_pago' => 'sometimes|required|integer|min:1|max:31',
            'tasa_interes' => 'nullable|numeric|min:0|max:100',
            'icono' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
        ]);

        $todayDay = now()->day;
        
        // Reversión inteligente de intereses si el usuario corrige su día de pago
        if (isset($validated['dia_pago']) && $validated['dia_pago'] >= $todayDay) {
            $ultimoCobro = $tarjetas_credito->fecha_ultimo_interes ? \Carbon\Carbon::parse($tarjetas_credito->fecha_ultimo_interes) : null;
            
            // Si se le cobró interés este mes
            if ($ultimoCobro && $ultimoCobro->format('Y-m') === now()->format('Y-m')) {
                // Solo revertimos si el usuario no modificó la deuda manualmente al editar
                if (isset($validated['deuda_actual']) && $validated['deuda_actual'] == $tarjetas_credito->deuda_actual) {
                    $tasaMensual = $tarjetas_credito->tasa_interes / 12 / 100;
                    if ($tasaMensual > 0) {
                        $deudaOriginal = $tarjetas_credito->deuda_actual / (1 + $tasaMensual);
                        $validated['deuda_actual'] = round($deudaOriginal, 2);
                        $validated['fecha_ultimo_interes'] = null; // Reseteamos para permitir futuros cobros
                        
                        // Ocultamos la notificación
                        $tarjetas_credito->user->notificaciones()
                            ->where('titulo', 'LIKE', 'Intereses aplicados: %')
                            ->whereDate('created_at', now()->toDateString())
                            ->delete();
                    }
                }
            }
        }

        $validated['deuda_actual'] = $validated['deuda_actual'] ?? 0;

        $tarjetas_credito->update($validated);

        // Ejecutar evaluación de notificaciones inmediatamente
        \Illuminate\Support\Facades\Artisan::call('tarjetas:aplicar-intereses');

        return response()->json($tarjetas_credito->fresh());
    }

    public function destroy(Request $request, TarjetaCredito $tarjetas_credito)
    {
        if ($tarjetas_credito->user_id !== $request->user()->id) abort(403);

        // Eliminar notificaciones asociadas al nombre de la tarjeta
        $request->user()->notificaciones()
            ->where('titulo', 'LIKE', '%: ' . $tarjetas_credito->nombre)
            ->delete();

        $tarjetas_credito->delete();
        return response()->json(null, 204);
    }

    public function pagarTarjeta(Request $request, TarjetaCredito $tarjetaCredito)
    {
        if ($tarjetaCredito->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'compra_id' => 'nullable'
        ]);

        $monto = $validated['monto'];
        $compra_id = $validated['compra_id'] ?? null;
        $compra = null;

        if ($compra_id && $compra_id !== 'intereses') {
            $compra = CompraTarjeta::where('id', $compra_id)
                ->where('tarjeta_credito_id', $tarjetaCredito->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();
                
            if ($compra->estado === 'pagado') {
                return response()->json(['message' => 'Esta compra ya está pagada completamente.'], 400);
            }
        }

        // --- CALCULAR INTERÉS PENDIENTE ANTES DE PAGAR ---
        $today = now();
        $ultimoCobro = $tarjetaCredito->fecha_ultimo_interes ? \Carbon\Carbon::parse($tarjetaCredito->fecha_ultimo_interes) : null;
        $cobrarInteres = (!$ultimoCobro || $ultimoCobro->format('Y-m') !== $today->format('Y-m'));

        if ($cobrarInteres && $tarjetaCredito->deuda_actual > 0 && $today->day > $tarjetaCredito->dia_pago && $tarjetaCredito->tasa_interes > 0) {
            // Siempre aplicar intereses al momento de pagar cuando el pago está atrasado
            $tasaMensual = $tarjetaCredito->tasa_interes / 12 / 100;
            $interes = round($tarjetaCredito->deuda_actual * $tasaMensual, 2);
            $tarjetaCredito->deuda_actual += $interes;
            $tarjetaCredito->fecha_ultimo_interes = $today->toDateString();
            $tarjetaCredito->save();
            
            if ($compra_id === 'intereses') {
                $compra = CompraTarjeta::create([
                    'tarjeta_credito_id' => $tarjetaCredito->id,
                    'user_id' => $request->user()->id,
                    'descripcion' => 'Intereses generados',
                    'monto' => $interes,
                    'fecha' => now()->toDateString(),
                ]);
                $compra_id = $compra->id;
            }
        } else if ($compra_id === 'intereses') {
            return response()->json(['message' => 'No hay intereses pendientes por pagar.'], 400);
        }

        // No pagar más de lo que se debe
        if ($monto > $tarjetaCredito->deuda_actual) {
            $monto = $tarjetaCredito->deuda_actual;
        }

        $tarjetaCredito = \Illuminate\Support\Facades\DB::transaction(function () use ($tarjetaCredito, $monto, $request, $compra, $compra_id) {
            $lockedTarjeta = TarjetaCredito::where('id', $tarjetaCredito->id)->lockForUpdate()->first();

            $lockedTarjeta->update([
                'deuda_actual' => $lockedTarjeta->deuda_actual - $monto
            ]);

            // Actualizar la compra específica si se seleccionó
            $descripcionAbono = "Abono general a tarjeta";
            
            if (isset($compra)) {
                // Bloquear la fila de la compra para evitar race conditions
                $lockedCompra = CompraTarjeta::where('id', $compra->id)->lockForUpdate()->first();
                
                $nuevoPagado = $lockedCompra->monto_pagado + $monto;
                if ($nuevoPagado >= $lockedCompra->monto) {
                    $nuevoPagado = $lockedCompra->monto;
                    $lockedCompra->update(['estado' => 'pagado', 'monto_pagado' => $nuevoPagado]);
                } else {
                    $lockedCompra->update(['monto_pagado' => $nuevoPagado]);
                }
                
                $descripcionAbono = "Abono a compra: " . $lockedCompra->descripcion;
            }

            // Registrar movimiento de gasto (sale dinero del balance)
            $request->user()->movimientos()->create([
                'tipo' => 'gasto',
                'monto' => $monto,
                'fecha' => now()->toDateString(),
                'categoria' => 'Servicios',
                'descripcion' => "Pago a tarjeta: " . $lockedTarjeta->nombre,
                'metodo_pago' => 'transferencia',
            ]);
            
            HistorialTarjetaCredito::create([
                'tarjeta_credito_id' => $lockedTarjeta->id,
                'user_id' => $request->user()->id,
                'compra_tarjeta_id' => $compra_id ?? null,
                'tipo' => 'abono',
                'monto' => $monto,
                'descripcion' => $descripcionAbono,
                'fecha' => now()->toDateString(),
            ]);
            
            return $lockedTarjeta;
        });

        // Ejecutar evaluación de notificaciones inmediatamente
        \Illuminate\Support\Facades\Artisan::call('tarjetas:aplicar-intereses');

        return response()->json($tarjetaCredito->fresh());
    }

    public function agregarDeuda(Request $request, TarjetaCredito $tarjetaCredito)
    {
        if ($tarjetaCredito->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255'
        ]);

        $monto = $validated['monto'];
        $descripcion = $validated['descripcion'];

        $tarjetaCredito = \Illuminate\Support\Facades\DB::transaction(function () use ($tarjetaCredito, $monto, $descripcion, $request) {
            $lockedTarjeta = TarjetaCredito::where('id', $tarjetaCredito->id)->lockForUpdate()->first();
            $nuevaDeuda = $lockedTarjeta->deuda_actual + $monto;
            
            if ($nuevaDeuda > $lockedTarjeta->limite_credito) {
                return false;
            }

            $lockedTarjeta->update([
                'deuda_actual' => $nuevaDeuda
            ]);
            
            $compra = CompraTarjeta::create([
                'tarjeta_credito_id' => $lockedTarjeta->id,
                'user_id' => $request->user()->id,
                'descripcion' => $descripcion,
                'monto' => $monto,
                'fecha' => now()->toDateString(),
            ]);

            HistorialTarjetaCredito::create([
                'tarjeta_credito_id' => $lockedTarjeta->id,
                'user_id' => $request->user()->id,
                'compra_tarjeta_id' => $compra->id,
                'tipo' => 'compra',
                'monto' => $monto,
                'descripcion' => "Compra: " . $descripcion,
                'fecha' => now()->toDateString(),
            ]);

            return $lockedTarjeta;
        });

        if ($tarjetaCredito === false) {
            return response()->json([
                'message' => 'El monto ingresado supera el límite de crédito de la tarjeta.'
            ], 422);
        }

        // Ejecutar evaluación de notificaciones inmediatamente
        \Illuminate\Support\Facades\Artisan::call('tarjetas:aplicar-intereses');

        return response()->json($tarjetaCredito->fresh());
    }

    public function comprasPendientes(Request $request, TarjetaCredito $tarjetaCredito)
    {
        if ($tarjetaCredito->user_id !== $request->user()->id) abort(403);

        $compras = $tarjetaCredito->compras()
            ->where('estado', 'pendiente')
            ->orderBy('fecha', 'desc')
            ->get();

        return response()->json($compras);
    }

    public function historial(Request $request, TarjetaCredito $tarjetaCredito)
    {
        if ($tarjetaCredito->user_id !== $request->user()->id) abort(403);

        $compras = $tarjetaCredito->compras()
            ->with(['historial' => function($query) {
                $query->where('tipo', 'abono')->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($compras);
    }
}
