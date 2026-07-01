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
        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $categories = Category::where('business_id', $businessId)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::where('business_id', $businessId)
            ->where('active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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

        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $query = Sale::with([
            'items.product.category',
            'salePayments.paymentMethod',
        ])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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

        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $query = InventoryEntry::with(['product.category'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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
            'sale_mode'     => $e->product?->sale_mode ?? 'weight',
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

        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $query = CashRegister::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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

        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $query = Order::withCount('items')
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
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
        $user        = Auth::user();
        $businessId  = $user->business->id;
        $branchId    = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        // branch_admin/cashier solo pueden ver su propia sucursal — ignorar branch_id del request
        if (in_array($user->role, ['branch_admin', 'cashier'], true)) {
            $branchId = $user->branch_id;
        }

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
        $cashier     = Auth::user();
        $business    = $cashier->business;
        $branchId    = isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        // branch_admin/cashier solo pueden ver su propia sucursal
        if (in_array($cashier->role, ['branch_admin', 'cashier'], true)) {
            $branchId = $cashier->branch_id;
        }

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

    // ─── Rendimiento por canal (Bóveda) ──────────────────────────────────────

    public function canalRendimiento(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $isAdmin    = in_array($user->role, ['super_admin', 'owner']);
        $branchId   = $isAdmin ? null : $user->branch_id;
        $fecha      = $request->input('fecha', now('America/Caracas')->toDateString());

        // Calcular el rango del ciclo del día actual (corte a las 19:00 Caracas)
        $ahora       = now('America/Caracas');
        $horaCorte   = 19;
        $inicioCiclo = $ahora->copy()->setTime($horaCorte, 0, 0);
        if ($ahora->hour < $horaCorte) {
            $inicioCiclo->subDay();
        }
        $finCiclo = $inicioCiclo->copy()->addDay();

        // Canales del ciclo actual: activas + pendientes + procesadas hoy
        $canales = \App\Models\BovedaEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereIn('product_type', ['RES - Medio Canal', 'CERDO - Canal', 'POLLO - Entero Congelado', 'JAMON - Pierna Sellado'])
            ->where('entered_at', '>=', $inicioCiclo)
            ->where('entered_at', '<', $finCiclo)
            ->orderByDesc('entered_at')
            ->get()
            ->map(function ($canal) {
                if ((float) $canal->kg_surtido_vitrina === 0.0) {
                    $canal->estado = 'pendiente';
                } elseif ($canal->closed_at === null) {
                    $canal->estado = 'activa';
                } else {
                    $canal->estado = 'procesada';
                }
                return $canal;
            });

        // Ingresos del día por product_id: una query para todas las canales
        $ingresosDelDia = \DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->where('sales.status', 'paid')
            ->whereDate('sales.accounting_date', $fecha)
            ->select(
                'sale_items.product_id',
                \DB::raw('SUM(sale_items.subtotal_usd) as total_usd'),
                \DB::raw('SUM(sale_items.subtotal_bs)  as total_bs')
            )
            ->groupBy('sale_items.product_id')
            ->get()
            ->keyBy('product_id');

        // Kg despiezado total por product_id en TODAS las canales (para calcular proporción)
        $kgTotalPorProducto = \DB::table('inventory_entries')
            ->where('business_id', $businessId)
            ->whereIn('boveda_entry_id', $canales->pluck('id'))
            ->where('quantity_kg', '>', 0)
            ->where('location', 'vitrina')
            ->select('product_id', \DB::raw('SUM(quantity_kg) as total_kg'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // Stock REAL del pool por product_id (única fuente de verdad).
        // Se calcula UNA sola vez: el remanente es del pool, no de cada canal.
        $stockIdsPool = \App\Models\Product::whereIn('id', $kgTotalPorProducto->keys())
            ->get()
            ->map(fn ($p) => $p->stock_product_id ?? $p->id)
            ->unique()
            ->values();

        $stockRealPorPool = \App\Models\InventoryEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('location', 'vitrina')
            ->whereIn('product_id', $stockIdsPool)
            ->whereDate('entered_at', '<=', $fecha)
            ->select('product_id', \DB::raw('SUM(net_kg) as stock_kg'))
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        $resultado = $canales->map(function ($canal) use ($fecha, $businessId, $branchId, $ingresosDelDia, $kgTotalPorProducto, $stockRealPorPool) {
            // Productos despiezados de esta canal: inventory_entries con boveda_entry_id = canal.id,
            // quantity_kg > 0, location = 'vitrina' (las entradas positivas de cortes)
            $despiezadosRaw = \App\Models\InventoryEntry::where('business_id', $businessId)
                ->where('boveda_entry_id', $canal->id)
                ->where('quantity_kg', '>', 0)
                ->where('location', 'vitrina')
                ->select('product_id', \DB::raw('SUM(quantity_kg) as kg_despiece'))
                ->groupBy('product_id')
                ->get();

            // Mapear a productos con sus datos
            $productos = $despiezadosRaw->map(function ($entry) use ($fecha, $businessId, $branchId, $ingresosDelDia, $kgTotalPorProducto, $stockRealPorPool) {
                $prod = \App\Models\Product::find($entry->product_id);
                if (!$prod) {
                    return null;
                }

                // Pool: si tiene stock_product_id usar ese para calcular stock
                $stockId = $prod->stock_product_id ?? $prod->id;

                // Vendido HOY (entradas negativas de venta del día)
                $vendidoHoy = (float) \App\Models\InventoryEntry::where('business_id', $businessId)
                    ->where('product_id', $stockId)
                    ->whereDate('entered_at', $fecha)
                    ->where('quantity_kg', '<', 0)
                    ->sum('quantity_kg');

                // Ingresos de venta HOY
                // Buscar productos que usan este como pool (stock_product_id) + el producto mismo
                $productIds = \App\Models\Product::where('business_id', $businessId)
                    ->where(function ($q) use ($prod) {
                        $q->where('id', $prod->id)
                          ->orWhere('stock_product_id', $prod->id);
                    })->pluck('id');

                // Ingresos totales hoy para este grupo de productos (ya pre-calculados)
                $totalUsdProducto = $productIds->sum(fn ($pid) => (float) ($ingresosDelDia[$pid]->total_usd ?? 0));
                $totalBsProducto  = $productIds->sum(fn ($pid) => (float) ($ingresosDelDia[$pid]->total_bs  ?? 0));

                // Atribución proporcional: esta canal recibe ingresos en proporción a sus kg despiezados
                $kgEstaCanal   = (float) ($entry->kg_despiece ?? 0);
                $kgTotalGlobal = (float) ($kgTotalPorProducto[$entry->product_id]->total_kg ?? 0);
                $proporcion    = $kgTotalGlobal > 0 ? $kgEstaCanal / $kgTotalGlobal : 1.0;
                $ingresosHoy   = $totalUsdProducto * $proporcion;
                $ingresosHoyBs = $totalBsProducto  * $proporcion;

                // Remanente: stock REAL del pool repartido por proporción de despiece de esta canal.
                // La suma de todas las canales reconcilia con el pool real — sin doble conteo.
                $poolStock   = (float) ($stockRealPorPool[$stockId]->stock_kg ?? 0);
                $kgRemanente = max(0, $poolStock * $proporcion);

                return [
                    'product_id'     => $prod->id,
                    'nombre'         => $prod->name,
                    'kg_despiece'    => round((float) $entry->kg_despiece, 3),
                    'kg_vendido_hoy' => round(abs((float) $vendidoHoy), 3),
                    'kg_remanente'   => round(max(0, $kgRemanente), 3),
                    'ingresos_usd'   => round((float) $ingresosHoy, 2),
                    'ingresos_bs'    => round((float) $ingresosHoyBs, 2),
                    'precio_kg'      => round((float) $prod->price_per_kg_usd, 2),
                ];
            })->filter()->values();

            $costoCanal     = (float) $canal->costo_usd;
            $ingresosTotal  = $productos->sum('ingresos_usd');
            $ingresosTotalBs = $productos->sum('ingresos_bs');
            $kgRemanente    = $productos->sum('kg_remanente');
            $precioPromedio = $productos->avg('precio_kg') ?? 0;
            $valorRemanente = round($kgRemanente * $precioPromedio, 2);
            $utilidadReal   = round($ingresosTotal - $costoCanal, 2);
            $utilidadTotal  = round($utilidadReal + $valorRemanente, 2);

            // Auto-cierre: canal sin remanente, completamente surtida y con ventas
            $kgSurtido = (float) $canal->kg_surtido_vitrina;
            $kgEntrada = (float) $canal->kg_entrada;
            if ($kgRemanente <= 0 && $kgSurtido >= $kgEntrada && $ingresosTotal > 0) {
                $canal->update(['closed_at' => now()]);
                return null;
            }

            return [
                'boveda_entry_id' => $canal->id,
                'tipo'            => $canal->product_type,
                'descripcion'     => $canal->description,
                'fecha_entrada'   => $canal->entered_at->format('d/m/Y'),
                'kg_entrada'      => round((float) $canal->kg_entrada, 2),
                'costo_usd'       => $costoCanal,
                'estado'          => $canal->estado,
                'costo_kg'        => $canal->kg_entrada > 0 ? round($costoCanal / $canal->kg_entrada, 4) : 0,
                'ingresos_usd'    => round($ingresosTotal, 2),
                'ingresos_bs'     => round($ingresosTotalBs, 2),
                'utilidad_real'   => $utilidadReal,
                'kg_remanente'    => round($kgRemanente, 3),
                'valor_remanente' => $valorRemanente,
                'utilidad_total'  => $utilidadTotal,
                'margen_pct'      => $costoCanal > 0 ? round($utilidadReal / $costoCanal * 100, 1) : 0,
                'recuperado_pct'  => $costoCanal > 0 ? round(($ingresosTotal / $costoCanal) * 100, 1) : 0,
                'productos'       => $productos,
            ];
        })->filter()->values();

        // Remanente real del pool (verdad física, calculado una sola vez)
        $remananteTotal = round((float) $stockRealPorPool->sum('stock_kg'), 3);

        return response()->json([
            'canales'         => $resultado,
            'remanente_total' => $remananteTotal,
        ]);
    }

    // ─── Helper: agregación consolidada por sucursal ─────────────────────────

    public function cerrarCanal(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        abort_unless(
            in_array($user->role, ['owner', 'super_admin', 'admin', 'branch_admin'], true),
            403,
            'Sin permiso para cerrar canales.'
        );

        $data = $request->validate([
            'boveda_entry_id' => ['required', 'integer', 'exists:boveda_entries,id'],
        ]);

        $entry = \App\Models\BovedaEntry::where('id', $data['boveda_entry_id'])
            ->where('business_id', $user->business_id)
            ->firstOrFail();

        $entry->update(['closed_at' => now()]);

        return response()->json(['message' => 'Canal cerrado y enviado al historial.']);
    }

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
            $margen      = $costoUsd > 0 ? round(($utilidadUsd / $costoUsd) * 100, 1) : 0.0;
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
            ->selectRaw('categories.name as categoria, SUM(sale_items.subtotal_bs) as vendido_bs')
            ->orderByDesc('vendido_bs')
            ->get()
            ->map(fn ($r) => [
                'categoria'  => $r->categoria,
                'vendido_bs' => round((float) $r->vendido_bs, 2),
            ])
            ->values()
            ->all();

        $catOrder = ['Res', 'Pollo', 'Cerdo', 'Charcutería', 'Trastes', 'Víveres'];
        usort($categorias, function (array $a, array $b) use ($catOrder): int {
            $ai = array_search($a['categoria'], $catOrder);
            $bi = array_search($b['categoria'], $catOrder);
            $ai = $ai === false ? PHP_INT_MAX : $ai;
            $bi = $bi === false ? PHP_INT_MAX : $bi;
            return $ai <=> $bi;
        });

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
        // Incluir paid y pending: ventas devengadas en la fecha (devengo vs cobro)
        $sales = Sale::where('business_id', $businessId)
            ->whereIn('status', ['paid', 'pending'])
            ->whereDate('accounting_date', $fecha)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with(['items.product.category'])
            ->get(['id', 'rate_used', 'status', 'total_bs', 'total_usd']);

        // Costo real: costo total bóveda del día / kg totales entrada
        $bovedaDia = DB::table('boveda_entries')
            ->where('business_id', $businessId)
            ->whereDate('entered_at', '<=', $fecha)
            ->whereNotNull('costo_usd')
            ->where('costo_usd', '>', 0)
            ->where('kg_entrada', '>', 0)
            ->selectRaw('SUM(costo_usd) as total_costo, SUM(kg_entrada) as total_kg')
            ->first();

        $costoPorKgGlobal = ($bovedaDia && (float) $bovedaDia->total_kg > 0)
            ? (float) $bovedaDia->total_costo / (float) $bovedaDia->total_kg
            : 0.0;

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

                $subtotalUsd = (float) $item->subtotal_usd;
                $subtotalBs  = (float) $item->subtotal_bs;
                $qty         = (float) $item->quantity_value;

                $byCat[$catId]['vendido_usd'] += $subtotalUsd;
                $byCat[$catId]['vendido_bs']  += $subtotalBs;
                $byCat[$catId]['costo_usd']   += $costoPorKgGlobal * $qty;
                $byCat[$catId]['costo_bs']    += $costoPorKgGlobal * $qty * $rate;

                $prodId   = $item->product_id;
                $prodName = $item->product?->name ?? 'Sin nombre';
                if (!isset($byProd[$catId][$prodId])) {
                    $byProd[$catId][$prodId] = [
                        'producto'    => $prodName,
                        'sale_mode'   => $item->product?->sale_mode ?? 'weight',
                        'kg'          => 0.0,
                        'vendido_usd' => 0.0,
                        'vendido_bs'  => 0.0,
                        'costo_usd'   => 0.0,
                    ];
                }
                $byProd[$catId][$prodId]['kg']          += $qty;
                $byProd[$catId][$prodId]['vendido_usd'] += $subtotalUsd;
                $byProd[$catId][$prodId]['vendido_bs']  += $subtotalBs;
                $byProd[$catId][$prodId]['costo_usd']   += $costoPorKgGlobal * $qty;
            }
        }

        $categories = collect($byCat)->map(function (array $row, int|string $catId) use ($byProd): array {
            $utilidadUsd = $row['vendido_usd'] - $row['costo_usd'];
            $utilidadBs  = $row['vendido_bs']  - $row['costo_bs'];
            $margen      = $row['costo_usd'] > 0
                ? round(($utilidadUsd / $row['costo_usd']) * 100, 1)
                : 0.0;

            return [
                'categoria'    => $row['categoria'],
                'vendido_usd'  => round($row['vendido_usd'], 2),
                'vendido_bs'   => round($row['vendido_bs'], 2),
                'costo_usd'    => round($row['costo_usd'], 2),
                'utilidad_usd' => round($utilidadUsd, 2),
                'utilidad_bs'  => round($utilidadBs, 2),
                'margen_pct'   => $margen,
                'productos'    => collect($byProd[$catId] ?? [])
                    ->map(fn (array $p): array => [
                        'producto'    => $p['producto'],
                        'sale_mode'   => $p['sale_mode'],
                        'kg'          => round($p['kg'], 3),
                        'vendido_usd' => round($p['vendido_usd'], 2),
                        'vendido_bs'  => round($p['vendido_bs'], 2),
                        'costo_usd'   => round($p['costo_usd'], 2),
                    ])
                    ->sortByDesc('vendido_usd')
                    ->values()
                    ->all(),
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

        // Totales separados: cobrado (paid) vs devengado (paid+pending)
        $cobradoBs  = round((float) $sales->where('status', 'paid')->sum('total_bs'), 2);
        $cobradoUsd = round((float) $sales->where('status', 'paid')->sum('total_usd'), 2);
        $creditoBs  = round((float) $sales->where('status', 'pending')->sum('total_bs'), 2);
        $creditoUsd = round((float) $sales->where('status', 'pending')->sum('total_usd'), 2);

        // Ticket stats por status
        $ticketStats = DB::table('sales')
            ->where('business_id', $businessId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('accounting_date', $fecha)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return [
            'categories' => $categories,
            'fecha'      => $fecha,
            'totals'     => [
                'vendido_usd'        => round($totVendidoUsd, 2),
                'vendido_bs'         => round($totVendidoBs, 2),
                'cobrado_usd'        => $cobradoUsd,
                'cobrado_bs'         => $cobradoBs,
                'credito_usd'        => $creditoUsd,
                'credito_bs'         => $creditoBs,
                'costo_usd'          => round($totCostoUsd, 2),
                'utilidad_usd'       => round($totUtilidadUsd, 2),
                'utilidad_bs'        => round($totUtilidadBs, 2),
                'margen_pct'         => $totMargen,
                'tickets_paid'       => (int) ($ticketStats->get('paid')?->total ?? 0),
                'tickets_cancelled'  => (int) ($ticketStats->get('cancelled')?->total ?? 0),
                'tickets_pending'    => (int) ($ticketStats->get('pending')?->total ?? 0),
            ],
        ];
    }

    // ─── Helper: HTML para PDF del día ────────────────────────────────────────

    private function buildDayPdfHtml($business, $cashier, string $fecha, array $categories, array $totals): string
    {
        $businessName = htmlspecialchars($business->name ?? '');
        $cashierName  = htmlspecialchars($cashier->name ?? '');
        $branchName   = htmlspecialchars($cashier->branch?->name ?? '');
        $fechaLabel   = date('d/m/Y', strtotime($fecha));
        $rate         = (float) $this->rates->getTodayRate();
        $rateLabel    = number_format($rate, 2);
        $generatedAt  = now()->format('d/m/Y H:i');

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

        $tdP = 'padding:4px 8px;border:1px solid #ddd;';
        $rows = '';
        foreach ($categories as $cat) {
            $catName = htmlspecialchars($cat['categoria']);
            $rows .= '<tr><td colspan="4" style="padding:5px 8px;background:#f0f0f0;font-weight:bold;'
                   . 'font-size:11px;border:1px solid #ccc;letter-spacing:0.02em;">'
                   . strtoupper($catName) . '</td></tr>';

            foreach ($cat['productos'] as $prod) {
                $prodName = htmlspecialchars($prod['producto']);
                $qty = ($prod['sale_mode'] ?? 'weight') === 'unit'
                    ? number_format((float) $prod['kg'], 0) . ' und'
                    : number_format((float) $prod['kg'], 3) . ' kg';
                $rows .= '<tr>'
                    . '<td style="' . $tdP . 'padding-left:20px;">' . $prodName . '</td>'
                    . '<td style="' . $tdP . 'text-align:right;">' . $qty . '</td>'
                    . '<td style="' . $tdP . 'text-align:right;">Bs. ' . number_format((float) $prod['vendido_bs'], 2) . '</td>'
                    . '<td style="' . $tdP . 'text-align:right;">$ ' . number_format((float) $prod['vendido_usd'], 2) . '</td>'
                    . '</tr>';
            }

            $catKg = array_sum(array_column($cat['productos'], 'kg'));
            $rows .= '<tr style="background:#e8e8e8;font-weight:bold;">'
                . '<td style="' . $tdP . '">Subtotal ' . $catName . '</td>'
                . '<td style="' . $tdP . 'text-align:right;">' . number_format($catKg, 3) . ' kg</td>'
                . '<td style="' . $tdP . 'text-align:right;">Bs. ' . number_format((float) $cat['vendido_bs'], 2) . '</td>'
                . '<td style="' . $tdP . 'text-align:right;">$ ' . number_format((float) $cat['vendido_usd'], 2) . '</td>'
                . '</tr>'
                . '<tr><td colspan="4" style="height:4px;border:none;"></td></tr>';
        }

        $ticketsPaid      = (int) ($totals['tickets_paid'] ?? 0);
        $ticketsCancelled = (int) ($totals['tickets_cancelled'] ?? 0);
        $ticketsPending   = (int) ($totals['tickets_pending'] ?? 0);

        // Totales contables
        $cobradoBs  = 'Bs. ' . number_format((float) ($totals['cobrado_bs']  ?? $totals['vendido_bs']),  2);
        $cobradoUsd = '$ '   . number_format((float) ($totals['cobrado_usd'] ?? $totals['vendido_usd']), 2);
        $creditoBs  = 'Bs. ' . number_format((float) ($totals['credito_bs']  ?? 0), 2);
        $creditoUsd = '$ '   . number_format((float) ($totals['credito_usd'] ?? 0), 2);
        $devBs      = 'Bs. ' . number_format((float)  $totals['vendido_bs'],  2);
        $devUsd     = '$ '   . number_format((float)  $totals['vendido_usd'], 2);

        $haCredito = ($totals['credito_bs'] ?? 0) > 0;
        $creditRow = $haCredito
            ? '<tr style="background:#fff8e1;">'
              . '<td style="' . $tdP . 'color:#b45309;">Cr&#233;ditos despachados (pendiente cobro)</td>'
              . '<td style="' . $tdP . 'text-align:right;"></td>'
              . '<td style="' . $tdP . 'text-align:right;color:#b45309;">' . $creditoBs . '</td>'
              . '<td style="' . $tdP . 'text-align:right;color:#b45309;">' . $creditoUsd . '</td>'
              . '</tr>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><style>
  body { font-family: Arial, sans-serif; font-size: 10px; color: #111; margin: 0; padding: 16px; }
  h1 { font-size: 16px; margin: 2px 0; }
  h2 { font-size: 11px; font-weight: normal; color: #555; margin: 2px 0 8px; }
  table { width: 100%; border-collapse: collapse; margin-top: 12px; }
  .total-row td { background: #1a1a2e; color: #fff; font-weight: bold; padding: 6px 8px; }
  .footer { margin-top: 8px; font-size: 9px; color: #777; }
  .sign { margin-top: 36px; border-top: 1px solid #aaa; width: 220px; padding-top: 4px; font-size: 9px; color: #555; }
  .note { font-size: 8px; color: #888; margin-top: 6px; font-style: italic; }
</style></head>
<body>
  <div style="margin-bottom:10px;">
    {$logoHtml}
    <h1>{$businessName}</h1>
    <h2>Reporte de Ventas &mdash; {$fechaLabel}</h2>
    <p style="margin:0;font-size:10px;color:#555;">Tasa BCV: Bs.&nbsp;{$rateLabel}/USD &nbsp;|&nbsp; Sucursal: {$branchName} &nbsp;|&nbsp; Cajero: {$cashierName}</p>
  </div>
  <table>
    <thead>
      <tr style="background:#333;color:#fff;">
        <th style="padding:5px 8px;text-align:left;border:1px solid #555;">Producto</th>
        <th style="padding:5px 8px;text-align:right;border:1px solid #555;">Cantidad</th>
        <th style="padding:5px 8px;text-align:right;border:1px solid #555;">Total Bs.</th>
        <th style="padding:5px 8px;text-align:right;border:1px solid #555;">Total USD</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
      <tr style="background:#2d5016;color:#fff;font-weight:bold;">
        <td style="padding:5px 8px;">Ventas Cobradas &mdash; {$ticketsPaid} tickets</td>
        <td></td>
        <td style="padding:5px 8px;text-align:right;">{$cobradoBs}</td>
        <td style="padding:5px 8px;text-align:right;">{$cobradoUsd}</td>
      </tr>
      {$creditRow}
      <tr class="total-row">
        <td>Total Devengado del D&#237;a</td>
        <td></td>
        <td style="text-align:right;">{$devBs}</td>
        <td style="text-align:right;">{$devUsd}</td>
      </tr>
    </tbody>
  </table>
  <p class="footer">Cobrados: {$ticketsPaid} &nbsp;|&nbsp; Anulados: {$ticketsCancelled} &nbsp;|&nbsp; Cr&#233;ditos/Delivery pendiente: {$ticketsPending}</p>
  <p class="note">Las ventas a cr&#233;dito se registran en la fecha de despacho (devengo), independientemente de cu&#225;ndo se cobren.</p>
  <p class="footer">Generado el {$generatedAt} &mdash; SYNTImeat</p>
  <div class="sign">Firma responsable: ___________________________</div>
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

    // ─── Historial de canales cerradas ───────────────────────────────────────

    public function valorVitrinaryPage(): \Inertia\Response
    {
        return \Inertia\Inertia::render('Reports/ValorVitrina');
    }

    public function valorVitrina(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        // Invertido = SUM(costo_usd) de boveda_entries del ciclo actual (closed_at >= ayer)
        // Keyword map para clasificar boveda_entries por categoria
        $keywordMap = [
            'res'       => 'Res',    'vaca'    => 'Res',   'novillo' => 'Res',   'canal res' => 'Res',
            'cerdo'     => 'Cerdo',  'cochino' => 'Cerdo', 'canal cerdo' => 'Cerdo',
            'pollo'     => 'Pollo',
            'jamon'     => 'Charcutería', 'jamón' => 'Charcutería', 'salchicha' => 'Charcutería',
            'mortadela' => 'Charcutería', 'tocineta' => 'Charcutería', 'queso' => 'Charcutería',
            'pavo'      => 'Charcutería', 'chorizo'  => 'Charcutería', 'chuleta' => 'Charcutería',
            'carne total' => 'Res', 'remanente' => 'Res',
        ];

        $bovedas = DB::table('boveda_entries as be')
            ->where('be.business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('be.branch_id', $branchId))
            ->where('be.closed_at', '>=', now('America/Caracas')->subDay()->startOfDay())
            ->select('be.product_type', 'be.costo_usd')
            ->get();

        $bovedaInvertido = collect();
        foreach ($bovedas as $b) {
            $lower = mb_strtolower($b->product_type);
            $cat = null;
            foreach ($keywordMap as $kw => $catName) {
                if (str_contains($lower, $kw)) { $cat = $catName; break; }
            }
            if ($cat) {
                $bovedaInvertido[$cat] = ($bovedaInvertido[$cat] ?? 0) + (float) $b->costo_usd;
            }
        }

        // Ventas del ciclo por categoria
        $ventasPorCategoria = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('s.business_id', $businessId)
            ->whereIn('s.status', ['paid', 'pending'])
            ->whereDate('s.accounting_date', now('America/Caracas')->toDateString())
            ->when($branchId, fn($q) => $q->where('s.branch_id', $branchId))
            ->groupBy('c.id', 'c.name')
            ->select('c.id as categoria_id', 'c.name as categoria', DB::raw('SUM(si.subtotal_usd) as vendido'))
            ->get()
            ->keyBy('categoria');

        // Stock disponible por producto
        $rows = DB::table('inventory_entries as ie')
            ->join('products as p', 'p.id', '=', 'ie.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('ie.business_id', $businessId)
            ->where('ie.location', 'vitrina')
            ->when($branchId, fn($q) => $q->where('ie.branch_id', $branchId))
            ->groupBy('p.id', 'p.name', 'c.id', 'c.name', 'p.price_per_kg_usd', 'p.cost_per_kg_usd')
            ->havingRaw('SUM(ie.quantity_kg - ie.waste_kg) > 0')
            ->orderBy('c.name')
            ->select([
                'c.id as categoria_id',
                'c.name as categoria',
                'p.id as product_id',
                'p.name as producto',
                DB::raw('ROUND(SUM(ie.quantity_kg - ie.waste_kg), 3) as kg_disponible'),
                DB::raw('p.cost_per_kg_usd as costo_kg'),
                DB::raw('p.price_per_kg_usd as precio_venta_kg'),
            ])
            ->get();

        // Invertido real por categoria via inventory_entries — fallback cuando la categoria
        // no tiene boveda_entries (productos que entran directo a vitrina, ej. Trastes, Viveres)
        $invertidoPorCategoria = DB::table('inventory_entries as ie')
            ->join('products as p', 'p.id', '=', 'ie.product_id')
            ->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('ie.business_id', $businessId)
            ->where('ie.location', 'vitrina')
            ->where('ie.quantity_kg', '>', 0)
            ->whereNotNull('ie.cost_per_kg_usd')
            ->where('ie.cost_per_kg_usd', '>', 0)
            ->when($branchId, fn($q) => $q->where('ie.branch_id', $branchId))
            ->groupBy('c.id', 'c.name')
            ->select('c.name as categoria', DB::raw('ROUND(SUM(ie.quantity_kg * ie.cost_per_kg_usd), 2) as invertido_real'))
            ->get()
            ->keyBy('categoria');

        $categorias = $rows->groupBy('categoria')->map(function($items, $cat) use ($bovedaInvertido, $invertidoPorCategoria) {
            $invertidoBoveda = (float) ($bovedaInvertido[$cat] ?? 0);
            $invertido = $invertidoBoveda > 0
                ? $invertidoBoveda
                : (float) ($invertidoPorCategoria->get($cat)?->invertido_real ?? 0);
            $ventaPotencial = round($items->sum(fn ($p) => (float) $p->kg_disponible * (float) ($p->precio_venta_kg ?? 0)), 2);
            $utilidad  = round($ventaPotencial - $invertido, 2);
            return [
                'categoria'          => $cat,
                'total_invertido'    => round((float) $invertido, 2),
                'total_venta'        => $ventaPotencial,
                'utilidad_potencial' => $utilidad,
                'kg_total'           => round($items->sum('kg_disponible'), 3),
                'productos'          => $items->map(function ($p) {
                    $invertidoProducto = round((float) $p->kg_disponible * (float) ($p->costo_kg ?? 0), 2);
                    $ventaPotencial    = round((float) $p->kg_disponible * (float) ($p->precio_venta_kg ?? 0), 2);
                    $p->total_invertido       = $invertidoProducto;
                    $p->total_venta_potencial = $ventaPotencial;
                    $p->utilidad_potencial    = round($ventaPotencial - $invertidoProducto, 2);
                    return $p;
                })->values(),
            ];
        })->values();

        $totalInvertido = round($categorias->sum('total_invertido'), 2);
        $totalVenta     = round($categorias->sum('total_venta'), 2);

        return response()->json([
            'categorias'         => $categorias,
            'total_invertido'    => $totalInvertido,
            'total_venta'        => $totalVenta,
            'utilidad_potencial' => round($totalVenta - $totalInvertido, 2),
        ]);
    }

    public function canalHistorial(Request $request): \Illuminate\Http\JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $isAdmin    = in_array($user->role, ['super_admin', 'owner']);
        $branchId   = $isAdmin ? null : $user->branch_id;

        $canales = \App\Models\BovedaEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNotNull('closed_at')
            ->whereIn('product_type', ['RES - Medio Canal', 'CERDO - Canal', 'POLLO - Entero Congelado', 'JAMON - Pierna Sellado'])
            ->orderByDesc('closed_at')
            ->get()
            ->map(fn ($c) => [
                'boveda_entry_id' => $c->id,
                'tipo'            => $c->product_type,
                'descripcion'     => $c->description,
                'fecha_entrada'   => $c->entered_at->format('d/m/Y'),
                'fecha_cierre'    => $c->closed_at->format('d/m/Y'),
                'kg_entrada'      => round((float) $c->kg_entrada, 2),
                'kg_surtido'      => round((float) $c->kg_surtido_vitrina, 2),
                'costo_usd'       => round((float) $c->costo_usd, 2),
                'supplier'        => $c->supplier,
            ]);

        return response()->json(['canales' => $canales]);
    }

}