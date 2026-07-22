<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarjetaCredito extends Model
{
    protected $table = 'tarjeta_creditos';

    protected $fillable = [
        'user_id',
        'nombre',
        'banco',
        'ultimos_digitos',
        'limite_credito',
        'deuda_actual',
        'dia_corte',
        'dia_pago',
        'tasa_interes',
        'icono',
        'color',
        'fecha_ultimo_interes'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function compras()
    {
        return $this->hasMany(CompraTarjeta::class, 'tarjeta_credito_id');
    }

    public function historial()
    {
        return $this->hasMany(HistorialTarjetaCredito::class, 'tarjeta_credito_id');
    }
}
