<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tienda extends Model
{
    protected $table = 'tiendas';

    protected $fillable = [
        'nombre_comercial',
        'direccion',
        'departamento',
        'provincia',
        'distrito',
        'telefono',
        'email',
        'logo_url',
    ];

    public function empresas()
    {
        return $this->hasMany(Empresa::class);
    }

    public function productos ()
    {
        return $this->hasManyThrough(Producto::class, Empresa::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function seriesComprobantes()
    {
        return $this->hasMany(SerieComprobante::class);
    }

    public function seriesUnificadas()
    {
        return $this->hasMany(SerieComprobante::class)
            ->whereNull('empresa_id');
    }

     public function sesionesCaja()
    {
        return $this->hasMany(SesionCaja::class);
    }

}
