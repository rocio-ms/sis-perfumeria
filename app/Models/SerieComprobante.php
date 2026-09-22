<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SerieComprobante extends Model
{
    protected $table = 'serie_comprobantes';

    protected $fillable = [
        'tienda_id',
        'empresa_id',
        'tipo_comprobante_id',
        'serie',
        'correlativo_actual',
        'activo',
    ];

    protected $casts = [
        'correlativo_actual' => 'integer',
        'activo' => 'boolean',
    ];

    public function tienda()
    {
        return $this->belongsTo(Tienda::class);
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoComprobante()
    {
        return $this->belongsTo(TipoComprobante::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function getEsUnificadaAttribute(): bool
    {
        return is_null($this->empresa_id);
    }
}
