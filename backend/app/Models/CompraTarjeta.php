<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraTarjeta extends Model
{
    protected $table = 'compra_tarjeta_creditos';

    protected $fillable = [
        'tarjeta_credito_id',
        'user_id',
        'descripcion',
        'monto',
        'monto_pagado',
        'estado',
        'fecha',
    ];

    public function tarjetaCredito()
    {
        return $this->belongsTo(TarjetaCredito::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function historial()
    {
        return $this->hasMany(HistorialTarjetaCredito::class, 'compra_tarjeta_id');
    }
}
