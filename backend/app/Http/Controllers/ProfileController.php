<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function updateProfile(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'apellido' => 'required|string|max:255',
            'telefono' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        $user->name = $request->nombre . ' ' . $request->apellido;
        $user->telefono = $request->telefono;
        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'contrasena_actual' => 'required|string',
            'nueva_contrasena' => 'required|string|min:8|different:contrasena_actual',
        ]);

        $user = $request->user();

        if (!Hash::check($request->contrasena_actual, $user->password)) {
            throw ValidationException::withMessages([
                'contrasena_actual' => ['La contraseña actual es incorrecta.'],
            ]);
        }

        $user->password = Hash::make($request->nueva_contrasena);
        $user->save();

        // Optional: Logout other devices, or just return success
        // The frontend will handle logging out the user locally and redirecting.
        $user->tokens()->delete(); // Revoke all tokens so the user is forced to login again everywhere

        return response()->json([
            'message' => 'Contraseña actualizada correctamente'
        ]);
    }
}
