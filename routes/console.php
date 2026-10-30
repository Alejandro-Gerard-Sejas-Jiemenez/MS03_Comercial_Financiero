<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Disparadores JOB de los casos de uso (CASOS_DE_USO.md). El relay del Outbox corre cada minuto.
Schedule::command('outbox:publicar')->everyMinute()->withoutOverlapping();
Schedule::command('promociones:vencer')->dailyAt('00:05');
Schedule::command('acuerdos:finalizar-vencidos')->dailyAt('00:10');
Schedule::command('pagos:expirar-pendientes')->hourly();
Schedule::command('conciliacion:ejecutar')->dailyAt('02:00');
Schedule::command('liquidaciones:generar')->dailyAt('03:00');
