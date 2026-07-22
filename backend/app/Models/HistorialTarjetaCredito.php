<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialTarjetaCredito extends Model
{
    protected $table = 'historial_tarjeta_creditos';

    protected $fillable = [
        'tarjeta_credito_id',
        'user_id',
        'compra_tarjeta_id',
        'tipo',
        'monto',
        'descripcion',
        'fecha',
    ];

    public function tarjetaCredito()
    {
        return $this->belongsTo(TarjetaCredito::class);
    }

    public function compraTarjeta()
    {
        return $this->belongsTo(CompraTarjeta::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
