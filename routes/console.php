<?php

use App\Services\NotificacionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notificaciones:sincronizar', function (NotificacionService $servicio) {
    $servicio->sincronizar();
})->purpose('Sincronizar alertas de stock y vencimientos');

Schedule::command('notificaciones:sincronizar')->everyMinute()->withoutOverlapping(10);
