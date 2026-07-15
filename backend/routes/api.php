<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\ObjetivoController;
use App\Http\Controllers\GastoFijoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\TarjetaCreditoController;
use App\Http\Controllers\PayPalController;

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [\App\Http\Controllers\ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword']);
    
    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    Route::apiResource('movimientos', MovimientoController::class);
    Route::apiResource('objetivos', ObjetivoController::class);
    Route::apiResource('gastos-fijos', GastoFijoController::class);
    Route::post('gastos-fijos/{gastoFijo}/abono', [GastoFijoController::class, 'payPartial']);
    
    Route::apiResource('tarjetas-credito', TarjetaCreditoController::class);
    Route::post('tarjetas-credito/{tarjetaCredito}/pago', [TarjetaCreditoController::class, 'pagarTarjeta']);
    Route::post('tarjetas-credito/{tarjetaCredito}/deuda', [TarjetaCreditoController::class, 'agregarDeuda']);
    
    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::put('/notificaciones/read-all', [NotificacionController::class, 'markAllAsRead']);
    Route::put('/notificaciones/{id}/read', [NotificacionController::class, 'markAsRead']);
    Route::delete('/notificaciones/{id}', [NotificacionController::class, 'destroy']);
    Route::delete('/notificaciones', [NotificacionController::class, 'destroyAll']);
    
    // PayPal Routes
    Route::get('/paypal/client-id', [PayPalController::class, 'getClientId']);
    Route::post('/paypal/create-order', [PayPalController::class, 'createOrder']);
    Route::post('/paypal/capture-order', [PayPalController::class, 'captureOrder']);

    // Admin Routes
    Route::middleware([\App\Http\Middleware\IsAdmin::class])->group(function () {
        Route::get('/admin/stats', [\App\Http\Controllers\AdminController::class, 'getStats']);
        Route::get('/admin/users', [\App\Http\Controllers\AdminController::class, 'getUsers']);
        Route::post('/admin/users', [\App\Http\Controllers\AdminController::class, 'createUser']);
        Route::put('/admin/users/{id}', [\App\Http\Controllers\AdminController::class, 'updateUser']);
        Route::delete('/admin/users/{id}', [\App\Http\Controllers\AdminController::class, 'deleteUser']);
        Route::get('/admin/users/{id}/audit', [\App\Http\Controllers\AdminController::class, 'auditUser']);
    });
});



