<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleEntrada extends Model
{
    protected $fillable = [
        'entrada_id',
        'producto_id',
        'cantidad',
        'precio_compra',
        'subtotal',
        'precio_venta',
        'tiene_descuento',
        'descuento'
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'costo_unitario' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'tiene_descuento' => 'boolean',
        'descuento' => 'decimal:2',
    ];

    public function entrada()
    {
        return $this->belongsTo(Entrada::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function lotes()
    {
        return $this->hasMany(
            Lote::class,
            'detalle_entrada_id'
        );
    }

    public function getPrecioFinalAttribute()
    {
        if (!$this->tiene_descuento) {
            return $this->precio_venta;
        }

        return max(
            0,
            $this->precio_venta - $this->descuento
        );
    }
}
