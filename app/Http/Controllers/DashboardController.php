<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\InventoryEntry;
use App\Models\Order;
use App\Models\BovedaEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\DollarRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DollarRateService $rates) {}

    // ─── Página principal ─────────────────────────────────────────────────────

    public function index(): Response
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        return Inertia::render('Dashboard', $this->buildData($businessId));
    }

    // ─── Endpoint JSON para polling ───────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        return response()->json($this->buildData($businessId));
    }

    // ─── Builder compartido ───────────────────────────────────────────────────

    private function buildData(int $businessId): array
    {
        $user     = Auth::user();
        $branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $rate       = $this->rates->getTodayRate();
        $today      = now('America/Caracas')->toDateString();

        // ── Ventas del día ────────────────────────────────────────────────────
        $ventasHoyRaw = Sale::where('business_id', $businessId)
            ->where('status', 'paid')
            ->whereDate('accounting_date', $today)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_bs), 0) as total_bs, COALESCE(SUM(total_usd), 0) as total_usd')
            ->first();

        $ventasHoy = [
            'count'    => (int)   ($ventasHoyRaw->count    ?? 0),
            'total_bs' => (float) ($ventasHoyRaw->total_bs ?? 0),
            'total_usd'=> (float) ($ventasHoyRaw->total_usd ?? 0),
        ];

        // ── Top 5 productos del día por monto Bs ─────────────────────────────
        $topProductos = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->whereDate('sales.accounting_date', $today)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw('sale_items.product_id, sale_items.product_name, SUM(sale_items.quantity_value) as cantidad, SUM(sale_items.subtotal_bs) as monto_bs')
            ->groupBy('sale_items.product_id', 'sale_items.product_name')
            ->orderByDesc('monto_bs')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'name'     => $r->product_name,
                'monto_bs' => round((float) $r->monto_bs, 2),
                'cantidad' => round((float) $r->cantidad, 3),
            ])
            ->values()
            ->toArray();

        // ── Stock crítico ─────────────────────────────────────────────────────
        // Stock actual = SUM(net_kg) de inventory_entries (ventas YA están como entradas negativas)
        // No restar sale_items — sería doble descuento
        $stockMap = InventoryEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('product_id, SUM(quantity_kg - waste_kg) as stock')
            ->groupBy('product_id')
            ->pluck('stock', 'product_id')
            ->map(fn ($v) => (float) $v);

        $stockCritico = Product::with('category')
            ->where('business_id', $businessId)
            ->where('active', true)
            ->whereNotNull('min_stock')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get()
            ->filter(function (Product $p) use ($stockMap) {
                $net = ($stockMap[$p->id] ?? 0.0);
                return $net <= (float) $p->min_stock;
            })
            ->map(function (Product $p) use ($stockMap) {
                $net = ($stockMap[$p->id] ?? 0.0);
                return [
                    'id'        => $p->id,
                    'name'      => $p->name,
                    'category'  => $p->category?->name ?? '—',
                    'stock'     => round($net, 3),
                    'min_stock' => (float) $p->min_stock,
                    'sale_mode' => $p->sale_mode,
                ];
            })
            ->sortBy('stock')
            ->values()
            ->toArray();

        // ── Últimas 8 ventas paid ─────────────────────────────────────────────
        $ultimasVentas = Sale::where('business_id', $businessId)
            ->where('status', 'paid')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->withCount('items')
            ->orderByDesc('sold_at')
            ->limit(8)
            ->get()
            ->map(fn (Sale $s) => [
                'id'             => $s->id,
                'ticket_number'  => $s->ticket_number,
                'sold_at'        => $s->sold_at?->toDateTimeString(),
                'items_count'    => $s->items_count,
                'total_bs'       => (float) $s->total_bs,
                'total_usd'      => (float) $s->total_usd,
                'payment_method' => $s->payment_method,
                'status'         => $s->status,
            ])
            ->toArray();

        // ── Caja activa ───────────────────────────────────────────────────────
        $cajaActiva = CashRegister::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('closed_at')
            ->select('id', 'name', 'opened_at')
            ->first();

        // ── Pedidos pendientes ────────────────────────────────────────────────
        $pedidosPendientes = Order::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'pending')
            ->count();

        // ── Ventas por categoría hoy ──────────────────────────────────────────
        $categoriaStats = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->whereDate('sales.accounting_date', $today)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->groupBy('categories.id', 'categories.name')
            ->select(
                'categories.id as category_id',
                'categories.name as category_name',
                DB::raw('SUM(sale_items.subtotal_bs) as total_bs'),
                DB::raw('SUM(sale_items.subtotal_usd) as total_usd'),
                DB::raw("SUM(CASE WHEN sale_items.input_type = 'weight' THEN sale_items.quantity_value ELSE 0 END) as kg_vendidos"),
            )
            ->get()
            ->keyBy('category_name');

        $categoriasHoy = $categoriaStats->map(fn ($r) => [
            'category_id'   => $r->category_id,
            'category_name' => $r->category_name,
            'total_bs'      => round((float) $r->total_bs, 2),
            'total_usd'     => round((float) $r->total_usd, 2),
            'kg_vendidos'   => round((float) $r->kg_vendidos, 3),
        ])->values()->toArray();

        // ── Utilidad por Bóveda ───────────────────────────────────────────────
        // product_type es texto libre — mapeamos a categoría de vitrina
        $bovedaCategoryMap = [
            'RES - Medio Canal'        => 'Res',
            'CERDO - Canal'            => 'Cerdo',
            'POLLO - Entero Congelado' => 'Pollo',
            // Legacy (por si hay entradas antiguas en DB)
            'Medio Canal Res'          => 'Res',
            'Canal Cerdo'              => 'Cerdo',
            'Pollo Entero Congelado'   => 'Pollo',
            'Jamón Pierna Sellado'     => 'Charcutería',
        ];

        $utilidadBoveda = BovedaEntry::active()
            ->where('business_id', $businessId)
            ->get()
            ->map(function (BovedaEntry $entry) use ($bovedaCategoryMap, $categoriaStats): array {
                $catName   = $bovedaCategoryMap[$entry->product_type] ?? null;
                $stats     = $catName ? $categoriaStats->get($catName) : null;
                $ventasUsd = round((float) ($stats?->total_usd ?? 0), 2);
                $costoUsd  = round((float) $entry->costo_usd, 2);
                $utilidad  = round($ventasUsd - $costoUsd, 2);
                $pct       = $costoUsd > 0 ? round(($ventasUsd / $costoUsd) * 100, 1) : 0.0;

                return [
                    'id'            => $entry->id,
                    'product_label' => $entry->product_type,
                    'description'   => $entry->description,
                    'category_name' => $catName,
                    'costo'         => $costoUsd,
                    'ventas_hoy'    => $ventasUsd,
                    'utilidad'      => $utilidad,
                    'kg_vendidos'   => round((float) ($stats?->kg_vendidos ?? 0), 3),
                    'porcentaje'    => $pct,
                ];
            })
            ->values()
            ->toArray();

        return [
            'ventas_hoy'        => $ventasHoy,
            'top_productos'     => $topProductos,
            'stock_critico'     => $stockCritico,
            'ultimas_ventas'    => $ultimasVentas,
            'caja_activa'       => $cajaActiva,
            'tasa_hoy'          => $rate,
            'pedidos_pendientes'=> $pedidosPendientes,
            'categorias_hoy'    => $categoriasHoy,
            'utilidad_boveda'   => $utilidadBoveda,
            'banking_alert'     => Cache::get('banking_alert'),
        ];
    }
}
