<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Salida extends Model
{
    protected $fillable = [
        'codigo_salida',
        'tipo_salida',
        'fecha_salida',
        'cliente_id', 
        'observacion',
        'total',
        'user_id',
        'venta_id'
    ];

    protected $casts = [
        'fecha_salida' => 'date',
        'total' => 'decimal:2',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cliente ()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles ()
    {
        return $this->hasMany(DetalleSalida::class);
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function kardexs()
    {
        return $this->hasMany(Kardex::class);
    }

}
