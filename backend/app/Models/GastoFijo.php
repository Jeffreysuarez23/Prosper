<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GastoFijo extends Model
{
    protected $table = 'gasto_fijos';

    protected $fillable = [
        'user_id',
        'nombre',
        'monto',
        'monto_pagado_mes',
        'dia_vencimiento',
        'icono',
        'fecha_ultimo_pago'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function historial()
    {
        return $this->hasMany(HistorialGastoFijo::class, 'gasto_fijo_id');
    }
}
