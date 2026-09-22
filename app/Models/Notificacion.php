<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'tipo',
        'titulo',
        'mensaje',
        'referencia_tipo',
        'referencia_id',
        'leida',
        'leida_at',
        'solucionada',
        'solucionada_at',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'solucionada' => 'boolean',
        'leida_at' => 'datetime',
        'solucionada_at' => 'datetime',
    ];

    /**
     * Registro que originó la notificación.
     */
    public function referencia(): MorphTo
    {
        return $this->morphTo(
            null,
            'referencia_tipo',
            'referencia_id'
        );
    }
}