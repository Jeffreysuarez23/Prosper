<?php

namespace App\Http\Controllers;

use App\Models\Movimiento;
use Illuminate\Http\Request;

class MovimientoController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->movimientos()->orderBy('fecha', 'desc')->orderBy('id', 'desc');

        if ($request->has('month') && $request->month !== 'all') {
            $query->whereRaw("TO_CHAR(fecha, 'YYYY-MM') = ?", [$request->month]);
        }
        
        if ($request->filled('q')) {
            $query->where(function($q) use ($request) {
                $q->where('descripcion', 'LIKE', '%' . $request->q . '%')
                  ->orWhere('categoria', 'LIKE', '%' . $request->q . '%');
            });
        }
        
        if ($request->filled('type')) {
            $query->where('tipo', $request->type);
        }
        
        if ($request->filled('cat')) {
            $query->where('categoria', $request->cat);
        }

        if ($request->boolean('paginate')) {
            $statsQuery = clone $query;
            $stats = $statsQuery->get();
            $income = $stats->where('tipo', 'ingreso')->sum('monto');
            $expense = $stats->where('tipo', 'gasto')->sum('monto');
            $balance = $income - $expense;

            $movimientos = $query->paginate(5);
            return response()->json([
                'movimientos' => $movimientos,
                'stats' => [
                    'income' => $income,
                    'expense' => $expense,
                    'balance' => $balance
                ]
            ]);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => 'required|in:ingreso,gasto,ahorro',
            'monto' => 'required|numeric|min:0',
            'fecha' => 'required|date',
            'categoria' => 'required|string|max:50',
            'descripcion' => 'nullable|string|max:255',
            'metodo_pago' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $plan = $user->plan;

        if ($plan === 'gratis') {
            $count = $user->movimientos()->count();
            if ($count >= 15) {
                return response()->json(['message' => 'Has alcanzado el límite de 15 movimientos para el plan Gratis.'], 403);
            }
        }

        $movimiento = $request->user()->movimientos()->create($validated);
        return response()->json($movimiento, 201);
    }

    public function show(Movimiento $movimiento)
    {
        return response()->json($movimiento);
    }

    public function update(Request $request, Movimiento $movimiento)
    {
        $validated = $request->validate([
            'tipo' => 'sometimes|required|in:ingreso,gasto,ahorro',
            'monto' => 'sometimes|required|numeric|min:0',
            'fecha' => 'sometimes|required|date',
            'categoria' => 'sometimes|required|string|max:50',
            'descripcion' => 'nullable|string|max:255',
            'metodo_pago' => 'nullable|string|max:50',
        ]);

        $movimiento->update($validated);
        return response()->json($movimiento);
    }

    public function destroy(Movimiento $movimiento)
    {
        $movimiento->delete();
        return response()->json(null, 204);
    }
}
