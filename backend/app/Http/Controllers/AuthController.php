<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Membresia;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->telefono = $request->telefono;
            $user->password = Hash::make($request->password);
            $user->role_id = 2; // Default user role
            $user->tema_preferido = 'light';
            $user->save();

            Membresia::create([
                'user_id' => $user->id,
                'plan' => 'gratis',
                'status' => 'active'
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            \Illuminate\Support\Facades\DB::commit();

            $user->load('membresia');

            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'token' => $token,
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'message' => 'Error interno al registrar. Por favor, intenta de nuevo.'
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $user->load('membresia');

        return response()->json([
            'token' => $user->createToken('auth_token')->plainTextToken,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $user->load('membresia');
        return response()->json($user);
    }
}
