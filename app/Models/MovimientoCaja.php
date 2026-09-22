<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    protected $table = 'movimiento_cajas';

    protected $fillable = [
        'tipo',
        'concepto',
        'monto',
        'sesion_caja_id',
        'user_id'
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
