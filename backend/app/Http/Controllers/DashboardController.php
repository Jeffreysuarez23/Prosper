<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Movimiento;
use App\Models\Notificacion;
use App\Services\NotificationService;

class DashboardController extends Controller
{
    public function index(Request $request, NotificationService $notificationService)
    {
        $user = $request->user();
        
        // Auto-generate notifications
        $notificationService->generateForUser($user);
        
        $ingresosTotales = Movimiento::where('user_id', $user->id)
                            ->where('tipo', 'ingreso')
                            ->sum('monto');
                            
        $gastosTotales = Movimiento::where('user_id', $user->id)
                            ->where('tipo', 'gasto')
                            ->sum('monto');
                            
        $balanceGlobal = $ingresosTotales - $gastosTotales;
        
        $unreadNotifs = Notificacion::where('user_id', $user->id)
                            ->where('leida', false)
                            ->count();

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'tema_preferido' => $user->tema_preferido,
            ],
            'balance_global' => $balanceGlobal,
            'ingresos_totales' => $ingresosTotales,
            'gastos_totales' => $gastosTotales,
            'unread_notifications' => $unreadNotifs
        ]);
    }
}
