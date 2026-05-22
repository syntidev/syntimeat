<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Product;
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

        // ─── Productos activos con categoría ─────────────────────────────────
        $products = Product::with('category')
            ->where('business_id', $businessId)
            ->where('active', true)
            ->where('location', '!=', 'boveda')
            ->orderBy('sort_order')
            ->get();

        // ─── Categorías para el formulario ────────────────────────────────────
        $categories = Category::where('business_id', $businessId)
            ->orderBy('sort_order')
            ->get();

        // ─── Entradas de hoy ──────────────────────────────────────────────────
        $todayEntries = InventoryEntry::with(['product.category', 'creator'])
            ->where('business_id', $businessId)
            ->whereDate('entered_at', today())
            ->orderByDesc('entered_at')
            ->get();

        // ─── Stock disponible: SUM(net_kg) - SUM(vendido en paid) ────────────
        $stockIn = InventoryEntry::where('business_id', $businessId)
            ->selectRaw('product_id, SUM(net_kg) as total_net')
            ->groupBy('product_id')
            ->pluck('total_net', 'product_id');

        $stockOut = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity_value) as total_sold')
            ->groupBy('sale_items.product_id')
            ->pluck('total_sold', 'product_id');

        $stockMap = [];
        foreach ($products as $product) {
            $net  = (float) ($stockIn[$product->id]  ?? 0);
            $sold = (float) ($stockOut[$product->id] ?? 0);
            $stockMap[$product->id] = round($net - $sold, 3);
        }

        // ─── Último ingreso por producto ──────────────────────────────────────
        $lastEntryMap = InventoryEntry::where('business_id', $businessId)
            ->selectRaw('product_id, MAX(entered_at) as last_at')
            ->groupBy('product_id')
            ->pluck('last_at', 'product_id');

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

        return Inertia::render('Inventory/Index', [
            'products'     => $products,
            'categories'   => $categories,
            'todayEntries' => $todayEntries,
            'stockMap'     => $stockMap,
            'lastEntryMap' => $lastEntryMap,
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

        $businessId = Auth::user()->business->id;
        $userId     = Auth::id();

        $product = Product::where('id', $data['product_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        if ($product->location === 'boveda') {
            return back()->withErrors(['product_id' => 'Productos bóveda no se pueden agregar al inventario directamente.']);
        }

        $isUnit  = $product->sale_mode === 'unit';
        $wasteKg = $isUnit ? 0.0 : (float) ($data['waste_kg'] ?? 0);

        // Para productos por peso: waste no puede superar la cantidad
        if (! $isUnit && $wasteKg >= (float) $data['quantity_kg']) {
            return back()->withErrors(['waste_kg' => 'La merma no puede ser igual o mayor al total recibido.']);
        }

        $entry = InventoryEntry::create([
            'business_id'     => $businessId,
            'product_id'      => $data['product_id'],
            'quantity_kg'     => $data['quantity_kg'],
            'waste_kg'        => $wasteKg,
            'cost_per_kg_usd' => $data['cost_per_kg_usd'] ?? null,
            'supplier'        => $data['supplier'] ?? null,
            'entered_at'      => $data['entered_at'],
            'created_by'      => $userId,
        ]);

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
}
