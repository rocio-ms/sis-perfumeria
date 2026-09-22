<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model

{

    protected $table = 'kardexs';
    
    protected $fillable = [
        'fecha_movimiento',
        'observacion',
        'cantidad',
        'costo_unitario',
        'costo_total',
        'saldo_cantidad',
        'saldo_costo_unitario',
        'tipo_movimiento_id',
        'producto_id',
        'lote_id',
        'venta_id',
        'entrada_id',
        'salida_id',
        'user_id',
    ];

    protected $casts = [
        'fecha_movimiento' => 'datetime',
        'cantidad' => 'integer',
        'costo_unitario' => 'decimal:2',
        'costo_total' => 'decimal:2',
        'saldo_cantidad' => 'integer',
        'saldo_costo_unitario' => 'decimal:2',
    ];

    public function tipoMovimiento()
    {
        return $this->belongsTo(
            TipoMovimiento::class,
        );
    }

    public function producto()
    {
        return $this->belongsTo(
            Producto::class
        );
    }

    public function lote()
    {
        return $this->belongsTo(
            Lote::class
        );
    }

    public function venta()
    {
        return $this->belongsTo(
            Venta::class
        );
    }

    public function entrada()
    {
        return $this->belongsTo(Entrada::class);
    }

    public function salida()
    {
        return $this->belongsTo(Salida::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
