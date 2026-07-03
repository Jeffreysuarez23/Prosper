<?php

namespace App\Http\Controllers;

use App\Models\Objetivo;
use Illuminate\Http\Request;

class ObjetivoController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->objetivos()->orderBy('created_at', 'desc')->orderBy('id', 'desc');

        if ($request->filled('q')) {
            $query->where('nombre', 'LIKE', '%' . $request->q . '%');
        }

        if ($request->filled('status')) {
            if ($request->status === 'completed') {
                $query->whereRaw('monto_actual >= monto_objetivo');
            } else if ($request->status === 'progress') {
                $query->whereRaw('monto_actual < monto_objetivo');
            }
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'monto_objetivo' => 'required|numeric|min:0',
            'monto_actual' => 'numeric|min:0',
            'fecha_limite' => 'required|date',
            'icono' => 'nullable|string|max:10',
        ]);

        $objetivo = $request->user()->objetivos()->create($validated);

        if ($objetivo->monto_actual > 0) {
            $request->user()->movimientos()->create([
                'tipo' => 'gasto',
                'monto' => $objetivo->monto_actual,
                'fecha' => now()->toDateString(),
                'categoria' => 'Ahorro',
                'descripcion' => "Abono inicial a meta: " . $objetivo->nombre,
                'metodo_pago' => 'efectivo',
            ]);
        }

        return response()->json($objetivo, 201);
    }

    public function show(Objetivo $objetivo)
    {
        return response()->json($objetivo);
    }

    public function update(Request $request, Objetivo $objetivo)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'monto_objetivo' => 'sometimes|required|numeric|min:0',
            'monto_actual' => 'numeric|min:0',
            'fecha_limite' => 'sometimes|required|date',
            'icono' => 'nullable|string|max:10',
        ]);

        $old_monto_actual = $objetivo->monto_actual;
        $objetivo->update($validated);
        
        if (isset($validated['monto_actual'])) {
            $diff = $validated['monto_actual'] - $old_monto_actual;
            if ($diff != 0) {
                $tipo_mov = $diff > 0 ? 'gasto' : 'ingreso';
                $desc = ($diff > 0 ? "Abono a objetivo: " : "Retiro de objetivo: ") . $objetivo->nombre;
                
                $request->user()->movimientos()->create([
                    'tipo' => $tipo_mov,
                    'monto' => abs($diff),
                    'fecha' => now()->toDateString(),
                    'categoria' => 'Ahorro',
                    'descripcion' => $desc,
                    'metodo_pago' => 'efectivo',
                ]);
            }
        }

        if ($objetivo->monto_actual >= $objetivo->monto_objetivo) {
            $request->user()->notificaciones()
                ->where('categoria', 'objetivo_vencer')
                ->where('accion_url', 'LIKE', '%id=' . $objetivo->id . '%')
                ->delete();
        }

        return response()->json($objetivo);
    }

    public function destroy(Objetivo $objetivo)
    {
        $monto_actual = $objetivo->monto_actual;
        $nombre = $objetivo->nombre;
        
        auth()->user()->notificaciones()
            ->where('accion_url', 'LIKE', '%id=' . $objetivo->id . '%')
            ->delete();

        $objetivo->delete();
        
        if ($monto_actual > 0) {
            auth()->user()->movimientos()->create([
                'tipo' => 'ingreso',
                'monto' => $monto_actual,
                'fecha' => now()->toDateString(),
                'categoria' => 'Ahorro',
                'descripcion' => "Devolución por meta eliminada: " . $nombre,
                'metodo_pago' => 'efectivo',
            ]);
        }
        
        return response()->json(null, 204);
    }
}
