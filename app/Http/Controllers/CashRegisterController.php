<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BovedaEntry;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Services\DollarRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CashRegisterController extends Controller
{
    public function __construct(private readonly DollarRateService $rates) {}

    // ─── Vista principal ──────────────────────────────────────────────────────

    public function index(): Response
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $todayRate  = $this->rates->getTodayRate();

        $branchId = $user->branch_id;

        $isAdmin = in_array($user->role, ['admin', 'super_admin', 'owner', 'supervisor'], true);

        // Cajero ve su propia caja; admin/supervisor ve todas las abiertas
        $cashRegister = CashRegister::with(['movements.creator', 'opener'])
            ->where('business_id', $businessId)
            ->when(! $isAdmin, fn ($q) => $q->where('opened_by', $user->id))
            ->when($branchId && ! $isAdmin, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('closed_at')
            ->orderBy('opened_at')
            ->first();

        // Admin ve todas las cajas abiertas (para supervisar y cerrar turnos)
        $allOpenRegisters = $isAdmin
            ? CashRegister::with(['opener'])
                ->where('business_id', $businessId)
                ->whereNull('closed_at')
                ->orderBy('opened_at')
                ->get()
                ->map(fn ($r) => [
                    'id'                => $r->id,
                    'name'              => $r->name,
                    'opened_at'         => $r->opened_at,
                    'opening_amount_bs' => $r->opening_amount_bs,
                    'opener_name'       => $r->opener?->name ?? '—',
                    'branch_id'         => $r->branch_id,
                ])
            : [];

        $history = CashRegister::with(['opener', 'closer'])
            ->where('business_id', $businessId)
            ->when($branchId && ! $isAdmin, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get();

        $kpis = null;

        if ($cashRegister) {
            $salesTotalBs = $cashRegister->sales()
                ->where('status', 'paid')
                ->sum('total_bs');

            // Movimientos en Bs. — usar amount_bs guardado, no reconvertir con tasa actual
            $movInBs  = (float) $cashRegister->movements()->where('type', 'in')->sum('amount_bs');
            $movOutBs = (float) $cashRegister->movements()->where('type', 'out')->sum('amount_bs');

            // Bug fix: usar opening_amount_bs guardado — no reconvertir con tasa actual
            $openingBs   = (float) $cashRegister->opening_amount_bs;
            $expectedBs  = round($openingBs + $movInBs - $movOutBs, 2);

            $kpis = [
                'expected_bs'     => $expectedBs,
                'sales_total_bs'  => round((float) $salesTotalBs, 2),
                'movements_count' => $cashRegister->movements()->count(),
                'rate'            => $todayRate,
            ];
        }

        return Inertia::render('Cash/Index', [
            'cashRegister'     => $cashRegister,
            'allOpenRegisters' => $allOpenRegisters,
            'history'          => $history,
            'kpis'             => $kpis,
            'todayRate'        => $todayRate,
            'isAdmin'          => $isAdmin,
        ]);
    }

    // ─── Abrir caja ───────────────────────────────────────────────────────────

    public function open(Request $request): RedirectResponse|JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        $branchId = $user->branch_id;

        // ── Bloquear si hay caja del día anterior sin cerrar ──────────────────
        $cajaAnterior = CashRegister::where('business_id', $businessId)
            ->whereNull('closed_at')
            ->whereDate('opened_at', '<', now('America/Caracas')->toDateString())
            ->first();

        if ($cajaAnterior) {
            return response()->json([
                'message' => 'Hay una caja abierta desde el ' . $cajaAnterior->opened_at->format('d/m/Y') . '. Debes cerrarla antes de abrir una nueva.',
                'errors'  => [
                    'requires_close' => 'true',
                    'caja_id'        => (string) $cajaAnterior->id,
                    'caja_fecha'     => $cajaAnterior->opened_at->format('d/m/Y'),
                ],
            ], 422);
        }

        // Bloquear solo si ESTE USUARIO ya tiene una caja abierta (admins exentos)
        $isAdmin     = in_array($user->role, ['super_admin', 'admin', 'owner', 'branch_admin', 'analyst'], true);
        $alreadyOpen = CashRegister::where('business_id', $businessId)
            ->when(! $isAdmin, fn ($q) => $q->where('opened_by', $user->id))
            ->whereNull('closed_at')
            ->exists();

        if ($alreadyOpen) {
            return back()->withErrors(['caja' => 'Ya tienes una caja abierta. Haz el corte de tu turno antes de abrir una nueva.']);
        }

        $data = $request->validate([
            'opening_amount_bs' => ['required', 'numeric', 'min:0'],
        ]);

        $rate       = $this->rates->getTodayRate();
        $openingUsd = $rate > 0
            ? round((float) $data['opening_amount_bs'] / $rate, 2)
            : 0.0;

        $register = CashRegister::create([
            'business_id'        => $businessId,
            'branch_id'          => $branchId,
            'name'               => 'Caja ' . now()->format('d/m/Y'),
            'opened_at'          => now(),
            'opening_amount_usd' => $openingUsd,
            'opening_amount_bs'  => (float) $data['opening_amount_bs'],
            'rate_at_opening'    => $rate,
            'opened_by'          => $user->id,
        ]);

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => $user->id,
            'action'      => 'caja.apertura',
            'model_type'  => CashRegister::class,
            'model_id'    => $register->id,
            'new_values'  => ['monto_apertura' => $data['opening_amount_bs']],
        ]);

        return redirect()->route('cash.index');
    }

    // ─── Corte de turno (NO cierra la caja) ──────────────────────────────────

    public function close(Request $request, CashRegister $register): RedirectResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        abort_unless((int) $register->business_id === (int) $businessId, 403);
        abort_unless($register->closed_at === null, 422, 'La caja ya está cerrada.');

        $data = $request->validate([
            'counted_cash_bs' => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $rate      = $this->rates->getTodayRate();
        $countedBs = (float) $data['counted_cash_bs'];
        $countedUsd = $rate > 0 ? round($countedBs / $rate, 4) : 0.0;

        $concept = 'Corte de turno'
            . ($data['notes'] ? ': ' . $data['notes'] : '');

        DB::transaction(function () use ($register, $user, $businessId, $countedUsd, $countedBs, $concept, $rate) {
            $register->movements()->create([
                'type'       => 'corte',
                'amount_usd' => $countedUsd,
                'amount_bs'  => $countedBs,
                'concept'    => $concept,
                'created_by' => $user->id,
            ]);

            $register->update([
                'closed_at' => now(),
                'closed_by' => $user->id,
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'cash.corte_turno',
                'model_type'  => CashRegister::class,
                'model_id'    => $register->id,
                'new_values'  => [
                    'counted_bs'  => $countedBs,
                    'counted_usd' => round($countedUsd, 2),
                    'rate'        => $rate,
                ],
            ]);
        });

        return redirect()->route('cash.index');
    }

    // ─── Vista Cierre del Día ─────────────────────────────────────────────────

    public function dayClose(): Response
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $rate       = $this->rates->getTodayRate();

        $isAdmin = in_array(Auth::user()->role, ['super_admin', 'admin', 'owner', 'branch_admin', 'analyst'], true);

        // Admin ve todas las cajas abiertas; cajero solo la suya
        $cashRegister = CashRegister::with(['movements.creator', 'opener'])
            ->where('business_id', $businessId)
            ->when(! $isAdmin, fn ($q) => $q->where('opened_by', Auth::id()))
            ->whereNull('closed_at')
            ->orderBy('opened_at')
            ->first();

        if ($cashRegister === null) {
            return Inertia::render('Cash/DayClose', [
                'cashRegister' => null,
                'salesCount'   => 0,
                'totalBs'      => 0,
                'byMethod'     => [],
                'movements'    => [],
                'expectedBs'   => 0,
                'expectedUsd'  => 0,
                'todayRate'    => $rate,
                'bovedaActiva' => [],
            ]);
        }

        $sales = $cashRegister->sales()
            ->where('status', 'paid')
            ->get(['id', 'total_bs', 'total_usd', 'payment_method', 'ticket_number', 'sold_at']);

        // Agrupa desde sale_payments para soportar pagos mixtos correctamente
        $byMethod = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $cashRegister->id)
            ->where('sales.status', 'paid')
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->select(
                'payment_methods.name as method',
                DB::raw('COUNT(DISTINCT sales.id) as count'),
                DB::raw('SUM(sale_payments.amount_bs) as total_bs'),
            )
            ->get()
            ->map(fn ($r) => [
                'method'   => $r->method,
                'count'    => (int) $r->count,
                'total_bs' => round((float) $r->total_bs, 2),
            ])
            ->values();

        $openingBs   = (float) $cashRegister->opening_amount_bs;
        $movInBs     = (float) $cashRegister->movements->where('type', 'in')->sum('amount_bs');
        $movOutBs    = (float) $cashRegister->movements->where('type', 'out')->sum('amount_bs');

        // Solo ventas cobradas en efectivo — pago móvil/transferencia no entran a caja
        $ventasEfectivo = (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $cashRegister->id)
            ->where('sales.status', 'paid')
            ->where('payment_methods.type', 'cash')
            ->sum('sale_payments.amount_bs');

        $expectedBs  = round($openingBs + $ventasEfectivo + $movInBs - $movOutBs, 2);
        $expectedUsd = $rate > 0 ? round($expectedBs / $rate, 2) : 0.0;

        // ── Utilidad por Bóveda ───────────────────────────────────────────────
        // Ventas del día por categoría — usando inventory_entries con boveda_entry_id
        // para trazabilidad real (sin mapas hardcodeados de strings)
        $categoryStats = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->whereDate('sales.sold_at', today())
            ->groupBy('categories.name')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(sale_items.subtotal_usd) as ventas_usd'),
                DB::raw('SUM(sale_items.quantity_value) as kg_vendidos'),
            )
            ->get()
            ->keyBy('category_name');

        // Mapa boveda_entry_id → categoría de vitrina via inventory_entries + products + categories
        $bovedaCategoryMap = DB::table('inventory_entries')
            ->join('products', 'products.id', '=', 'inventory_entries.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('inventory_entries.business_id', $businessId)
            ->where('inventory_entries.location', 'vitrina')
            ->whereNotNull('inventory_entries.boveda_entry_id')
            ->groupBy('inventory_entries.boveda_entry_id', 'categories.name')
            ->select('inventory_entries.boveda_entry_id', 'categories.name as category_name')
            ->get()
            ->groupBy('boveda_entry_id')
            ->map(fn ($rows) => $rows->first()->category_name);

        $bovedaActiva = BovedaEntry::active()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $cashRegister->opened_at)
            ->get()
            ->map(function (BovedaEntry $entry) use ($bovedaCategoryMap, $categoryStats): array {
                $catName    = $bovedaCategoryMap->get($entry->id);
                $stats      = $catName ? $categoryStats->get($catName) : null;
                $ventasUsd  = round((float) ($stats?->ventas_usd ?? 0), 2);
                $costoUsd   = round((float) $entry->costo_usd, 2);
                $kgVendidos = round((float) ($stats?->kg_vendidos ?? 0), 3);
                $utilidad   = round($ventasUsd - $costoUsd, 2);
                $pct        = $costoUsd > 0 ? round(($ventasUsd / $costoUsd) * 100, 1) : 0.0;

                return [
                    'id'                    => $entry->id,
                    'product_label'         => $entry->product_type,
                    'description'           => $entry->description,
                    'kg_entrada'            => (float) $entry->kg_entrada,
                    'waste_kg'              => (float) $entry->waste_kg,
                    'kg_surtido_vitrina'    => (float) $entry->kg_surtido_vitrina,
                    'kg_disponible'         => (float) $entry->kg_disponible,
                    'costo_entrada_usd'     => $costoUsd,
                    'ventas_categoria_usd'  => $ventasUsd,
                    'utilidad_usd'          => $utilidad,
                    'kg_vendidos_categoria' => $kgVendidos,
                    'porcentaje_recuperado' => $pct,
                ];
            });

        // ── Ventas por macro-categoría ────────────────────────────────────────
        $macroMap = ['RES' => 'RES', 'POLLO' => 'POLLO', 'CERDO' => 'CERDO', 'TRASTES' => 'TRASTES'];
        $macroStats = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.cash_register_id', $cashRegister->id)
            ->where('sales.status', 'paid')
            ->groupBy('categories.macro_category')
            ->select(
                DB::raw('COALESCE(categories.macro_category, "MISC") as macro'),
                DB::raw('SUM(sale_items.subtotal_bs) as total_bs'),
                DB::raw('SUM(sale_items.subtotal_usd) as total_usd'),
                DB::raw('COUNT(DISTINCT sales.id) as ventas'),
                DB::raw('SUM(sale_items.quantity_value) as kg_total'),
            )
            ->get()
            ->map(fn ($r) => [
                'macro'     => $r->macro,
                'total_bs'  => round((float) $r->total_bs, 2),
                'total_usd' => round((float) $r->total_usd, 2),
                'ventas'    => (int) $r->ventas,
                'kg_total'  => round((float) $r->kg_total, 3),
            ])
            ->sortBy('macro')
            ->values();

        return Inertia::render('Cash/DayClose', [
            'cashRegister' => $cashRegister,
            'salesCount'   => $sales->count(),
            'totalBs'      => round($sales->sum('total_bs'), 2),
            'byMethod'     => $byMethod,
            'macroStats'   => $macroStats,
            'movements'    => $cashRegister->movements->map(fn ($m) => [
                'id'         => $m->id,
                'type'       => $m->type,
                'amount_usd' => (float) $m->amount_usd,
                'amount_bs'  => (float) ($m->amount_bs ?? round((float) $m->amount_usd * $rate, 2)),
                'concept'    => $m->concept,
                'creator'    => $m->creator?->name,
                'created_at' => $m->created_at,
            ]),
            'expectedBs'   => $expectedBs,
            'expectedUsd'  => $expectedUsd,
            'todayRate'    => $rate,
            'bovedaActiva' => $bovedaActiva,
        ]);
    }

    // ─── Confirmar Cierre del Día ─────────────────────────────────────────────

    public function confirmClose(Request $request, CashRegister $register): RedirectResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        abort_unless((int) $register->business_id === (int) $businessId, 403);
        abort_unless($register->closed_at === null, 422, 'La caja ya está cerrada.');

        $data = $request->validate([
            'counted_cash_bs' => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $rate = $this->rates->getTodayRate();

        $movInBs  = (float) $register->movements()->where('type', 'in')->sum('amount_bs');
        $movOutBs = (float) $register->movements()->where('type', 'out')->sum('amount_bs');

        // Solo ventas cobradas en efectivo — pago móvil/transferencia no entran a caja
        $ventasBs = (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $register->id)
            ->where('sales.status', 'paid')
            ->where('payment_methods.type', 'cash')
            ->sum('sale_payments.amount_bs');

        // Usar opening_amount_bs guardado — no reconvertir
        $openingBs     = (float) $register->opening_amount_bs;
        $expectedBs    = round($openingBs + $ventasBs + $movInBs - $movOutBs, 2);
        $expectedUsd   = $rate > 0 ? round($expectedBs / $rate, 2) : 0.0;
        $countedUsd    = $rate > 0 ? round((float) $data['counted_cash_bs'] / $rate, 2) : 0.0;
        $differenceUsd = round($countedUsd - $expectedUsd, 2);

        DB::transaction(function () use (
            $register, $data, $user, $businessId,
            $expectedUsd, $countedUsd, $differenceUsd
        ) {
            $register->update([
                'closed_at'         => now(),
                'expected_cash_usd' => $expectedUsd,
                'counted_cash_usd'  => $countedUsd,
                'difference_usd'    => $differenceUsd,
                'notes'             => $data['notes'] ?? null,
                'closed_by'         => $user->id,
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'cash.day_close',
                'model_type'  => CashRegister::class,
                'model_id'    => $register->id,
                'new_values'  => [
                    'expected_usd'   => $expectedUsd,
                    'counted_usd'    => $countedUsd,
                    'difference_usd' => $differenceUsd,
                ],
            ]);
        });

        return redirect()->route('cash.index');
    }

    // ─── Movimiento manual (retiro / ingreso) ─────────────────────────────────

    public function movement(Request $request, CashRegister $register): RedirectResponse|JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        abort_unless((int) $register->business_id === (int) $businessId, 403);
        abort_unless($register->closed_at === null, 422, 'La caja ya está cerrada.');

        $data = $request->validate([
            'type'      => ['required', 'string', 'in:in,out'],
            'amount_bs' => ['required', 'numeric', 'min:0.01'],
            'concept'   => ['required', 'string', 'min:3', 'max:150'],
        ]);

        $rate      = $this->rates->getTodayRate();
        $amountBs  = (float) $data['amount_bs'];
        $amountUsd = $rate > 0
            ? round($amountBs / $rate, 4)
            : 0.0;

        if ($data['type'] === 'out') {
            $inflows  = (float) $register->movements()->where('type', 'in')->sum('amount_bs');
            $outflows = (float) $register->movements()->where('type', 'out')->sum('amount_bs');

            // Ventas cobradas en efectivo asociadas a esta caja
            $ventasEfectivo = (float) \App\Models\SalePayment::whereHas('sale', function ($q) use ($register) {
                $q->where('cash_register_id', $register->id)
                  ->where('status', 'paid');
            })->whereHas('paymentMethod', function ($q) {
                $q->where('type', 'cash');
            })->sum('amount_bs');

            $saldo = (float) $register->opening_amount_bs + $inflows + $ventasEfectivo - $outflows;

            if ($amountBs > $saldo) {
                $mensaje = 'El retiro (Bs. ' . number_format($amountBs, 2) . ') supera el saldo disponible (Bs. ' . number_format($saldo, 2) . ').';

                return back()->withErrors(['amount_bs' => $mensaje]);
            }
        }

        $register->movements()->create([
            'type'       => $data['type'],
            'amount_usd' => $amountUsd,
            'amount_bs'  => $amountBs,
            'concept'    => $data['concept'],
            'created_by' => $user->id,
        ]);

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => $user->id,
            'action'      => 'caja.movimiento',
            'model_type'  => CashRegister::class,
            'model_id'    => $register->id,
            'new_values'  => [
                'tipo'   => $data['type'],
                'monto'  => $data['amount_bs'],
                'motivo' => $data['concept'],
            ],
        ]);

        return redirect()->route('cash.index');
    }
}
