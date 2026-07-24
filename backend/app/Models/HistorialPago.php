<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialPago extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'monto',
        'plan',
        'billing_cycle',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
