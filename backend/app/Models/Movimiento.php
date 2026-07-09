<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movimiento extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'monto',
        'fecha',
        'categoria',
        'descripcion',
        'metodo_pago'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::creating(function ($movimiento) {
            $user = $movimiento->user;
            if ($user && $user->plan === 'gratis') {
                if ($user->movimientos()->count() >= 15) {
                    abort(403, 'Límite alcanzado: El plan Gratis permite máximo 15 movimientos.');
                }
            }
        });
    }
}
