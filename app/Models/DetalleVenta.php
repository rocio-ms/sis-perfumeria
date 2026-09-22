<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'producto_id',
        'lote_id',
        'empresa_id',
        'marca_id',
        'cantidad',
        'unidad_medida',
        'precio_unitario',
        'descuento',
        'igv',
        'subtotal',
        'descripcion',
    ];


    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'igv' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function lote()
    {
        return $this->belongsTo(Lote::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }
}
