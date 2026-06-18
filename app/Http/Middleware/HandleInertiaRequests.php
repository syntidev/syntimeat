<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DollarRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $tasa                   = ['rate' => 40.0, 'source' => 'fallback', 'hora' => null, 'manual_override' => false];
        $stockCriticoCount      = 0;
        $cobrosPendientesCount  = 0;
        $despiecePendienteCount = 0;
        $businessName           = '';
        $businessLogoUrl        = null;
        $themeColor             = 'blue';
        $branches               = [];

        if ($user = $request->user()) {
            try {
                // Si el switch manual está activo, priorizar tasa manual
                $manualOverride = \Illuminate\Support\Facades\Cache::get('syntimeat_manual_rate_override', false);

                $record = $manualOverride
                    ? DollarRate::where('currency_type', 'USD')
                        ->where('is_active', true)
                        ->where('source', 'manual')
                        ->orderByDesc('effective_from')
                        ->first()
                        ?? DollarRate::where('currency_type', 'USD')
                            ->where('is_active', true)
                            ->orderByDesc('effective_from')
                            ->first()
                    : DollarRate::where('currency_type', 'USD')
                        ->where('is_active', true)
                        ->orderByDesc('effective_from')
                        ->first();

                // Pasar estado del switch al frontend
                $manualOverrideActive = $manualOverride;

                if ($record) {
                    $tasa = [
                        'rate'            => round((float) $record->rate, 2),
                        'source'          => $record->source,
                        'hora'            => $record->created_at?->format('H:i'),
                        'manual_override' => (bool) ($manualOverrideActive ?? false),
                    ];
                }
            } catch (Throwable $_) {
                // fallback silencioso
            }

            try {
                $business = $user->business;
                if ($business) {
                    $businessName    = $business->name ?? '';
                    $themeColor      = $business->theme_color ?? 'blue';
                    $businessLogoUrl = $business->logo_path
                        ? Storage::disk('public')->url($business->logo_path)
                        : null;
                    $bid      = $business->id;
                    $branches = $business->branches()
                        ->where('is_active', true)
                        ->get(['id', 'name'])
                        ->toArray();

                    $stockCriticoCount = Cache::remember(
                        "stock_critico_count_{$bid}",
                        300,
                        static function () use ($bid): int {
                            return (int) DB::table('products')
                                ->where('products.business_id', $bid)
                                ->where('products.active', true)
                                ->whereNotNull('products.min_stock')
                                ->whereRaw(
                                    'COALESCE((
                                        SELECT SUM(ie.quantity_kg - ie.waste_kg)
                                        FROM inventory_entries ie
                                        WHERE ie.product_id = products.id
                                    ), 0)
                                    - COALESCE((
                                        SELECT SUM(si.quantity_value)
                                        FROM sale_items si
                                        JOIN sales s ON s.id = si.sale_id
                                        WHERE si.product_id = products.id
                                          AND s.business_id = ?
                                          AND s.status = \'paid\'
                                    ), 0)
                                    <= products.min_stock',
                                    [$bid]
                                )
                                ->count();
                        }
                    );

                    $cobrosPendientesCount = Cache::remember(
                        "cobros_pendientes_count_{$bid}",
                        120,
                        static function () use ($bid): int {
                            return (int) DB::table('sales')
                                ->where('business_id', $bid)
                                ->where(function ($q): void {
                                    $q->where(function ($q2): void {
                                        $q2->where('status', 'paid')
                                           ->where('payment_status', 'pendiente_cobro');
                                    })->orWhere(function ($q2): void {
                                        $q2->where('origin', 'delivery')
                                           ->where('status', 'pending');
                                    });
                                })
                                ->count();
                        }
                    );

                    $despiecePendienteCount = Cache::remember(
                        "despiece_pendiente_count_{$bid}",
                        60,
                        static function () use ($bid): int {
                            return (int) DB::table('boveda_entries')
                                ->where('business_id', $bid)
                                ->whereNull('despiece_completado_at')
                                ->where('kg_surtido_vitrina', '>', 0)
                                ->count();
                        }
                    );
                }
            } catch (Throwable $_) {
                // fallback silencioso
            }
        }

        return [
            ...parent::share($request),
            'auth'                => ['user' => $request->user()],
            'tasa'                => $tasa,
            'stock_critico_count'      => $stockCriticoCount,
            'cobros_pendientes_count'   => $cobrosPendientesCount,
            'despiece_pendiente_count'  => $despiecePendienteCount,
            'business_name'       => $businessName,
            'business_logo_url'   => $businessLogoUrl,
            'theme_color'         => $themeColor,
            'branches'            => $branches,
            'currentBranch'       => session('current_branch_id'),
            'banking_alert'       => Cache::get('banking_alert'),
        ];
    }
}
