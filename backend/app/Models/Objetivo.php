<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Objetivo extends Model
{
    protected $fillable = [
        'user_id',
        'nombre',
        'monto_objetivo',
        'monto_actual',
        'fecha_limite',
        'icono'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
