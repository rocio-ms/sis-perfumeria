<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PrecioProducto;

class Producto extends Model
{
    protected $fillable = [
        'codigo',
        'codigo_barras',
        'nombre',
        'descripcion',
        'unidad_medida',
        'stock_actual',
        'stock_minimo',
        'activo',
        'volumen_ml',
        'imagen_url',
        'categoria_id',
        'subcategoria_id',
        'marca_id',
        'empresa_id',
    ];

     protected $casts = [
        'stock_minimo' => 'integer',
        'stock_actual' => 'integer',
        'volumen_ml' => 'integer',
        'activo' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function subcategoria()
    {
        return $this->belongsTo(Subcategoria::class);
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function precioActual()
    {
        return $this->hasOne(PrecioProducto::class)
            ->where('activo', true)
            ->latestOfMany();
    }

    public function detallesEntradas()
    {
        return $this->hasMany(
            DetalleEntrada::class,
            'producto_id'
        );
    }

    public function lotes()
    {
        return $this->hasManyThrough(
            Lote::class,
            DetalleEntrada::class,
            'producto_id',
            'detalle_entrada_id',
            'id',                // PK de productos
            'id'                 // PK de detalle_entradas
        );
    }

    public function detalleSalidas()
    {
        return $this->hasMany(DetalleSalida::class);
    }

    public function kardexs()
    {
        return $this->hasMany(Kardex::class);
    }

    public function getPrecioFinalAttribute()
    {
        $precio = $this->precioActual;

        if (!$precio) {
            return null;
        }

        $precioVenta = (float) $precio->precio_venta;

        if (!$precio->tiene_descuento) {
            return $precioVenta;
        }

        $descuento = (float) ($precio->descuento ?? 0);

        return max(
            0,
            $precioVenta - $descuento
        );
    }
}
