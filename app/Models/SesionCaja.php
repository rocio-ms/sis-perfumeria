<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class SesionCaja extends Model
{
    protected $table = 'sesion_cajas';

    protected $fillable = [
        'fecha_apertura',
        'fecha_cierre',
        'monto_apertura',
        'monto_cierre_teorico',
        'monto_cierre_real',
        'diferencia',
        'estado',
        'observaciones',
        'caja_id',
        'user_id',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'monto_apertura' => 'decimal:2',
        'monto_cierre_teorico' => 'decimal:2',
        'monto_cierre_real' => 'decimal:2',
        'diferencia' => 'decimal:2',
    ];

    public function caja() {
        return $this->belongsTo(Caja::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoCaja::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'sesion_caja_id');
    }
    
}
