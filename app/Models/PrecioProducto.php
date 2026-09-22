<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrecioProducto extends Model
{
    protected $table = 'precios_productos';

    protected $fillable = [
        'producto_id',
        'precio_venta',
        'tiene_descuento',
        'descuento',
        'activo',
    ];

    protected $casts = [
        'precio_venta' => 'decimal:2',
        'descuento' => 'decimal:2',
        'tiene_descuento' => 'boolean',
        'activo' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function getPrecioFinalAttribute(): float
    {
        $precio = (float) $this->precio_venta;

        if (!$this->tiene_descuento) {
            return $precio;
        }

        return max(
            0,
            $precio - (float) $this->descuento
        );
    }
}