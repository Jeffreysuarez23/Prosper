<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Movimiento;
use App\Models\Notificacion;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function index(Request $request, NotificationService $notificationService)
    {
        $user = $request->user();
        
        // Auto-generate notifications
        $notificationService->generateForUser($user);
        
        $totals = Movimiento::where('user_id', $user->id)
            ->selectRaw("
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END) as gastos
            ")->first();
            
        $ingresosTotales = (float) ($totals->ingresos ?? 0);
        $gastosTotales = (float) ($totals->gastos ?? 0);
        $balanceGlobal = $ingresosTotales - $gastosTotales;
        
        $unreadNotifs = Notificacion::where('user_id', $user->id)
                            ->where('leida', false)
                            ->count();

        $currentMonth = date('m');
        $currentYear = date('Y');

        $monthlyTotals = Movimiento::where('user_id', $user->id)
            ->whereMonth('fecha', $currentMonth)
            ->whereYear('fecha', $currentYear)
            ->selectRaw("
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                SUM(CASE WHEN tipo = 'gasto' THEN monto ELSE 0 END) as gastos
            ")->first();

        $balanceMensual = (float) ($monthlyTotals->ingresos ?? 0) - (float) ($monthlyTotals->gastos ?? 0);

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'tema_preferido' => $user->tema_preferido,
            ],
            'balance_global' => $balanceGlobal,
            'balance_mensual' => $balanceMensual,
            'ingresos_totales' => $ingresosTotales,
            'gastos_totales' => $gastosTotales,
            'unread_notifications' => $unreadNotifs
        ]);
    }
}
