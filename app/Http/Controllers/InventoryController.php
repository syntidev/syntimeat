<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(): Response
    {
        $business   = Auth::user()->business;
        $businessId = $business->id;

        // ─── Productos activos con categoría y sucursal ───────────────────────
        $user     = Auth::user();
        $branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $products = Product::with(['category', 'branch', 'stockPool'])
            ->where('business_id', $businessId)
            ->where('active', true)
            ->where('location', '!=', 'boveda')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->orderBy('sort_order')
            ->get();

        // ─── Categorías para el formulario ────────────────────────────────────
        $categories = Category::where('business_id', $businessId)
            ->orderBy('sort_order')
            ->get();

        // ─── Entradas de hoy ──────────────────────────────────────────────────
        $todayEntries = InventoryEntry::with(['product.category', 'creator'])
            ->where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->whereDate('entered_at', today())
            ->orderByDesc('entered_at')
            ->get();

        // ─── Stock disponible: SUM(net_kg) de inventory_entries ────────────────
        // Las ventas ya están reflejadas como InventoryEntry negativas en pay()
        // No restar stockOut — sería doble descuento
        $stockIn = InventoryEntry::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->selectRaw('product_id, SUM(net_kg) as total_net')
            ->groupBy('product_id')
            ->pluck('total_net', 'product_id');

        $stockMap = [];
        foreach ($products as $product) {
            if ($product->stock_product_id) {
                $stockMap[$product->id] = round((float) ($stockIn[$product->stock_product_id] ?? 0), 3);
            } else {
                $stockMap[$product->id] = round((float) ($stockIn[$product->id] ?? 0), 3);
            }
        }

        // ─── Último ingreso por producto ──────────────────────────────────────
        $lastEntryMap = InventoryEntry::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->selectRaw('product_id, MAX(entered_at) as last_at')
            ->groupBy('product_id')
            ->pluck('last_at', 'product_id');


        // Ultimo costo de compra por producto (desde la columna products.cost_per_kg_usd)
        $lastCostMap = $products->pluck('cost_per_kg_usd', 'id')
            ->map(fn ($v) => $v ? (float) $v : null);
        // ─── KPIs — solo entradas positivas (excluye descuentos de Fábrica) ──────
        $positiveEntries = $todayEntries->filter(fn (InventoryEntry $e) => (float) $e->quantity_kg > 0);
        $kgToday    = $positiveEntries->sum(fn (InventoryEntry $e) => (float) $e->net_kg);
        $totalStock = array_sum($stockMap);
        $costToday  = $positiveEntries->sum(
            fn (InventoryEntry $e) => (float) $e->quantity_kg * (float) ($e->cost_per_kg_usd ?? 0)
        );
        $belowMin = $products->filter(
            fn (Product $p) => (float) $p->min_stock > 0
                && ($stockMap[$p->id] ?? 0) < (float) $p->min_stock
        )->count();

        $products = $products->map(function (Product $p) {
            return [
                ...$p->toArray(),
                'shared_pool_name' => $p->stock_product_id && $p->stockPool
                    ? $p->stockPool->name
                    : null,
            ];
        });

        return Inertia::render('Inventory/Index', [
            'products'     => $products,
            'categories'   => $categories,
            'todayEntries' => $todayEntries,
            'stockMap'     => $stockMap,
            'lastEntryMap' => $lastEntryMap,
            'lastCostMap'  => $lastCostMap,
            'kpis'         => [
                'kg_today'    => round($kgToday, 3),
                'total_stock' => round($totalStock, 3),
                'below_min'   => $belowMin,
                'cost_today'  => round($costToday, 2),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id'      => ['required', 'integer', 'exists:products,id'],
            'quantity_kg'     => ['required', 'numeric', 'min:0.001', 'max:9999'],
            'waste_kg'        => ['nullable', 'numeric', 'min:0'],
            'cost_per_kg_usd' => ['nullable', 'numeric', 'min:0'],
            'supplier'        => ['nullable', 'string', 'max:150'],
            'entered_at'      => ['required', 'date'],
        ]);

        $user       = Auth::user();
        $businessId = $user->business->id;
        $userId     = $user->id;
        $branchId = $user->branch_id
            ?? session('current_branch_id')
            ?? \App\Models\Branch::where('business_id', $businessId)->orderBy('id')->value('id');

        $product = Product::where('id', $data['product_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($product->location === 'boveda') {
            return back()->withErrors(['product_id' => 'Productos bóveda no se pueden agregar al inventario directamente.']);
        }

        $isUnit  = $product->sale_mode === 'unit';
        if ($isUnit) {
            $data['quantity_kg'] = (int) round((float) $data['quantity_kg']);
        }
        $wasteKg = $isUnit ? 0.0 : (float) ($data['waste_kg'] ?? 0);

        // Para productos por peso: waste no puede superar la cantidad
        if (! $isUnit && $wasteKg >= (float) $data['quantity_kg']) {
            return back()->withErrors(['waste_kg' => 'La merma no puede ser igual o mayor al total recibido.']);
        }

        $entry = InventoryEntry::create([
            'business_id'     => $businessId,
            'branch_id'       => $branchId,
            'product_id'      => $data['product_id'],
            'quantity_kg'     => $data['quantity_kg'],
            'waste_kg'        => $wasteKg,
            'cost_per_kg_usd' => $data['cost_per_kg_usd'] ?? null,
            'supplier'        => $data['supplier'] ?? null,
            'entered_at'      => $data['entered_at'],
            'created_by'      => $userId,
        ]);

        // Actualizar costo de referencia en el producto si viene costo nuevo
        if (! empty($data['cost_per_kg_usd']) && (float) $data['cost_per_kg_usd'] > 0) {
            $product->update(['cost_per_kg_usd' => $data['cost_per_kg_usd']]);
        }

        // Propagar cost_per_kg_usd al producto para que reportes reflejen costo real
        if (! empty($data['cost_per_kg_usd'])) {
            $product->update(['cost_per_kg_usd' => $data['cost_per_kg_usd']]);
        }

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'action'      => 'inventory.entry',
            'model_type'  => 'InventoryEntry',
            'model_id'    => $entry->id,
            'new_values'  => $entry->toArray(),
            'ip_address'  => $request->ip(),
        ]);

        return back()->with('success', 'Entrada registrada correctamente.');
    }

    public function adjust(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'stock_real' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        $user       = Auth::user();
        $businessId = $user->business->id;
        $userId     = $user->id;
        $branchId   = $user->branch_id
            ?? session('current_branch_id')
            ?? \App\Models\Branch::where('business_id', $businessId)->orderBy('id')->value('id');

        $product = Product::where('id', $data['product_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        $isGlobalRole = in_array($user->role, ['owner', 'super_admin'], true);
        abort_unless(
            $isGlobalRole || $branchId === null || $product->branch_id === $branchId,
            403,
            'Producto no pertenece a esta sucursal.'
        );

        $stockActual = (float) InventoryEntry::where('business_id', $businessId)
            ->where('product_id', $data['product_id'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->sum('net_kg');

        $diferencia = round((float) $data['stock_real'] - $stockActual, 3);

        if (abs($diferencia) < 0.001) {
            return back();
        }

        $entry = InventoryEntry::create([
            'business_id' => $businessId,
            'branch_id'   => $branchId,
            'product_id'  => $data['product_id'],
            'quantity_kg' => $diferencia,
            'waste_kg'    => 0,
            'entered_at'  => now(),
            'created_by'  => $userId,
            'notes'       => 'Ajuste de inventario — stock real: ' . $data['stock_real'],
        ]);

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'action'      => 'inventory.adjust',
            'model_type'  => 'InventoryEntry',
            'model_id'    => $entry->id,
            'new_values'  => $entry->toArray(),
            'ip_address'  => $request->ip(),
        ]);

        return back()->with('success', 'Stock ajustado correctamente.');
    }

    // ─── Reciclar remanente con nuevo costo ──────────────────────────────────

    public function reciclarRemanente(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'      => ['required', 'integer', 'exists:products,id'],
            'new_cost_per_kg' => ['required', 'numeric', 'min:0.01'],
            'notes'           => ['nullable', 'string', 'max:200'],
        ]);

        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id')
                ?? \App\Models\Branch::where('business_id', $businessId)
                    ->orderBy('id')->value('id'));

        abort_unless(
            in_array($user->role, ['owner', 'super_admin', 'branch_admin'], true),
            403,
            'No tienes permiso para reciclar remanentes.'
        );

        $product = Product::where('id', $data['product_id'])
            ->where('business_id', $businessId)
            ->where('sale_mode', 'weight')
            ->firstOrFail();

        // Stock actual del producto (Regla #1 — solo inventory_entries)
        $stockActual = (float) InventoryEntry::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->sum('net_kg');

        if ($stockActual <= 0) {
            return response()->json(
                ['error' => 'No hay stock disponible para reciclar.'],
                422
            );
        }

        // Lote origen — inventory_entry positiva más reciente con boveda_entry_id
        $loteOrigen = InventoryEntry::where('business_id', $businessId)
            ->where('branch_id', $branchId)
            ->where('product_id', $product->id)
            ->where('location', 'vitrina')
            ->where('quantity_kg', '>', 0)
            ->whereNotNull('boveda_entry_id')
            ->orderByDesc('entered_at')
            ->orderByDesc('id')
            ->first();

        $bovadaEntryId = $loteOrigen?->boveda_entry_id;
        $costoAnterior = $loteOrigen?->cost_per_kg_usd ?? 0;

        $newCost    = (float) $data['new_cost_per_kg'];
        $kgReciclar = round($stockActual, 3);
        $costoTotal = round($kgReciclar * $newCost, 2);
        $motivo     = $data['notes'] ?? 'Reciclaje de remanente';

        DB::transaction(function () use (
            $businessId, $branchId, $user, $product,
            $kgReciclar, $newCost, $costoTotal,
            $bovadaEntryId, $costoAnterior, $motivo
        ): void {
            // 1. Entrada negativa — cierra el lote actual
            InventoryEntry::create([
                'business_id'     => $businessId,
                'branch_id'       => $branchId,
                'product_id'      => $product->id,
                'boveda_entry_id' => $bovadaEntryId,
                'quantity_kg'     => -$kgReciclar,
                'waste_kg'        => 0,
                'cost_per_kg_usd' => $costoAnterior,
                'location'        => 'vitrina',
                'entered_at'      => now(),
                'created_by'      => $user->id,
                'notes'           => "Cierre lote reciclaje — {$motivo}",
            ]);

            // 2. Nueva BovedaEntry con nuevo costo
            $nuevaBoveda = \App\Models\BovedaEntry::create([
                'business_id'            => $businessId,
                'branch_id'              => $branchId,
                'product_type'           => $product->name,
                'description'            => "Remanente reciclado — {$motivo}",
                'kg_entrada'             => $kgReciclar,
                'costo_usd'              => $costoTotal,
                'supplier'               => 'Remanente',
                'entered_at'             => now(),
                'kg_surtido_vitrina'     => $kgReciclar,
                'despiece_completado_at' => now(),
            ]);

            // 3. Entrada positiva vitrina con nuevo costo — listo para FIFO
            InventoryEntry::create([
                'business_id'     => $businessId,
                'branch_id'       => $branchId,
                'product_id'      => $product->id,
                'boveda_entry_id' => $nuevaBoveda->id,
                'quantity_kg'     => $kgReciclar,
                'waste_kg'        => 0,
                'cost_per_kg_usd' => $newCost,
                'location'        => 'vitrina',
                'entered_at'      => now(),
                'created_by'      => $user->id,
                'notes'           => "Remanente reciclado a \${$newCost}/kg — {$motivo}",
            ]);

            // 4. Actualizar costo referencia en el producto
            $product->update(['cost_per_kg_usd' => $newCost]);

            // 5. Cerrar BovedaEntry origen si tiene 0 disponible
            if ($bovadaEntryId) {
                $entradaOrigen = \App\Models\BovedaEntry::find($bovadaEntryId);
                if ($entradaOrigen && (float) $entradaOrigen->kg_disponible <= 0) {
                    $entradaOrigen->update(['closed_at' => now()]);
                }
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'inventory.reciclar_remanente',
                'model_type'  => 'InventoryEntry',
                'model_id'    => $product->id,
                'new_values'  => [
                    'product'         => $product->name,
                    'kg_reciclados'   => $kgReciclar,
                    'costo_anterior'  => $costoAnterior,
                    'nuevo_costo'     => $newCost,
                    'nueva_boveda_id' => $nuevaBoveda->id,
                ],
            ]);
        });

        return response()->json([
            'ok'            => true,
            'kg_reciclados' => $kgReciclar,
            'nuevo_costo'   => $newCost,
            'costo_total'   => $costoTotal,
        ]);
    }
}