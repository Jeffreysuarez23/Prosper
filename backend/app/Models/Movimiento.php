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
}
