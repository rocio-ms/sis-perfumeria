<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $fillable = [
        'nombre',
        'empresa_id',
        'activo'
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function productos(){
        return $this->hasMany(Producto::class);
    }

    public function detallesVentas()
    {
        return $this->hasMany(DetalleVenta::class);
    }
}
