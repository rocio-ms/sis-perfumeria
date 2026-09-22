<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'tipo_documento',
        'n_documento',
        'nombre_contacto',
        'direccion',
        'telefono',
        'email',
        'activo'
    ];

    public function entradas()
    {
        return $this->hasMany(Entrada::class);
    }

    public function lotes()
    {
        return $this->hasMany(Lote::class);
    }
}
