<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Movimiento;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function getStats()
    {
        $totalUsers = User::count();
        $totalMovimientos = Movimiento::count();

        // Get count of users by plan (active)
        $usersByPlan = DB::table('membresias')
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                      ->orWhere('ends_at', '>=', now());
            })
            ->select('plan', DB::raw('count(*) as total'))
            ->groupBy('plan')
            ->pluck('total', 'plan')->toArray();

        $ultraUsers = $usersByPlan['ultra'] ?? 0;
        $gratisUsers = $totalUsers - $ultraUsers;

        return response()->json([
            'total_users' => $totalUsers,
            'gratis_users' => $gratisUsers,
            'ultra_users' => $ultraUsers,
            'total_movimientos' => $totalMovimientos
        ]);
    }

    public function getUsers()
    {
        $users = User::with('membresia')->get();
        return response()->json($users);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:255',
            'role_id' => 'required|integer',
            'password' => 'nullable|string|min:8'
        ]);

        $user->name = $request->name;
        $user->telefono = $request->telefono;
        $user->role_id = $request->role_id;

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json(['message' => 'User updated successfully']);
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|integer',
            'telefono' => 'nullable|string|max:255',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->role_id = $request->role_id;
        $user->telefono = $request->telefono;
        $user->save();

        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent admin from deleting themselves
        if (auth()->id() == $id) {
            return response()->json(['error' => 'No puedes eliminar tu propia cuenta.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function auditUser($id)
    {
        $user = User::with('membresia')->findOrFail($id);
        
        // Get Movimientos and hide amounts
        $movimientos = \App\Models\Movimiento::where('user_id', $id)->orderBy('created_at', 'desc')->get()->map(function ($item) {
            $item->monto = '***';
            $item->descripcion = 'Privado';
            return $item;
        });

        // Get Objetivos and hide amounts
        $objetivos = \App\Models\Objetivo::where('user_id', $id)->get()->map(function ($item) {
            $item->monto_objetivo = '***';
            $item->monto_ahorrado = '***';
            return $item;
        });

        // Get Gastos Fijos and hide amounts
        $gastosFijos = \App\Models\GastoFijo::where('user_id', $id)->get()->map(function ($item) {
            $item->monto = '***';
            $item->monto_pagado_mes = '***';
            return $item;
        });

        // Get Tarjetas
        $tarjetas = \App\Models\TarjetaCredito::where('user_id', $id)->get()->map(function ($item) {
            $item->limite_credito = '***';
            $item->deuda_actual = '***';
            return $item;
        });

        return response()->json([
            'user' => $user,
            'movimientos' => $movimientos,
            'objetivos' => $objetivos,
            'gastos_fijos' => $gastosFijos,
            'tarjetas_credito' => $tarjetas
        ]);
    }
}

