<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class CalendarioComercial
{
    public const ZONA = 'America/Lima';

    public static function fechaLocalDeVentaSql(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "DATE(fecha_emision, '-5 hours')"
            : 'DATE(DATE_SUB(fecha_emision, INTERVAL 5 HOUR))';
    }
}
