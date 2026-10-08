<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'razon_social_nombre',
        'ap_paterno',
        'ap_materno',
        'nombres',
        'direccion',
        'telefono',
        'email',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $appends = [
        'nombre_completo',
    ];


    //Relaciones
    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getNombreCompletoAttribute()
    {
        if ($this->tipo_documento === 'RUC') {
            return $this->razon_social_nombre ?? '';
        }

        return trim(
            ($this->nombres ?? '') . ' ' .
                ($this->ap_paterno ?? '') . ' ' .
                ($this->ap_materno ?? '')
        );
    }
}
