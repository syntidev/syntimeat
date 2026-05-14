<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BovedaEntry;
use App\Models\BovedaProduct;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BovedaController extends Controller
{
    public function index(): Response
    {
        $businessId = Auth::user()->business_id;

        $activas = BovedaEntry::active()
            ->where('business_id', $businessId)
            ->orderByDesc('entered_at')
            ->get()
            ->map(fn ($e) => $this->format($e));

        $historial = BovedaEntry::where('business_id', $businessId)
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->limit(30)
            ->get()
            ->map(fn ($e) => $this->format($e));

        $productosVitrina = Product::with('category')
            ->where('business_id', $businessId)
            ->where('active', true)
            ->where('location', 'vitrina')
            ->where('sale_mode', 'weight')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'            => $p->id,
                'name'          => $p->name,
                'category_name' => $p->category?->name ?? '—',
            ]);

        $bovedaProducts = BovedaProduct::where('business_id', $businessId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'     => $p->id,
                'name'   => $p->name,
                'unit'   => $p->unit,
                'active' => $p->active,
            ]);

        $kgDisponibleTotal = BovedaEntry::active()->where('business_id', $businessId)
            ->sum(DB::raw('kg_entrada - kg_surtido_vitrina - waste_kg'));

        $costoActivoTotal = BovedaEntry::active()->where('business_id', $businessId)
            ->sum('costo_usd');

        // F6: trazabilidad real por FK en lugar de LIKE en notes
        $surtidoHoy = InventoryEntry::where('business_id', $businessId)
            ->whereDate('entered_at', today())
            ->whereNotNull('boveda_entry_id')
            ->where('location', 'vitrina')
            ->sum('quantity_kg');

        return Inertia::render('Boveda/Index', [
            'activas'          => $activas,
            'historial'        => $historial,
            'productosVitrina' => $productosVitrina,
            'bovedaProducts'   => $bovedaProducts,
            'kpis'             => [
                'entradasActivas' => $activas->count(),
                'kgDisponible'    => round((float) $kgDisponibleTotal, 3),
                'costoActivo'     => round((float) $costoActivoTotal, 2),
                'surtidoHoy'      => round((float) $surtidoHoy, 3),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_type' => ['required', 'string', 'max:80'],
            'description'  => ['nullable', 'string', 'max:100'],
            'kg_entrada'   => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'costo_usd'    => ['required', 'numeric', 'min:0'],
            'supplier'     => ['nullable', 'string', 'max:100'],
            'entered_at'   => ['required', 'date'],
        ]);

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();

        DB::transaction(function () use ($data, $businessId, $userId): void {
            $entry = BovedaEntry::create([
                'business_id' => $businessId,
                ...$data,
            ]);

            // F2: crear espejo en inventory_entries(location=boveda) para trazabilidad.
            // Solo cuando existe un producto de catálogo con ese nombre en location=boveda.
            $product = Product::where('business_id', $businessId)
                ->where('location', 'boveda')
                ->where('name', $data['product_type'])
                ->first();

            if ($product !== null) {
                $costoPorKg = $data['kg_entrada'] > 0
                    ? round($data['costo_usd'] / $data['kg_entrada'], 4)
                    : null;

                InventoryEntry::create([
                    'business_id'     => $businessId,
                    'product_id'      => $product->id,
                    'boveda_entry_id' => $entry->id,
                    'quantity_kg'     => $data['kg_entrada'],
                    'waste_kg'        => 0,
                    'cost_per_kg_usd' => $costoPorKg,
                    'location'        => 'boveda',
                    'notes'           => 'Entrada bóveda #' . $entry->id,
                    'entered_at'      => $data['entered_at'],
                    'created_by'      => $userId,
                ]);
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'action'      => 'boveda.entry',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $entry->id,
                'description' => 'Entrada bóveda: ' . $data['product_type'] . ' — ' . $data['kg_entrada'] . ' kg',
            ]);
        });

        return back()->with('success', 'Entrada registrada.');
    }

    public function surte(Request $request, BovedaEntry $entry): RedirectResponse
    {
        abort_unless($entry->business_id === Auth::user()->business_id, 403);
        abort_if($entry->closed_at !== null, 422, 'Entrada ya cerrada.');

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'kg_surtir'  => ['required', 'numeric', 'min:0.001'],
        ]);

        $disponible = (float) $entry->kg_disponible;
        if ((float) $data['kg_surtir'] > $disponible) {
            return back()->withErrors(['kg_surtir' => "No puede surtir más de {$disponible} kg disponibles."]);
        }

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();

        abort_unless(
            Product::where('id', $data['product_id'])->where('business_id', $businessId)->exists(),
            403
        );

        DB::transaction(function () use ($entry, $data, $businessId, $userId): void {
            $entry->increment('kg_surtido_vitrina', (float) $data['kg_surtir']);

            // Salida de bóveda — mantiene sincronizado el stock de DespieceController
            $bovedaProduct = Product::where('business_id', $businessId)
                ->where('location', 'boveda')
                ->where('name', $entry->product_type)
                ->first();

            if ($bovedaProduct !== null) {
                InventoryEntry::create([
                    'business_id'     => $businessId,
                    'product_id'      => $bovedaProduct->id,
                    'boveda_entry_id' => $entry->id,
                    'quantity_kg'     => -(float) $data['kg_surtir'],
                    'waste_kg'        => 0,
                    'location'        => 'boveda',
                    'notes'           => 'Surtido desde bóveda (entrada #' . $entry->id . ')',
                    'entered_at'      => now(),
                    'created_by'      => $userId,
                ]);
            }

            InventoryEntry::create([
                'business_id'     => $businessId,
                'product_id'      => $data['product_id'],
                'boveda_entry_id' => $entry->id,
                'quantity_kg'     => (float) $data['kg_surtir'],
                'waste_kg'        => 0,
                'location'        => 'vitrina',
                'notes'           => 'Surtido desde bóveda (entrada #' . $entry->id . ')',
                'entered_at'      => now(),
                'created_by'      => $userId,
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'action'      => 'boveda.surte',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $entry->id,
                'description' => 'Surtido ' . $data['kg_surtir'] . ' kg a vitrina desde entrada #' . $entry->id,
            ]);
        });

        return back()->with('success', 'Surtido registrado.');
    }

    public function close(BovedaEntry $entry): RedirectResponse
    {
        abort_unless($entry->business_id === Auth::user()->business_id, 403);
        abort_if($entry->closed_at !== null, 422, 'Entrada ya cerrada.');

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();

        $entry->update(['closed_at' => now()]);

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'action'      => 'boveda.close',
            'model_type'  => 'BovedaEntry',
            'model_id'    => $entry->id,
            'description' => 'Entrada bóveda #' . $entry->id . ' cerrada.',
        ]);

        return back()->with('success', 'Entrada cerrada.');
    }

    public function storeProduct(Request $request): \Illuminate\Http\JsonResponse
    {
        $businessId = Auth::user()->business_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('boveda_products')->where(
                    fn ($q) => $q->where('business_id', $businessId)
                ),
            ],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);

        $product = BovedaProduct::create([
            'business_id' => $businessId,
            'name'        => $data['name'],
            'unit'        => $data['unit'] ?? 'kg',
            'active'      => true,
        ]);

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => Auth::id(),
            'action'      => 'boveda.product.create',
            'model_type'  => 'BovedaProduct',
            'model_id'    => $product->id,
            'description' => 'Producto bóveda creado: ' . $product->name,
        ]);

        return response()->json([
            'product' => ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit, 'active' => $product->active],
        ], 201);
    }

    public function updateProduct(Request $request, BovedaProduct $product): \Illuminate\Http\JsonResponse
    {
        abort_unless($product->business_id === Auth::user()->business_id, 403);

        $businessId = Auth::user()->business_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('boveda_products')->where(
                    fn ($q) => $q->where('business_id', $businessId)
                )->ignore($product->id),
            ],
            'unit' => ['nullable', 'string', 'max:20'],
        ]);

        $product->update([
            'name' => $data['name'],
            'unit' => $data['unit'] ?? $product->unit,
        ]);

        ActivityLog::create([
            'business_id' => $businessId,
            'user_id'     => Auth::id(),
            'action'      => 'boveda.product.update',
            'model_type'  => 'BovedaProduct',
            'model_id'    => $product->id,
            'description' => 'Producto bóveda editado: ' . $product->name,
        ]);

        return response()->json([
            'product' => ['id' => $product->id, 'name' => $product->name, 'unit' => $product->unit, 'active' => $product->active],
        ]);
    }

    public function destroyProduct(BovedaProduct $product): \Illuminate\Http\JsonResponse
    {
        abort_unless($product->business_id === Auth::user()->business_id, 403);

        $product->update(['active' => false]);

        ActivityLog::create([
            'business_id' => $product->business_id,
            'user_id'     => Auth::id(),
            'action'      => 'boveda.product.deactivate',
            'model_type'  => 'BovedaProduct',
            'model_id'    => $product->id,
            'description' => 'Producto bóveda desactivado: ' . $product->name,
        ]);

        return response()->json(['ok' => true]);
    }

    // F4: expone waste_kg y cost_per_kg_usd calculado
    private function format(BovedaEntry $e): array
    {
        $kgEntrada = (float) $e->kg_entrada;

        return [
            'id'                 => $e->id,
            'product_type'       => $e->product_type,
            'description'        => $e->description,
            'kg_entrada'         => $kgEntrada,
            'costo_usd'          => (float) $e->costo_usd,
            'cost_per_kg_usd'    => $kgEntrada > 0 ? round($e->costo_usd / $kgEntrada, 4) : null,
            'waste_kg'           => (float) $e->waste_kg,
            'kg_surtido_vitrina' => (float) $e->kg_surtido_vitrina,
            'kg_disponible'      => (float) ($kgEntrada - (float) $e->kg_surtido_vitrina - (float) $e->waste_kg),
            'supplier'           => $e->supplier,
            'entered_at'         => $e->entered_at?->format('d/m/Y H:i'),
            'closed_at'          => $e->closed_at?->format('d/m/Y H:i'),
        ];
    }
}
