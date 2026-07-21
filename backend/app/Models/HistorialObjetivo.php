<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialObjetivo extends Model
{
    protected $table = 'historial_objetivos';

    protected $fillable = [
        'objetivo_id',
        'user_id',
        'tipo',
        'monto',
        'fecha',
    ];

    public function objetivo()
    {
        return $this->belongsTo(Objetivo::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
