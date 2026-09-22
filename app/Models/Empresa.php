<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $fillable = [
        'tienda_id',
        'ruc',
        'razon_social',
        'nombre_comercial',
        'direccion',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tienda()
    {
        return $this->belongsTo(Tienda::class);
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function detallesVentas()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function ventasEmitidas()
    {
        return $this->hasMany(
            Venta::class,
            'empresa_emisora_id'
        );
    }

    public function seriesComprobantes()
    {
        return $this->hasMany(SerieComprobante::class);
    }
}
