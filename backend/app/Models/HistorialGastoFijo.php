<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialGastoFijo extends Model
{
    protected $table = 'historial_gastos_fijos';

    protected $fillable = [
        'gasto_fijo_id',
        'user_id',
        'tipo',
        'monto',
        'fecha',
    ];

    public function gastoFijo()
    {
        return $this->belongsTo(GastoFijo::class, 'gasto_fijo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
