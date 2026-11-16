<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoComprobante extends Model
{
    public const NOTA_VENTA = 'Nota de venta';

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function seriesComprobantes()
    {
        return $this->hasMany(
            SerieComprobante::class,
            'tipo_comprobante_id'
        );
    }

    public function ventas()
    {
        return $this->hasMany(
            Venta::class,
            'tipo_comprobante_id'
        );
    }
}
