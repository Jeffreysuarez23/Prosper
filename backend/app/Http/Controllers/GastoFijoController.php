<?php

namespace App\Http\Controllers;

use App\Models\GastoFijo;
use Illuminate\Http\Request;

class GastoFijoController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->gastoFijos()->orderBy('dia_vencimiento', 'asc');
        
        if ($request->filled('q')) {
            $query->where('nombre', 'LIKE', '%' . $request->q . '%');
        }
        
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'monto' => 'required|numeric|min:0',
            'dia_vencimiento' => 'required|integer|min:1|max:31',
            'icono' => 'nullable|string|max:50',
        ]);

        $validated['fecha_ultimo_pago'] = now()->toDateString();
        $gastoFijo = $request->user()->gastoFijos()->create($validated);
        
        return response()->json($gastoFijo, 201);
    }

    public function show(GastoFijo $gastos_fijo)
    {
        return response()->json($gastos_fijo);
    }

    public function update(Request $request, GastoFijo $gastos_fijo)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'monto' => 'sometimes|required|numeric|min:0',
            'monto_pagado_mes' => 'numeric|min:0',
            'dia_vencimiento' => 'sometimes|required|integer|min:1|max:31',
            'icono' => 'nullable|string|max:50',
            'fecha_ultimo_pago' => 'nullable|date',
        ]);

        $gastos_fijo->update($validated);

        if ($gastos_fijo->monto_pagado_mes >= $gastos_fijo->monto) {
            $request->user()->notificaciones()
                ->where('categoria', 'gasto_fijo')
                ->where('titulo', 'LIKE', '%' . $gastos_fijo->nombre . '%')
                ->delete();
        }

        return response()->json($gastos_fijo);
    }

    public function destroy(Request $request, GastoFijo $gastos_fijo)
    {
        if ($gastos_fijo->user_id !== $request->user()->id) abort(403);

        $currentMonth = now()->format('Y-m');
        $lastPaidMonth = $gastos_fijo->fecha_ultimo_pago ? \Carbon\Carbon::parse($gastos_fijo->fecha_ultimo_pago)->format('Y-m') : '';

        if ($lastPaidMonth === $currentMonth && $gastos_fijo->monto_pagado_mes > 0) {
            $request->user()->movimientos()->create([
                'tipo' => 'ingreso',
                'monto' => $gastos_fijo->monto_pagado_mes,
                'fecha' => now()->toDateString(),
                'categoria' => 'Otros',
                'descripcion' => "Reembolso por eliminación de gasto fijo: " . $gastos_fijo->nombre,
                'metodo_pago' => 'efectivo',
            ]);
        }

        $request->user()->notificaciones()
            ->where('categoria', 'gasto_fijo')
            ->where('titulo', 'LIKE', '%' . $gastos_fijo->nombre . '%')
            ->delete();

        $gastos_fijo->delete();
        return response()->json(null, 204);
    }

    // El parámetro para abono es "gastoFijo" porque la ruta es manual: api/gastos-fijos/{gastoFijo}/abono
    public function payPartial(Request $request, GastoFijo $gastoFijo)
    {
        if ($gastoFijo->user_id !== $request->user()->id) abort(403);

        $validated = $request->validate([
            'abono' => 'required|numeric|min:0.01'
        ]);

        $abono = $validated['abono'];
        $currentMonth = now()->format('Y-m');
        $lastPaidMonth = $gastoFijo->fecha_ultimo_pago ? \Carbon\Carbon::parse($gastoFijo->fecha_ultimo_pago)->format('Y-m') : '';

        if ($lastPaidMonth === $currentMonth) {
            $nuevo_pagado = $gastoFijo->monto_pagado_mes + $abono;
        } else {
            $nuevo_pagado = $abono;
        }

        if ($nuevo_pagado > $gastoFijo->monto) {
            $nuevo_pagado = $gastoFijo->monto;
        }

        $gastoFijo->update([
            'fecha_ultimo_pago' => now()->toDateString(),
            'monto_pagado_mes' => $nuevo_pagado
        ]);

        $request->user()->movimientos()->create([
            'tipo' => 'gasto',
            'monto' => $abono,
            'fecha' => now()->toDateString(),
            'categoria' => 'Servicios',
            'descripcion' => "Pago parcial de gasto fijo: " . $gastoFijo->nombre,
            'metodo_pago' => 'efectivo',
        ]);

        if ($nuevo_pagado >= $gastoFijo->monto) {
            $request->user()->notificaciones()
                ->where('categoria', 'gasto_fijo')
                ->where('titulo', 'LIKE', '%' . $gastoFijo->nombre . '%')
                ->delete();
        }

        return response()->json($gastoFijo);
    }
}
