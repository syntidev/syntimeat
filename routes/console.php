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

// Alertas de corte bancario — 7:00 pm, 7:10 pm, 7:20 pm y 7:30 pm (hora Venezuela)
Schedule::command('cash:banking-alert --minutes=30')->dailyAt('19:00')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=20')->dailyAt('19:10')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=10')->dailyAt('19:20')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=0')->dailyAt('19:30')->timezone('America/Caracas');

Schedule::command('dollar:fetch')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
