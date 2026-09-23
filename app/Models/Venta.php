<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'sesion_caja_id',
        'user_id',
        'tipo_comprobante_id',
        'fecha_emision',
        'serie',
        'correlativo',
        'moneda',
        'forma_pago',
        'tipo_pago_id',
        'op_gravada',
        'op_exonerada',
        'op_inafecta',
        'op_gratuita',
        'igv',
        'descuento_total',
        'importe_total',
        'tienda_id',
        'grupo_venta',

        // Se mantiene por compatibilidad con tu BD.
        // Ya no determina la serie ni el emisor.
        'empresa_emisora_id',

        'serie_comprobante_id',
        'activo',
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',

        'op_gravada' => 'decimal:2',
        'op_exonerada' => 'decimal:2',
        'op_inafecta' => 'decimal:2',
        'op_gratuita' => 'decimal:2',
        'igv' => 'decimal:2',
        'descuento_total' => 'decimal:2',
        'importe_total' => 'decimal:2',

        'activo' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sesionCaja()
    {
        return $this->belongsTo(SesionCaja::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class);
    }

    public function tipoPago()
    {
        return $this->belongsTo(TipoPago::class);
    }

    /*
    |--------------------------------------------------------------------------
    | TIENDA EMISORA
    |--------------------------------------------------------------------------
    |
    | Essence Boutique es la tienda que emite las ventas.
    |
    */

    public function tienda()
    {
        return $this->belongsTo(Tienda::class);
    }

    /*
    |--------------------------------------------------------------------------
    | EMPRESA EMISORA
    |--------------------------------------------------------------------------
    |
    | Se mantiene esta relación por compatibilidad con la estructura actual.
    | Sin embargo, ya NO se utiliza para decidir la serie del comprobante.
    |
    | En la nueva lógica:
    |
    | Boleta  -> B001
    | Factura -> F001
    |
    | independientemente de la empresa/marca del producto vendido.
    |
    */

    public function empresaEmisora()
    {
        return $this->belongsTo(
            Empresa::class,
            'empresa_emisora_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SERIE DEL COMPROBANTE
    |--------------------------------------------------------------------------
    |
    | Esta relación es la importante para la numeración.
    |
    */

    public function serieComprobante()
    {
        return $this->belongsTo(
            SerieComprobante::class,
            'serie_comprobante_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | DETALLES
    |--------------------------------------------------------------------------
    */

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class);
    }

    public function comprobantes()
    {
        return $this->hasMany(self::class, 'grupo_venta', 'grupo_venta');
    }

    /*
    |--------------------------------------------------------------------------
    | EMISOR
    |--------------------------------------------------------------------------
    |
    | Ahora el emisor siempre será la TIENDA.
    |
    | No importa si los productos pertenecen a diferentes empresas
    | o marcas.
    |
    */

    public function getRucEmisorAttribute(): ?string
    {
        return $this->tienda?->ruc;
    }

    public function getRazonSocialEmisorAttribute(): ?string
    {
        return $this->tienda?->razon_social;
    }

    public function getNombreComercialEmisorAttribute(): ?string
    {
        return $this->tienda?->nombre_comercial
            ?? $this->tienda?->nombre;
    }

    /*
    |--------------------------------------------------------------------------
    | VENTA MIXTA
    |--------------------------------------------------------------------------
    |
    | Este concepto ya no aplica.
    |
    | Antes:
    |   empresa_emisora_id = NULL -> venta mixta
    |
    | Ahora:
    |   TODAS las ventas son emitidas por la tienda.
    |
    */

    public function getEsVentaMixtaAttribute(): bool
    {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | SALIDA
    |--------------------------------------------------------------------------
    */

    public function salida()
    {
        return $this->hasOne(Salida::class);
    }

    /*
    |--------------------------------------------------------------------------
    | KARDEX
    |--------------------------------------------------------------------------
    */

    public function kardexs()
    {
        return $this->hasMany(Kardex::class);
    }
}