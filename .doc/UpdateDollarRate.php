<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DollarRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateDollarRate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dollar:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and propagate current dollar rate from DolarAPI';

    /**
     * The dollar rate service instance.
     */
    private DollarRateService $dollarRateService;

    /**
     * Create a new command instance.
     */
    public function __construct(DollarRateService $dollarRateService)
    {
        parent::__construct();
        $this->dollarRateService = $dollarRateService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // ── USD ──────────────────────────────────────────────────
        $this->info('🔄 Fetching USD rate...');
        $usd = $this->dollarRateService->fetchAndPropagate();

        if ($usd['success']) {
            $this->info("✅ USD updated: Bs. {$usd['rate']}");
            $this->info("📊 Propagated to {$usd['updated_tenants']} tenants");
        } else {
            $this->error("❌ USD failed: {$usd['message']}");
        }

        // ── EUR ──────────────────────────────────────────────────
        $this->info('🔄 Fetching EUR rate...');
        $eur = $this->dollarRateService->fetchAndStoreEuro();

        if ($eur['success']) {
            $this->info("✅ EUR updated: Bs. {$eur['rate']} (source: {$eur['source']})");
            $propagated = $this->dollarRateService->propagateEuroRateToTenants($eur['rate']);
            $this->info("📊 EUR propagated to {$propagated['updated_count']} tenants");
        } else {
            $this->error("❌ EUR failed: {$eur['message']}");
        }

        // Alerta si la tasa lleva más de 4 horas sin actualizarse
        if ($this->dollarRateService->isStale(4)) {
            Log::error('[CurrencyAlert] Tasa USD no se actualiza hace más de 4 horas');
            try {
                \Illuminate\Support\Facades\Mail::raw(
                    'ALERTA SYNTIweb: La tasa del dólar no se ha actualizado en más de 4 horas. Verifica las APIs o activa la tasa manual en el panel de administración.',
                    function ($m) {
                        $m->to(config('mail.from.address'))
                          ->subject('[ALERTA] Tasa BCV sin actualizar — SYNTIweb');
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('[CurrencyAlert] No se pudo enviar email de alerta: ' . $e->getMessage());
            }
        }

        return Command::SUCCESS;
    }
}
