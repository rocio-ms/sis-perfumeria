<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\SesionCaja;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = [
        'nombre',
        'activo',
        'turno'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function sesiones() {
         return $this->hasMany(SesionCaja::class);
    }

    public function sesionAbierta()
    {
        return $this->hasOne(SesionCaja::class)
            ->where('estado', 'abierta')
            ->latestOfMany();
    }
}
