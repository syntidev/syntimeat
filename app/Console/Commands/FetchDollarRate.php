<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DollarRateService;
use Illuminate\Console\Command;

class FetchDollarRate extends Command
{
    protected $signature   = 'dollar:fetch';
    protected $description = 'Actualiza la tasa del dólar BCV desde APIs externas';

    public function __construct(private readonly DollarRateService $rates)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->rates->fetchAndStore();

        if ($result['success']) {
            $this->info("[dollar:fetch] Tasa actualizada: {$result['rate']} Bs/USD (fuente: {$result['source']})");
            return Command::SUCCESS;
        }

        $this->error("[dollar:fetch] Fallo: {$result['message']}");
        return Command::FAILURE;
    }
}
