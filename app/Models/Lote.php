<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Lote extends Model
{
    public function scopeDisponibleParaVenta(Builder $query): Builder
    {
        return $query->where('lotes.cantidad_actual', '>=', 1)
            ->where(fn (Builder $lotes) => $lotes
                ->whereNull('lotes.fecha_vencimiento')
                ->orWhere('lotes.fecha_vencimiento', '>=', now('America/Lima')->toDateString()));
    }

    protected $fillable = [
        'numero_lote',
        'codigo_barras',
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
