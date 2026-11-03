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

        // La empresa emisora determina la serie y el emisor del comprobante.
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
    | La empresa emisora se obtiene de las marcas de los productos vendidos.
    | Las ventas con productos de varias empresas se separan por empresa.
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
    | Las nuevas ventas tienen una empresa emisora. Las ventas mixtas
    | existentes pueden no tenerla.
    |
    */

    public function getRucEmisorAttribute(): ?string
    {
        return $this->empresaEmisora?->ruc;
    }

    public function getRazonSocialEmisorAttribute(): ?string
    {
        return $this->empresaEmisora?->razon_social;
    }

    public function getNombreComercialEmisorAttribute(): ?string
    {
        return $this->empresaEmisora?->razon_social
            ?? $this->tienda?->nombre_comercial;
    }

    /*
    |--------------------------------------------------------------------------
    | VENTA MIXTA
    |--------------------------------------------------------------------------
    |
    | Las ventas nuevas se separan por empresa emisora. Este indicador
    | identifica registros anteriores sin empresa emisora asignada.
    |
    */

    public function getEsVentaMixtaAttribute(): bool
    {
        return is_null($this->empresa_emisora_id);
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