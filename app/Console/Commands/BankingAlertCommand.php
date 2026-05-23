<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BankingAlertCommand extends Command
{
    protected $signature   = 'cash:banking-alert {--minutes=0 : Minutos restantes para el corte bancario (20, 10 o 0)}';
    protected $description = 'Guarda una alerta de corte bancario en caché para mostrar en el dashboard';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');

        $message = match ($minutes) {
            20 => 'Faltan 20 minutos para el corte bancario (7:00 PM). Prepárese.',
            10 => '¡10 minutos para el corte bancario! Finalice las ventas pendientes.',
            0  => '¡CORTE BANCARIO AHORA! Las ventas a partir de este momento se registran en el día de mañana.',
            default => null,
        };

        if ($message === null) {
            $this->error("Valor --minutes inválido: {$minutes}. Use 20, 10 o 0.");
            return self::FAILURE;
        }

        // TTL: 2 horas — suficiente para cubrir la noche, la última alerta vence ~21:00
        Cache::put('banking_alert', $message, now()->addHours(2));

        $this->info("Alerta guardada (--minutes={$minutes}): {$message}");
        return self::SUCCESS;
    }
}
