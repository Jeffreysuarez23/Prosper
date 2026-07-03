<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'user_id',
        'tipo',
        'icono',
        'titulo',
        'mensaje',
        'categoria',
        'leida',
        'accion_texto',
        'accion_url'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
