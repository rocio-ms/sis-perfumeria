<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    protected $fillable = [
        'numero_lote',
        'fecha_ingreso',
        'fecha_vencimiento',
        'cantidad_inicial',
        'cantidad_actual',
        'entrada_id',
        'detalle_entrada_id',
    ];

    protected $casts = [
        'fecha_ingreso' => 'datetime',
        'fecha_vencimiento' => 'date',
    ];

    public function detalleEntrada()
    {
        return $this->belongsTo(DetalleEntrada::class);
    }

    public function detalleSalidas()
    {
        return $this->hasMany(DetalleSalida::class);
    }

    public function kardexs()
    {
        return $this->hasMany(Kardex::class);
    }
}
