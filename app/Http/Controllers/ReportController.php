<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    // ─── Vista principal ──────────────────────────────────────────────────────

    public function index(): Response
    {
        $businessId = Auth::user()->business->id;

        $categories = Category::where('business_id', $businessId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::where('business_id', $businessId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return Inertia::render('Reports/Index', [
            'categories' => $categories,
            'products'   => $products,
        ]);
    }

    // ─── JSON: Ventas ─────────────────────────────────────────────────────────

    public function sales(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_desde'  => ['nullable', 'date'],
            'fecha_hasta'  => ['nullable', 'date'],
            'category_id'  => ['nullable', 'integer'],
            'status'       => ['nullable', 'string', 'in:open,pending,paid,cancelled'],
        ]);

        $businessId = Auth::user()->business->id;

        $query = Sale::with([
            'items.product.category',
            'salePayments.paymentMethod',
        ])
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        if (! empty($data['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $data['fecha_desde']);
        }

        if (! empty($data['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $data['fecha_hasta']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (! empty($data['category_id'])) {
            $query->whereHas('items.product', function ($q) use ($data) {
                $q->where('category_id', $data['category_id']);
            });
        }

        $rows = $query->limit(500)->get()->map(fn (Sale $s) => [
            'id'             => $s->id,
            'ticket_number'  => $s->ticket_number,
            'fecha'          => $s->created_at?->toDateTimeString(),
            'client_name'    => $s->client_name ?? '—',
            'items_count'    => $s->items->count(),
            'metodos_pago'   => $s->salePayments->map(fn ($p) => $p->paymentMethod?->name ?? '—')->implode(', '),
            'total_bs'       => (float) ($s->total_bs ?? 0),
            'total_usd'      => (float) $s->total_usd,
            'status'         => $s->status,
        ]);

        return response()->json(['data' => $rows]);
    }

    // ─── JSON: Inventario ─────────────────────────────────────────────────────

    public function inventory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
            'product_id'  => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $businessId = Auth::user()->business->id;

        $query = InventoryEntry::with(['product.category'])
            ->where('business_id', $businessId)
            ->orderByDesc('entered_at');

        if (! empty($data['fecha_desde'])) {
            $query->whereDate('entered_at', '>=', $data['fecha_desde']);
        }

        if (! empty($data['fecha_hasta'])) {
            $query->whereDate('entered_at', '<=', $data['fecha_hasta']);
        }

        if (! empty($data['product_id'])) {
            $query->where('product_id', $data['product_id']);
        }

        if (! empty($data['category_id'])) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $data['category_id']));
        }

        $rows = $query->limit(500)->get()->map(fn (InventoryEntry $e) => [
            'id'            => $e->id,
            'fecha'         => $e->entered_at?->toDateTimeString(),
            'producto'      => $e->product?->name ?? '—',
            'categoria'     => $e->product?->category?->name ?? '—',
            'recibido'      => (float) $e->quantity_kg,
            'merma'         => (float) $e->waste_kg,
            'neto'          => round((float) $e->quantity_kg - (float) $e->waste_kg, 3),
            'costo_usd'     => (float) ($e->cost_per_kg_usd ?? 0),
            'proveedor'     => $e->supplier ?? '—',
        ]);

        return response()->json(['data' => $rows]);
    }

    // ─── JSON: Cierres de caja ────────────────────────────────────────────────

    public function closings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date'],
        ]);

        $businessId = Auth::user()->business->id;

        $query = CashRegister::where('business_id', $businessId)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at');

        if (! empty($data['fecha_desde'])) {
            $query->whereDate('closed_at', '>=', $data['fecha_desde']);
        }

        if (! empty($data['fecha_hasta'])) {
            $query->whereDate('closed_at', '<=', $data['fecha_hasta']);
        }

        $rows = $query->limit(200)->get()->map(fn (CashRegister $r) => [
            'id'            => $r->id,
            'nombre'        => $r->name,
            'abierto_at'    => $r->opened_at?->toDateTimeString(),
            'cerrado_at'    => $r->closed_at?->toDateTimeString(),
            'apertura_usd'  => (float) $r->opening_amount_usd,
            'esperado_usd'  => (float) ($r->expected_cash_usd ?? 0),
            'contado_usd'   => (float) ($r->counted_cash_usd ?? 0),
            'diferencia_usd'=> (float) ($r->difference_usd ?? 0),
            'tasa'          => (float) ($r->rate_at_opening ?? 0),
            'notas'         => $r->notes,
        ]);

        return response()->json(['data' => $rows]);
    }

    // ─── JSON: Pedidos ────────────────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha_desde'  => ['nullable', 'date'],
            'fecha_hasta'  => ['nullable', 'date'],
            'client_type'  => ['nullable', 'in:internal,external'],
            'status'       => ['nullable', 'in:open,pending,paid,cancelled'],
        ]);

        $businessId = Auth::user()->business->id;

        $query = Order::withCount('items')
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        if (! empty($data['fecha_desde'])) {
            $query->whereDate('created_at', '>=', $data['fecha_desde']);
        }

        if (! empty($data['fecha_hasta'])) {
            $query->whereDate('created_at', '<=', $data['fecha_hasta']);
        }

        if (! empty($data['client_type'])) {
            $query->where('client_type', $data['client_type']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $rows = $query->limit(500)->get()->map(fn (Order $o) => [
            'id'          => $o->id,
            'fecha'       => $o->created_at?->toDateTimeString(),
            'cliente'     => $o->client_name,
            'tipo'        => $o->client_type,
            'items_count' => $o->items_count,
            'total_usd'   => (float) $o->total_usd,
            'status'      => $o->status,
        ]);

        return response()->json(['data' => $rows]);
    }

    // ─── JSON: Reporte del Día ───────────────────────────────────────────────

    public function dayReport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fecha'          => ['nullable', 'date'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'branch_id'      => ['nullable', 'integer'],
        ]);

        $fecha       = $data['fecha'] ?? now()->toDateString();
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        $businessId  = Auth::user()->business->id;
        $branchId    = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        $result = $this->buildDayData($businessId, $fecha, $categoryIds, $branchId);

        return response()->json(array_merge(['fecha' => $fecha], $result));
    }

    // ─── PDF: Reporte del Día ─────────────────────────────────────────────────

    public function exportDayPdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'fecha'          => ['nullable', 'date'],
            'category_ids'   => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'branch_id'      => ['nullable', 'integer'],
        ]);

        $fecha       = $data['fecha'] ?? now()->toDateString();
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        $business    = Auth::user()->business;
        $cashier     = Auth::user();
        $branchId    = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        $result = $this->buildDayData($business->id, $fecha, $categoryIds, $branchId);
        $html   = $this->buildDayPdfHtml($business, $cashier, $fecha, $result['categories'], $result['totals']);

        return \Barryvdh\DomPDF\Facade\Pdf::loadHtml($html)
            ->setPaper('a4', 'portrait')
            ->download("reporte_dia_{$fecha}.pdf");
    }

    // ─── Panel Empresarial: Vista Consolidada de Sucursales ──────────────────

    public function consolidated(): Response
    {
        $business = Auth::user()->business;

        $branches = Branch::where('business_id', $business->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'city']);

        $maxBranches = (int) ($business->max_branches ?? 2);

        // Selección inicial: hasta max_branches sucursales
        $initialIds = $branches->take($maxBranches)->pluck('id')->all();

        $desde = now()->startOfMonth()->toDateString();
        $hasta = now()->toDateString();

        return Inertia::render('Reports/Consolidado', [
            'branches'     => $branches,
            'max_branches' => $maxBranches,
            'initial'      => $this->buildConsolidatedData($business->id, $initialIds, $desde, $hasta),
            'rango'        => ['desde' => $desde, 'hasta' => $hasta],
        ]);
    }

    public function consolidatedData(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_ids'   => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['integer'],
            'fecha_desde'  => ['nullable', 'date'],
            'fecha_hasta'  => ['nullable', 'date'],
        ]);

        $business    = Auth::user()->business;
        $maxBranches = (int) ($business->max_branches ?? 2);

        // Sucursales válidas del negocio
        $validIds = Branch::where('business_id', $business->id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $branchIds = array_values(array_intersect(
            array_map('intval', $data['branch_ids']),
            $validIds,
        ));

        // Gate del plan: nunca consolidar más de max_branches
        $branchIds = array_slice($branchIds, 0, $maxBranches);

        if (empty($branchIds)) {
            return response()->json(['error' => 'Selecciona al menos una sucursal válida.'], 422);
        }

        $desde = $data['fecha_desde'] ?? now()->startOfMonth()->toDateString();
        $hasta = $data['fecha_hasta'] ?? now()->toDateString();

        return response()->json($this->buildConsolidatedData($business->id, $branchIds, $desde, $hasta));
    }

    // ─── Helper: agregación consolidada por sucursal ─────────────────────────

    private function buildConsolidatedData(int $businessId, array $branchIds, string $desde, string $hasta): array
    {
        if (empty($branchIds)) {
            return [
                'branches'    => [],
                'totals'      => $this->emptyConsolidatedTotals(),
                'tendencia'   => [],
                'categorias'  => [],
                'rango'       => ['desde' => $desde, 'hasta' => $hasta],
            ];
        }

        // ── Ventas agregadas por sucursal ────────────────────────────────────
        $fallback  = $branchIds[0];
        $perBranch = Sale::without('items')
            ->where('business_id', $businessId)
            ->where('status', 'paid')
            ->where(function ($q) {
                $q->whereNull('payment_status')
                  ->orWhere('payment_status', '!=', 'pendiente_cobro');
            })
            ->where(fn ($q) => $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id'))
            ->whereDate('accounting_date', '>=', $desde)
            ->whereDate('accounting_date', '<=', $hasta)
            ->selectRaw("ANY_VALUE(COALESCE(branch_id, {$fallback})) as branch_id, COUNT(*) as ventas_count, COALESCE(SUM(total_bs), 0) as vendido_bs, COALESCE(SUM(total_usd), 0) as vendido_usd")
            ->groupBy(DB::raw("COALESCE(branch_id, {$fallback})"))
            ->get()
            ->keyBy('branch_id');

        // ── Costo real desde boveda_entries del período ──────────────────────
        // Costo por kg = costo_usd / kg_entrada de cada canal procesada
        $bovedaCostos = DB::table('boveda_entries')
            ->where('business_id', $businessId)
            ->whereDate('entered_at', '>=', $desde)
            ->whereDate('entered_at', '<=', $hasta)
            ->whereNotNull('costo_usd')
            ->where('costo_usd', '>', 0)
            ->where('kg_entrada', '>', 0)
            ->selectRaw('SUM(costo_usd) as total_costo, SUM(kg_entrada) as total_kg')
            ->first();

        $costoPorKgBoveda = ($bovedaCostos && (float) $bovedaCostos->total_kg > 0)
            ? (float) $bovedaCostos->total_costo / (float) $bovedaCostos->total_kg
            : 0.0;

        // ── Kg vendidos por sucursal (vía sale_items) ────────────────────────
        $itemRows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->where(fn ($q) => $q->whereIn('sales.branch_id', $branchIds)->orWhereNull('sales.branch_id'))
            ->whereDate('sales.accounting_date', '>=', $desde)
            ->whereDate('sales.accounting_date', '<=', $hasta)
            ->where('sale_items.input_type', 'weight')
            ->groupBy(DB::raw("COALESCE(sales.branch_id, {$fallback})"))
            ->selectRaw("ANY_VALUE(COALESCE(sales.branch_id, {$fallback})) as branch_id, SUM(sale_items.quantity_value) as qty")
            ->get();

        $costoPorSucursal = [];
        $kgPorSucursal    = [];

        foreach ($itemRows as $row) {
            $bid = (int) $row->branch_id;
            $qty = (float) $row->qty;
            $kgPorSucursal[$bid]    = $qty;
            $costoPorSucursal[$bid] = round($qty * $costoPorKgBoveda, 2);
        }

        // ── Armar fila por sucursal ──────────────────────────────────────────
        $branchModels = Branch::whereIn('id', $branchIds)->get(['id', 'name', 'city'])->keyBy('id');

        $branches = [];
        foreach ($branchIds as $bid) {
            $s          = $perBranch->get($bid);
            $vendidoBs  = (float) ($s->vendido_bs ?? 0);
            $vendidoUsd = (float) ($s->vendido_usd ?? 0);
            $ventas     = (int)   ($s->ventas_count ?? 0);
            $costoUsd   = round((float) ($costoPorSucursal[$bid] ?? 0), 2);

            $rateEf      = $vendidoUsd > 0 ? $vendidoBs / $vendidoUsd : 0.0;
            $utilidadUsd = round($vendidoUsd - $costoUsd, 2);
            $utilidadBs  = round($vendidoBs - ($costoUsd * $rateEf), 2);
            $margen      = $vendidoUsd > 0 ? round(($utilidadUsd / $vendidoUsd) * 100, 1) : 0.0;
            $ticketProm  = $ventas > 0 ? round($vendidoBs / $ventas, 2) : 0.0;

            $branches[] = [
                'id'             => $bid,
                'name'           => $branchModels->get($bid)?->name ?? 'Sucursal',
                'city'           => $branchModels->get($bid)?->city ?? '',
                'ventas_count'   => $ventas,
                'vendido_bs'     => round($vendidoBs, 2),
                'vendido_usd'    => round($vendidoUsd, 2),
                'costo_usd'      => $costoUsd,
                'utilidad_usd'   => $utilidadUsd,
                'utilidad_bs'    => $utilidadBs,
                'margen_pct'     => $margen,
                'ticket_prom_bs' => $ticketProm,
                'kg_vendidos'    => round((float) ($kgPorSucursal[$bid] ?? 0), 3),
            ];
        }

        // ── Totales consolidados (números brutos) ────────────────────────────
        $totVentas      = array_sum(array_column($branches, 'ventas_count'));
        $totVendidoBs   = array_sum(array_column($branches, 'vendido_bs'));
        $totVendidoUsd  = array_sum(array_column($branches, 'vendido_usd'));
        $totCostoUsd    = array_sum(array_column($branches, 'costo_usd'));
        $totUtilidadUsd = array_sum(array_column($branches, 'utilidad_usd'));
        $totUtilidadBs  = array_sum(array_column($branches, 'utilidad_bs'));
        $totKg          = array_sum(array_column($branches, 'kg_vendidos'));

        $totals = [
            'ventas_count'   => $totVentas,
            'vendido_bs'     => round($totVendidoBs, 2),
            'vendido_usd'    => round($totVendidoUsd, 2),
            'costo_usd'      => round($totCostoUsd, 2),
            'utilidad_usd'   => round($totUtilidadUsd, 2),
            'utilidad_bs'    => round($totUtilidadBs, 2),
            'margen_pct'     => $totVendidoUsd > 0 ? round(($totUtilidadUsd / $totVendidoUsd) * 100, 1) : 0.0,
            'ticket_prom_bs' => $totVentas > 0 ? round($totVendidoBs / $totVentas, 2) : 0.0,
            'kg_vendidos'    => round($totKg, 3),
        ];

        // ── Tendencia diaria consolidada ─────────────────────────────────────
        $tendencia = Sale::without('items')
            ->where('business_id', $businessId)
            ->where('status', 'paid')
            ->where(fn ($q) => $q->whereIn('branch_id', $branchIds)->orWhereNull('branch_id'))
            ->whereDate('accounting_date', '>=', $desde)
            ->whereDate('accounting_date', '<=', $hasta)
            ->selectRaw('DATE(accounting_date) as dia, COALESCE(SUM(total_usd), 0) as total_usd')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->map(fn ($r) => [
                'dia'       => $r->dia,
                'total_usd' => round((float) $r->total_usd, 2),
            ])
            ->values()
            ->all();

        // ── Mezcla por categoría consolidada ─────────────────────────────────
        $categorias = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->where(fn ($q) => $q->whereIn('sales.branch_id', $branchIds)->orWhereNull('sales.branch_id'))
            ->whereDate('sales.accounting_date', '>=', $desde)
            ->whereDate('sales.accounting_date', '<=', $hasta)
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as categoria, SUM(sale_items.subtotal_usd * sales.rate_used) as vendido_bs')
            ->orderByDesc('vendido_bs')
            ->get()
            ->map(fn ($r) => [
                'categoria'  => $r->categoria,
                'vendido_bs' => round((float) $r->vendido_bs, 2),
            ])
            ->values()
            ->all();

        return [
            'branches'   => $branches,
            'totals'     => $totals,
            'tendencia'  => $tendencia,
            'categorias' => $categorias,
            'rango'      => ['desde' => $desde, 'hasta' => $hasta],
        ];
    }

    private function emptyConsolidatedTotals(): array
    {
        return [
            'ventas_count'   => 0,
            'vendido_bs'     => 0.0,
            'vendido_usd'    => 0.0,
            'costo_usd'      => 0.0,
            'utilidad_usd'   => 0.0,
            'utilidad_bs'    => 0.0,
            'margen_pct'     => 0.0,
            'ticket_prom_bs' => 0.0,
            'kg_vendidos'    => 0.0,
        ];
    }

    // ─── Helper: construir datos del día ──────────────────────────────────────

    private function buildDayData(int $businessId, string $fecha, array $categoryIds, ?int $branchId = null): array
    {
        $sales = Sale::where('business_id', $businessId)
            ->where('status', 'paid')
            ->where(function ($q) {
                $q->whereNull('payment_status')
                  ->orWhere('payment_status', '!=', 'pendiente_cobro');
            })
            ->whereDate('accounting_date', $fecha)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['items.product.category'])
            ->get(['id', 'rate_used']);

        $avgCosts = InventoryEntry::where('business_id', $businessId)
            ->whereNotNull('cost_per_kg_usd')
            ->where('cost_per_kg_usd', '>', 0)
            ->whereDate('entered_at', '<=', $fecha)
            ->selectRaw('product_id, cost_per_kg_usd as avg_cost, MAX(entered_at) as last_entry')
            ->groupBy('product_id', 'cost_per_kg_usd')
            ->orderBy('last_entry', 'desc')
            ->get()
            ->unique('product_id')
            ->pluck('avg_cost', 'product_id');

        $byCat  = [];
        $byProd = [];

        foreach ($sales as $sale) {
            $rate = (float) ($sale->rate_used ?? 1);

            foreach ($sale->items as $item) {
                $catId   = $item->product?->category_id ?? 0;
                $catName = $item->product?->category?->name ?? 'Sin categoría';

                if (!empty($categoryIds) && !in_array($catId, $categoryIds, true)) {
                    continue;
                }

                if (!isset($byCat[$catId])) {
                    $byCat[$catId] = [
                        'categoria'   => $catName,
                        'vendido_usd' => 0.0,
                        'vendido_bs'  => 0.0,
                        'costo_usd'   => 0.0,
                        'costo_bs'    => 0.0,
                    ];
                }

                $subtotalUsd   = (float) $item->subtotal_usd;
                $costProductId = $item->product?->stock_product_id ?? $item->product_id;
                $costPerKg     = (float) ($avgCosts[$costProductId] ?? 0);
                $qty           = (float) $item->quantity_value;

                $byCat[$catId]['vendido_usd'] += $subtotalUsd;
                $byCat[$catId]['vendido_bs']  += $subtotalUsd * $rate;
                $byCat[$catId]['costo_usd']   += $costPerKg * $qty;
                $byCat[$catId]['costo_bs']    += $costPerKg * $qty * $rate;

                $prodId   = $item->product_id;
                $prodName = $item->product?->name ?? 'Sin nombre';
                if (!isset($byProd[$catId][$prodId])) {
                    $byProd[$catId][$prodId] = [
                        'producto'    => $prodName,
                        'vendido_usd' => 0.0,
                        'vendido_bs'  => 0.0,
                        'costo_usd'   => 0.0,
                    ];
                }
                $byProd[$catId][$prodId]['vendido_usd'] += $subtotalUsd;
                $byProd[$catId][$prodId]['vendido_bs']  += $subtotalUsd * $rate;
                $byProd[$catId][$prodId]['costo_usd']   += $costPerKg * $qty;
            }
        }

        $categories = collect($byCat)->map(function (array $row, int|string $catId) use ($byProd): array {
            $utilidadUsd = $row['vendido_usd'] - $row['costo_usd'];
            $utilidadBs  = $row['vendido_bs']  - $row['costo_bs'];
            $margen      = $row['vendido_usd'] > 0
                ? round(($utilidadUsd / $row['vendido_usd']) * 100, 1)
                : 0.0;

            return [
                'categoria'    => $row['categoria'],
                'vendido_usd'  => round($row['vendido_usd'], 2),
                'vendido_bs'   => round($row['vendido_bs'], 2),
                'costo_usd'    => round($row['costo_usd'], 2),
                'utilidad_usd' => round($utilidadUsd, 2),
                'utilidad_bs'  => round($utilidadBs, 2),
                'margen_pct'   => $margen,
                'productos'    => collect($byProd[$catId] ?? [])->values()->all(),
            ];
        })->values()->all();

        $totVendidoUsd  = array_sum(array_column($categories, 'vendido_usd'));
        $totVendidoBs   = array_sum(array_column($categories, 'vendido_bs'));
        $totCostoUsd    = array_sum(array_column($categories, 'costo_usd'));
        $totUtilidadUsd = array_sum(array_column($categories, 'utilidad_usd'));
        $totUtilidadBs  = array_sum(array_column($categories, 'utilidad_bs'));
        $totMargen      = $totVendidoUsd > 0
            ? round(($totUtilidadUsd / $totVendidoUsd) * 100, 1)
            : 0.0;

        return [
            'categories' => $categories,
            'totals'     => [
                'vendido_usd'  => round($totVendidoUsd, 2),
                'vendido_bs'   => round($totVendidoBs, 2),
                'costo_usd'    => round($totCostoUsd, 2),
                'utilidad_usd' => round($totUtilidadUsd, 2),
                'utilidad_bs'  => round($totUtilidadBs, 2),
                'margen_pct'   => $totMargen,
            ],
        ];
    }

    // ─── Helper: HTML para PDF del día ────────────────────────────────────────

    private function buildDayPdfHtml($business, $cashier, string $fecha, array $categories, array $totals): string
    {
        $businessName = htmlspecialchars($business->name ?? '');
        $cashierName  = htmlspecialchars($cashier->name ?? '');
        $fechaLabel   = date('d/m/Y', strtotime($fecha));

        $logoHtml = '';
        if (!empty($business->logo_path)) {
            $logoAbs = storage_path('app/public/' . $business->logo_path);
            if (file_exists($logoAbs)) {
                $ext  = strtolower(pathinfo($logoAbs, PATHINFO_EXTENSION));
                $mime = match ($ext) { 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/jpeg' };
                $b64  = base64_encode(file_get_contents($logoAbs));
                $logoHtml = '<img src="data:' . $mime . ';base64,' . $b64 . '" style="height:50px;margin-bottom:6px;">';
            }
        }

        $rows = '';
        foreach ($categories as $cat) {
            $utilColor = $cat['utilidad_usd'] >= 0 ? '#16a34a' : '#dc2626';
            $catName   = htmlspecialchars($cat['categoria']);
            $rows .= '<tr>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . $catName . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right;">Bs. ' . number_format($cat['vendido_bs'], 2) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right;">$ ' . number_format($cat['costo_usd'], 2) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right;color:' . $utilColor . ';">$ ' . number_format($cat['utilidad_usd'], 2) . '</td>'
                . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $cat['margen_pct'] . '%</td>'
                . '</tr>';
        }

        $totalColor   = $totals['utilidad_usd'] >= 0 ? '#16a34a' : '#dc2626';
        $totVendidoBs = 'Bs. ' . number_format($totals['vendido_bs'], 2);
        $totCostoUsd  = '$ '   . number_format($totals['costo_usd'], 2);
        $totUtilidad  = '$ '   . number_format($totals['utilidad_usd'], 2);
        $totMargen    = $totals['margen_pct'] . '%';
        $generatedAt  = now()->format('d/m/Y H:i');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; margin: 0; padding: 20px; }
  h1 { font-size: 18px; margin: 4px 0; }
  h2 { font-size: 13px; font-weight: normal; color: #6b7280; margin: 2px 0 12px; }
  table { width: 100%; border-collapse: collapse; margin-top: 16px; }
  th { background: #111827; color: #fff; padding: 8px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; }
  th.r { text-align: right; }
  .total-row td { background: #f9fafb; font-weight: bold; border-top: 2px solid #111827; }
  .footer { margin-top: 24px; font-size: 10px; color: #9ca3af; text-align: right; }
</style></head>
<body>
  <div style="margin-bottom:16px;">
    {$logoHtml}
    <h1>{$businessName}</h1>
    <h2>Reporte del D&#237;a &#8212; {$fechaLabel}</h2>
    <p style="margin:0;font-size:11px;color:#6b7280;">Cajero: {$cashierName}</p>
  </div>
  <table>
    <thead>
      <tr>
        <th>Categor&#237;a</th>
        <th class="r">Vendido Bs.</th>
        <th class="r">Costo USD</th>
        <th class="r">Utilidad USD</th>
        <th class="r">Margen %</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
      <tr class="total-row">
        <td style="padding:8px;">TOTAL GENERAL</td>
        <td style="padding:8px;text-align:right;">{$totVendidoBs}</td>
        <td style="padding:8px;text-align:right;">{$totCostoUsd}</td>
        <td style="padding:8px;text-align:right;color:{$totalColor};">{$totUtilidad}</td>
        <td style="padding:8px;text-align:right;">{$totMargen}</td>
      </tr>
    </tbody>
  </table>
  <p class="footer">Generado el {$generatedAt} &#8212; SYNTImeat</p>
</body>
</html>
HTML;
    }

    // ─── Export Excel ─────────────────────────────────────────────────────────

    public function export(Request $request): BinaryFileResponse
    {
        $data = $request->validate([
            'tipo'         => ['required', 'in:ventas,inventario,cierres,pedidos'],
            'fecha_desde'  => ['nullable', 'date'],
            'fecha_hasta'  => ['nullable', 'date'],
        ]);

        $tipo       = $data['tipo'];
        $businessId = Auth::user()->business->id;

        $rows = match ($tipo) {
            'ventas'     => $this->salesRows($businessId, $data),
            'inventario' => $this->inventoryRows($businessId, $data),
            'cierres'    => $this->closingsRows($businessId, $data),
            'pedidos'    => $this->ordersRows($businessId, $data),
        };

        $export   = new GenericExport($rows['rows'], $rows['headings'], $tipo);
        $filename = "syntimeat_{$tipo}_" . now()->format('Y-m-d') . '.xlsx';

        return Excel::download($export, $filename);
    }

    // ─── Helpers privados para export ─────────────────────────────────────────

    private function salesRows(int $businessId, array $filters): array
    {
        $q = Sale::with(['salePayments.paymentMethod'])
            ->where('business_id', $businessId)
            ->withCount('items')
            ->orderByDesc('created_at');

        $this->applyDateFilter($q, 'created_at', $filters);

        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }

        $rows = $q->limit(5000)->get()->map(fn ($s) => [
            $s->ticket_number,
            $s->created_at?->format('d/m/Y H:i'),
            $s->client_name ?? '—',
            $s->items_count,
            $s->salePayments->map(fn ($p) => $p->paymentMethod?->name ?? '—')->implode(', '),
            (float) ($s->total_bs ?? 0),
            (float) $s->total_usd,
            $s->status,
        ]);

        return [
            'rows'     => $rows,
            'headings' => ['Ticket', 'Fecha', 'Cliente', 'Items', 'Métodos Pago', 'Total Bs.', 'Total USD', 'Estado'],
        ];
    }

    private function inventoryRows(int $businessId, array $filters): array
    {
        $q = InventoryEntry::with(['product.category'])
            ->where('business_id', $businessId)
            ->orderByDesc('entered_at');

        $this->applyDateFilter($q, 'entered_at', $filters);

        $rows = $q->limit(5000)->get()->map(fn ($e) => [
            $e->entered_at?->format('d/m/Y H:i'),
            $e->product?->name ?? '—',
            $e->product?->category?->name ?? '—',
            (float) $e->quantity_kg,
            (float) $e->waste_kg,
            round((float) $e->quantity_kg - (float) $e->waste_kg, 3),
            (float) ($e->cost_per_kg_usd ?? 0),
            $e->supplier ?? '—',
        ]);

        return [
            'rows'     => $rows,
            'headings' => ['Fecha', 'Producto', 'Categoría', 'Recibido kg', 'Merma kg', 'Neto kg', 'Costo USD/kg', 'Proveedor'],
        ];
    }

    private function closingsRows(int $businessId, array $filters): array
    {
        $q = CashRegister::where('business_id', $businessId)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at');

        $this->applyDateFilter($q, 'closed_at', $filters);

        $rows = $q->limit(1000)->get()->map(fn ($r) => [
            $r->name,
            $r->opened_at?->format('d/m/Y H:i'),
            $r->closed_at?->format('d/m/Y H:i'),
            (float) $r->opening_amount_usd,
            (float) ($r->expected_cash_usd ?? 0),
            (float) ($r->counted_cash_usd ?? 0),
            (float) ($r->difference_usd ?? 0),
        ]);

        return [
            'rows'     => $rows,
            'headings' => ['Caja', 'Apertura', 'Cierre', 'Apertura USD', 'Esperado USD', 'Contado USD', 'Diferencia USD'],
        ];
    }

    private function ordersRows(int $businessId, array $filters): array
    {
        $q = Order::withCount('items')
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        $this->applyDateFilter($q, 'created_at', $filters);

        $rows = $q->limit(5000)->get()->map(fn ($o) => [
            $o->created_at?->format('d/m/Y H:i'),
            $o->client_name,
            $o->client_type === 'internal' ? 'Interno' : 'Externo',
            $o->items_count,
            (float) $o->total_usd,
            $o->status,
        ]);

        return [
            'rows'     => $rows,
            'headings' => ['Fecha', 'Cliente', 'Tipo', 'Items', 'Total USD', 'Estado'],
        ];
    }

    private function applyDateFilter($query, string $column, array $filters): void
    {
        if (! empty($filters['fecha_desde'])) {
            $query->whereDate($column, '>=', $filters['fecha_desde']);
        }

        if (! empty($filters['fecha_hasta'])) {
            $query->whereDate($column, '<=', $filters['fecha_hasta']);
        }
    }
}

// ─── Export genérico para Maatwebsite Excel ───────────────────────────────────

class GenericExport implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private readonly \Illuminate\Support\Collection $rows,
        private readonly array $headings,
        private readonly string $title,
    ) {}

    public function collection(): \Illuminate\Support\Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return ucfirst($this->title);
    }
}
