<?php

namespace App\Http\Controllers;

use App\Models\TarjetaCredito;
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
            'monto' => 'required|numeric|min:0.01'
        ]);

        $monto = $validated['monto'];

        // --- CALCULAR INTERÉS PENDIENTE ANTES DE PAGAR ---
        $today = now();
        $ultimoCobro = $tarjetaCredito->fecha_ultimo_interes ? \Carbon\Carbon::parse($tarjetaCredito->fecha_ultimo_interes) : null;
        if ($tarjetaCredito->deuda_actual > 0 && $today->day > $tarjetaCredito->dia_pago && $tarjetaCredito->tasa_interes > 0) {
            if (!$ultimoCobro || $ultimoCobro->format('Y-m') !== $today->format('Y-m')) {
                // Hay interés pendiente de cobrar
                $tasaMensual = $tarjetaCredito->tasa_interes / 12 / 100;
                $interes = $tarjetaCredito->deuda_actual * $tasaMensual;
                $tarjetaCredito->deuda_actual += $interes;
                $tarjetaCredito->fecha_ultimo_interes = $today->toDateString();
                $tarjetaCredito->save();
            }
        }

        // No pagar más de lo que se debe
        if ($monto > $tarjetaCredito->deuda_actual) {
            $monto = $tarjetaCredito->deuda_actual;
        }

        DB::transaction(function () use ($tarjetaCredito, $monto, $request) {
            $tarjetaCredito->update([
                'deuda_actual' => $tarjetaCredito->deuda_actual - $monto
            ]);

            // Registrar movimiento de gasto (sale dinero del balance)
            $request->user()->movimientos()->create([
                'tipo' => 'gasto',
                'monto' => $monto,
                'fecha' => now()->toDateString(),
                'categoria' => 'Servicios',
                'descripcion' => "Pago a tarjeta: " . $tarjetaCredito->nombre,
                'metodo_pago' => 'transferencia',
            ]);
        });

        // Ejecutar evaluación de notificaciones inmediatamente
        \Illuminate\Support\Facades\Artisan::call('tarjetas:aplicar-intereses');

        return response()->json($tarjetaCredito->fresh());
    }

    public function agregarDeuda(Request $request, TarjetaCredito $tarjetaCredito)
    {
        if ($tarjetaCredito->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01'
        ]);

        $monto = $validated['monto'];
        $nuevaDeuda = $tarjetaCredito->deuda_actual + $monto;

        // Validar que no se exceda el límite
        if ($nuevaDeuda > $tarjetaCredito->limite_credito) {
            return response()->json([
                'message' => 'El monto ingresado supera el límite de crédito de la tarjeta.'
            ], 422);
        }

        $tarjetaCredito->update([
            'deuda_actual' => $nuevaDeuda
        ]);

        // Ejecutar evaluación de notificaciones inmediatamente
        \Illuminate\Support\Facades\Artisan::call('tarjetas:aplicar-intereses');

        return response()->json($tarjetaCredito->fresh());
    }
}
