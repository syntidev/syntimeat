<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DollarRateService;
use Illuminate\Console\Command;

class UpdateDollarRate extends Command
{
    protected $signature   = 'dollar:update';
    protected $description = 'Consulta APIs y actualiza la tasa USD→Bs en syntimeat_db';

    public function handle(DollarRateService $service): int
    {
        $this->info('[dollar:update] Consultando APIs...');

        $result = $service->fetchAndStore();

        if (! $result['success']) {
            $this->error('[dollar:update] FALLÓ: ' . ($result['message'] ?? 'error desconocido'));
            return self::FAILURE;
        }

        $this->info(sprintf(
            '[dollar:update] Tasa actualizada: %s Bs/USD (fuente: %s)',
            number_format((float) $result['rate'], 4, '.', ''),
            $result['source'] ?? 'desconocida',
        ));

        return self::SUCCESS;
    }
}
