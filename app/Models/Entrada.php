<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Entrada extends Model
{
    protected $fillable = [
        'codigo_ingreso',
        'tipo_comprobante',
        'numero_comprobante',
        'fecha_ingreso',
        'observacion',
        'total_compra',
        'proveedor_id',
        'user_id'
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'total_compra' => 'decimal:2',
    ];

    public function detalles()
    {
        return $this->hasMany(DetalleEntrada::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lotes()
    {
        return $this->hasMany(Lote::class);
    }

    public function kardexs()
    {
        return $this->hasMany(Kardex::class);
    }
}
