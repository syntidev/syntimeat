<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Actualiza la tasa USD→Bs cada hora
Schedule::command('dollar:update')->hourly();

// Alertas de corte bancario — 6:40 pm, 6:50 pm y 7:00 pm (hora Venezuela)
Schedule::command('cash:banking-alert --minutes=20')->dailyAt('18:40')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=10')->dailyAt('18:50')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=0')->dailyAt('19:00')->timezone('America/Caracas');

Schedule::command('dollar:fetch')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
