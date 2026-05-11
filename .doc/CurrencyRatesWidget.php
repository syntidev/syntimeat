<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\DollarRate;
use App\Services\DollarRateService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrencyRatesWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 3;
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        try {
            $service = app(DollarRateService::class);
            $usdRate = $service->getCurrentRate();
        } catch (\Throwable) {
            $usdRate = 0;
        }

        try {
            $service ??= app(DollarRateService::class);
            $eurRate = $service->getCurrentEuroRate();
        } catch (\Throwable) {
            $eurRate = 0;
        }

        try {
            $lastUpdate = DollarRate::query()
                ->where('is_active', true)
                ->orderByDesc('effective_from')
                ->value('effective_from');
            $lastUpdateLabel = $lastUpdate
                ? \Carbon\Carbon::parse($lastUpdate)->diffForHumans()
                : 'Sin datos';
        } catch (\Throwable) {
            $lastUpdateLabel = 'N/D';
        }

        return [
            Stat::make('USD · BCV Oficial', $usdRate > 0 ? number_format($usdRate, 2) . ' Bs' : 'N/D')
                ->description('Tasa oficial BCV')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->icon('heroicon-o-currency-dollar')
                ->color('primary'),
            Stat::make('EUR · BCV Oficial', $eurRate > 0 ? number_format($eurRate, 2) . ' Bs' : 'N/D')
                ->description('Tasa euro BCV')
                ->descriptionIcon('heroicon-m-banknotes')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Actualizado · pydolarve.org', $lastUpdateLabel)
                ->description('Tasas de cambio')
                ->descriptionIcon('heroicon-m-clock')
                ->icon('heroicon-o-clock')
                ->color('gray'),
        ];
    }
}
