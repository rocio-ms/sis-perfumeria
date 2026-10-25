<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\PrecioProducto;

class Producto extends Model
{
    public const UBICACIONES = [
        'Vitrina Grande 1° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
        'Mostrador ubicado en el interior de la Tienda Lado Sur',
        'Mostrador de madera ubicado en la pared interior de la Tienda Lado Sur',
        'Encima de la Vitrina Mediana Ubicada en el Interior de la Tienda Lado Sur',
        'Encima de Vitrina Grande ubicado al Interior de la Tienda Lado Este',
        'Encima de Octogonal ubicado al Interior de la Tienda Lado Este',
        'Mostrador Metalico Ubicado en la pared interior de la Tienda Lado Este',
        'Vitrina Grande 5° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
        'Exhibidor Metalico ubicado en el interior de la Tienda Lado Norte (A la entrada)',
        'Exhibidor Metalico ubicado en el interior de la Tienda Lado Norte (A la entrada N° 02)',
        'Andamio Metalico Dexion lado Este de la Tienda',
        'Vitrina Grande 4° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
        'Caja Antigua ubicada en el interior de la Tienda Lado Este',
        'Vitrina Grande 6° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
        'Vitrina Grande 2° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
        'Octogonal ubicado en el interior de la Tienda',
        'Mostrador mediano ubicado en el interior de la Tienda Lado Sur',
        'Vitrina grande ubicada en el interior de la Tienda Lado Este',
        'Vitrina grande ubicada al interior de la Tienda Lado Oeste',
        'Mostrador ubicado en el interior de la Tienda Lado Este',
        'Vitrina Grande Ubicado al Interior exterior de la Tienda Lado Oeste',
        'Vitrina Grande 3° compartimiento ubicado en el lado Oeste de la Tienda (hacia afuera)',
        'Vitrina Mediana Lado Sur de la Tienda',
        'Armado Metalico Ranurado Dexion ubicado en el fondo de la Tienda lado Este',
        'Exhibidor Metalico Circular ubicado en el interior de la Tienda Lado Sur',
    ];

    protected $fillable = [
        'codigo',
        'codigo_barras',
        'nombre',
        'descripcion',
        'ubicacion',
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
