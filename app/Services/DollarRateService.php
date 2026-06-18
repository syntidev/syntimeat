<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DollarRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DollarRateService — SYNTImeat
 *
 * Lee y almacena tasas USD→Bs en syntimeat_db.dollar_rates.
 * Autónomo: no depende de synticorex.
 * NUNCA lanza excepciones. Siempre retorna un float válido.
 *
 * Columnas de dollar_rates:
 *   rate           decimal(12,4)
 *   source         bcv | parallel | negotiated | manual
 *   currency_type  USD | EUR
 *   effective_from datetime
 *   effective_until datetime|null
 *   is_active      boolean
 *   created_at     timestamp (sin updated_at)
 */
class DollarRateService
{
    private const CACHE_KEY           = 'syntimeat_dollar_rate_usd';
    private const MANUAL_OVERRIDE_KEY = 'syntimeat_manual_rate_override';
    private const CACHE_TTL           = 3600;   // 1 hora
    private const MAX_CHANGE_PCT      = 60.0;   // % máximo de variación diaria aceptada (VES es volátil)
    private const FALLBACK            = 40.00;

    /**
     * Desactiva el switch manual — el cron BCV retoma el control.
     */
    public function releaseManualRate(): void
    {
        Cache::forget(self::MANUAL_OVERRIDE_KEY);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Estado del switch manual.
     */
    public function isManualOverrideActive(): bool
    {
        return (bool) Cache::get(self::MANUAL_OVERRIDE_KEY, false);
    }

    public function __construct(
        private readonly CurrencyFetcherService $fetcher
    ) {}

    // ─── Lectura ──────────────────────────────────────────────────────────────

    /**
     * Tasa del día para la fuente indicada.
     * Fallback: última tasa activa si no hay registro de hoy.
     * NUNCA lanza excepción.
     */
    public function getTodayRate(string $source = 'bcv'): float
    {
        $source = $this->sanitizeSource($source);

        try {
            $rate = DollarRate::query()
                ->where('currency_type', 'USD')
                ->where('source', $source)
                ->where('is_active', true)
                ->whereDate('effective_from', now()->toDateString())
                ->orderByDesc('effective_from')
                ->value('rate');

            if ($rate !== null) {
                return round((float) $rate, 4);
            }

            Log::info('DollarRateService: Sin tasa del día, usando última disponible', [
                'source' => $source,
            ]);

            return $this->getLatestRate($source);
        } catch (Throwable $e) {
            Log::warning('DollarRateService::getTodayRate falló', ['error' => $e->getMessage()]);
            return $this->envFallback();
        }
    }

    /**
     * Última tasa activa sin importar la fecha.
     * NUNCA lanza excepción.
     */
    public function getLatestRate(string $source = 'bcv'): float
    {
        $source = $this->sanitizeSource($source);

        try {
            $rate = DollarRate::query()
                ->where('currency_type', 'USD')
                ->where('source', $source)
                ->where('is_active', true)
                ->orderByDesc('effective_from')
                ->value('rate');

            if ($rate !== null) {
                return round((float) $rate, 4);
            }

            Log::warning('DollarRateService::getLatestRate sin registros activos', [
                'source' => $source,
            ]);

            return $this->envFallback();
        } catch (Throwable $e) {
            Log::warning('DollarRateService::getLatestRate falló', ['error' => $e->getMessage()]);
            return $this->envFallback();
        }
    }

    // ─── Escritura (llamada desde el command) ─────────────────────────────────

    /**
     * Consulta las APIs y almacena la nueva tasa USD en DB.
     *
     * @return array{success: bool, rate?: float, source?: string, message: string}
     */
    public function fetchAndStore(): array
    {
        $fetched = $this->fetcher->fetchUSD();

        if (! $fetched['success']) {
            Log::error('DollarRateService: Todas las fuentes USD fallaron');
            return ['success' => false, 'message' => 'Todas las fuentes fallaron.'];
        }

        $newRate    = (float) $fetched['rate'];
        $sourceName = $fetched['source'];

        // Validar cambio excesivo — solo si ya hay un registro real en DB
        $previousRecord = DollarRate::query()
            ->where('currency_type', 'USD')
            ->where('source', $sourceName)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->value('rate');

        if ($previousRecord !== null) {
            $previous = (float) $previousRecord;

            // Saltar validación si el último registro tiene más de 48h (datos muy desactualizados)
            $lastDate = DollarRate::query()
                ->where('currency_type', 'USD')
                ->where('source', $sourceName)
                ->where('is_active', true)
                ->orderByDesc('effective_from')
                ->value('effective_from');

            $isStale = $lastDate !== null && Carbon::parse($lastDate)->diffInHours(Carbon::now()) > 48;

            if (! $isStale) {
                $changePct = $previous > 0
                    ? abs(($newRate - $previous) / $previous) * 100
                    : 0;

                if ($changePct > self::MAX_CHANGE_PCT) {
                    Log::critical('DollarRateService: Variación sospechosa rechazada', [
                        'anterior' => $previous,
                        'nueva'    => $newRate,
                        'pct'      => round($changePct, 2),
                    ]);
                    return ['success' => false, 'message' => 'Tasa rechazada por variación sospechosa (>' . self::MAX_CHANGE_PCT . '%). Usa tasa manual si es correcto.'];
                }
            } else {
                Log::info('DollarRateService: Datos >48h desactualizados — omitiendo validación de variación', [
                    'anterior' => $previous,
                    'nueva'    => $newRate,
                ]);
            }
        }

        try {
            // Si el switch manual está activo, guardar la tasa BCV pero no activarla
            $manualOverride = Cache::get(self::MANUAL_OVERRIDE_KEY, false);

            // Desactivar tasas anteriores activas de esta fuente
            DollarRate::query()
                ->where('currency_type', 'USD')
                ->where('source', $sourceName)
                ->where('is_active', true)
                ->update(['is_active' => false, 'effective_until' => Carbon::now()]);

            // Insertar nueva tasa — inactiva si el switch manual está ON
            DollarRate::create([
                'rate'            => $newRate,
                'source'          => $sourceName,
                'currency_type'   => 'USD',
                'effective_from'  => Carbon::now(),
                'effective_until' => null,
                'is_active'       => ! $manualOverride,
            ]);

            Cache::forget(self::CACHE_KEY);

            Log::info('DollarRateService: USD almacenada', [
                'rate'   => $newRate,
                'source' => $sourceName,
            ]);

            return ['success' => true, 'rate' => $newRate, 'source' => $sourceName, 'message' => 'OK'];
        } catch (Throwable $e) {
            Log::error('DollarRateService: Error al persistir tasa USD', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error DB: ' . $e->getMessage()];
        }
    }

    /**
     * Sobreescribe la tasa con un valor manual ingresado por el admin.
     * Desactiva cualquier tasa manual previa y crea un registro nuevo.
     */
    public function storeManualRate(float $rate): bool
    {
        try {
            DollarRate::query()
                ->where('currency_type', 'USD')
                ->where('source', 'manual')
                ->where('is_active', true)
                ->update(['is_active' => false, 'effective_until' => Carbon::now()]);

            DollarRate::create([
                'rate'            => $rate,
                'source'          => 'manual',
                'currency_type'   => 'USD',
                'effective_from'  => Carbon::now(),
                'effective_until' => null,
                'is_active'       => true,
            ]);

            // Activar switch: el sistema usa tasa manual hasta que se desactive
            Cache::put(self::MANUAL_OVERRIDE_KEY, true, now()->addHours(48));
            Cache::forget(self::CACHE_KEY);

            return true;
        } catch (Throwable $e) {
            Log::error('DollarRateService::storeManualRate falló', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // ─── Utilidades ───────────────────────────────────────────────────────────

    /**
     * Convierte USD a Bs: usd × rate, redondeado a 2 decimales.
     */
    public function formatBs(float $usd, float $rate): float
    {
        return round($usd * $rate, 2);
    }

    /**
     * Fuentes disponibles.
     *
     * @return string[]
     */
    public function getSources(): array
    {
        return ['bcv', 'parallel', 'negotiated', 'manual'];
    }

    // ─── Privados ─────────────────────────────────────────────────────────────

    private function sanitizeSource(string $source): string
    {
        return in_array($source, $this->getSources(), true) ? $source : 'bcv';
    }

    private function envFallback(): float
    {
        return (float) env('DOLLAR_FALLBACK_RATE', self::FALLBACK);
    }
}