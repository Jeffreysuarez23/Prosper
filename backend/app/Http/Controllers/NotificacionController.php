<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->notificaciones()->orderBy('leida', 'asc')->orderBy('created_at', 'desc')->take(5)->get());
    }

    public function markAsRead(Request $request, $id)
    {
        $notificacion = $request->user()->notificaciones()->findOrFail($id);
        $notificacion->update(['leida' => true]);
        return response()->json($notificacion);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->notificaciones()->where('leida', false)->update(['leida' => true]);
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, $id)
    {
        $notificacion = $request->user()->notificaciones()->findOrFail($id);
        $notificacion->delete();
        return response()->json(null, 204);
    }

    public function destroyAll(Request $request)
    {
        $request->user()->notificaciones()->delete();
        return response()->json(null, 204);
    }
}
