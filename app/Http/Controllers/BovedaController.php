<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BovedaEntry;
use App\Models\BovedaProduct;
use App\Models\Business;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
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

        $bovedaProducts = BovedaProduct::where('business_id', $businessId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'                 => $p->id,
                'name'               => $p->name,
                'unit'               => $p->unit,
                'active'             => $p->active,
                'requires_despiece'  => $p->requires_despiece,
                'vitrina_product_id' => $p->vitrina_product_id,
            ]);

        $productosVitrina = Product::where('business_id', $businessId)
            ->where('active', true)
            ->where('location', 'vitrina')
            ->where('sale_mode', 'weight')
            ->orderBy('name')
            ->get(['id', 'name']);

        $kgDisponibleTotal = BovedaEntry::active()->where('business_id', $businessId)
            ->whereRaw('kg_entrada - kg_surtido_vitrina - waste_kg > 0')
            ->sum(DB::raw('kg_entrada - kg_surtido_vitrina - waste_kg'));

        $costoActivoTotal = BovedaEntry::active()->where('business_id', $businessId)
            ->sum('costo_usd');

        $surtidoHoy = ActivityLog::where('business_id', $businessId)
            ->where('action', 'boveda.surte')
            ->whereDate('created_at', today())
            ->get()
            ->sum(fn ($log) => (float) (($log->new_values ?? [])['kg_surtir'] ?? 0));

        return Inertia::render('Boveda/Index', [
            'activas'          => $activas,
            'historial'        => $historial,
            'bovedaProducts'   => $bovedaProducts,
            'productosVitrina' => $productosVitrina,
            'kpis'             => [
                'entradasActivas' => $activas->count(),
                'kgDisponible'    => round((float) $kgDisponibleTotal, 3),
                'costoActivo'     => round((float) $costoActivoTotal, 2),
                'surtidoHoy'      => round((float) $surtidoHoy, 3),
            ],
        ]);
    }

    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'product_type' => ['required', 'string', 'max:80'],
            'description'  => ['nullable', 'string', 'max:100'],
            'kg_entrada'   => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'costo_usd'    => ['required', 'numeric', 'min:0'],
            'supplier'     => ['nullable', 'string', 'max:100'],
            'entered_at'   => ['required', 'date'],
            'kg_par'       => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();
        $kgPar      = isset($data['kg_par']) ? (float) $data['kg_par'] : null;

        DB::transaction(function () use ($data, $businessId, $userId, $kgPar): void {
            $entry = BovedaEntry::create([
                'business_id' => $businessId,
                ...$data,
            ]);

            // Si viene canal par, marcar Canal 1 y crear Canal 2
            if ($kgPar !== null) {
                $pairId = $entry->id;
                $entry->update([
                    'pair_id'     => $pairId,
                    'description' => 'Canal 1',
                ]);

                $entry2 = BovedaEntry::create([
                    'business_id'  => $businessId,
                    'product_type' => $data['product_type'],
                    'description'  => 'Canal 2',
                    'kg_entrada'   => $kgPar,
                    'costo_usd'    => $data['costo_usd'],
                    'supplier'     => $data['supplier'] ?? null,
                    'entered_at'   => $data['entered_at'],
                    'pair_id'      => $pairId,
                ]);
            }

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

                // InventoryEntry para Canal 2 si aplica
                if ($kgPar !== null) {
                    $costoPorKgPar = $kgPar > 0
                        ? round($data['costo_usd'] / $kgPar, 4)
                        : null;

                    InventoryEntry::create([
                        'business_id'     => $businessId,
                        'product_id'      => $product->id,
                        'boveda_entry_id' => $entry2->id,
                        'quantity_kg'     => $kgPar,
                        'waste_kg'        => 0,
                        'cost_per_kg_usd' => $costoPorKgPar,
                        'location'        => 'boveda',
                        'notes'           => 'Entrada bóveda #' . $entry2->id . ' (Canal 2)',
                        'entered_at'      => $data['entered_at'],
                        'created_by'      => $userId,
                    ]);
                }
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'action'      => 'boveda.entry',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $entry->id,
                'description' => 'Entrada bóveda: ' . $data['product_type'] . ' — ' . $data['kg_entrada'] . ' kg'
                               . ($kgPar !== null ? ' + Canal 2: ' . $kgPar . ' kg' : ''),
            ]);
        });

        return response()->json(['message' => 'Entrada registrada.']);
    }

    public function surte(Request $request, BovedaEntry $entry): \Illuminate\Http\JsonResponse
    {
        abort_unless($entry->business_id === Auth::user()->business_id, 403);
        abort_if($entry->closed_at !== null, 422, 'Entrada ya cerrada.');

        $data = $request->validate([
            'peso_real' => ['required', 'numeric', 'min:0'],
        ]);

        if ($entry->product_type === 'POLLO - Entero Congelado') {
            $request->validate([
                'pollo_tipo' => ['required', 'string', 'in:Pollo Entero Tipo A,Pollo Entero Tipo B'],
            ]);
        }

        $disponible = round(
            (float) $entry->kg_entrada - (float) $entry->kg_surtido_vitrina - (float) $entry->waste_kg,
            3
        );

        $kg = round((float) $data['peso_real'], 3);

        if ($kg <= 0 && $entry->kg_disponible > 0) {
            return response()->json(['error' => 'Peso debe ser mayor a 0'], 422);
        }

        if ($kg > $disponible) {
            return response()->json(
                ['errors' => ['peso_real' => ["El peso ({$kg} kg) supera el disponible ({$disponible} kg)."]]],
                422
            );
        }

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();

        $bovedaProductType = BovedaProduct::where('business_id', $businessId)
            ->where('name', $entry->product_type)
            ->first();

        $requiresDespiece = (bool) ($bovedaProductType?->requires_despiece ?? true);

        $vitrinaProduct = null;
        if (! $requiresDespiece) {
            if ($entry->product_type === 'POLLO - Entero Congelado') {
                $vitrinaProduct = Product::where('business_id', $businessId)
                    ->where('location', 'vitrina')
                    ->where('name', $request->input('pollo_tipo'))
                    ->first();

                if ($vitrinaProduct === null) {
                    abort(422, 'Producto no encontrado en vitrina: ' . $request->input('pollo_tipo'));
                }
            } else {
                // 1) Preferir vitrina_product_id configurado en el producto bóveda
                if ($bovedaProductType?->vitrina_product_id !== null) {
                    $vitrinaProduct = Product::find($bovedaProductType->vitrina_product_id);
                }

                // 2) Fallback: búsqueda LIKE por primera palabra del tipo
                if ($vitrinaProduct === null) {
                    $keyword        = explode(' ', $entry->product_type)[0];
                    $vitrinaProduct = Product::where('business_id', $businessId)
                        ->where('location', 'vitrina')
                        ->where('name', 'like', '%' . $keyword . '%')
                        ->first();
                }

                if ($vitrinaProduct === null) {
                    return response()->json([
                        'error' => 'No existe producto en vitrina para ' . $entry->product_type . '. Créalo primero en Catálogo.',
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($entry, $kg, $businessId, $userId, $requiresDespiece, $vitrinaProduct): void {
            $entry->increment('kg_surtido_vitrina', $kg);

            if (! $requiresDespiece) {
                $entry->despiece_completado_at = now();
                $entry->save();
            }

            $entry->refresh();
            if ((float) $entry->kg_disponible <= 0) {
                $entry->update([
                    'closed_at' => now(),
                    'waste_kg'  => max(0, (float) $entry->kg_entrada
                                   - (float) $entry->kg_surtido_vitrina),
                ]);
            }

            if ($requiresDespiece) {
                $bovedaProduct = Product::where('business_id', $businessId)
                    ->where('location', 'boveda')
                    ->where('name', $entry->product_type)
                    ->first();

                if ($bovedaProduct !== null) {
                    InventoryEntry::create([
                        'business_id'     => $businessId,
                        'product_id'      => $bovedaProduct->id,
                        'boveda_entry_id' => $entry->id,
                        'quantity_kg'     => -$kg,
                        'waste_kg'        => 0,
                        'location'        => 'boveda',
                        'notes'           => 'Salida bóveda → vitrina para despiece (entrada #' . $entry->id . ')',
                        'entered_at'      => now(),
                        'created_by'      => $userId,
                    ]);
                }
            } else {
                InventoryEntry::create([
                    'business_id'     => $businessId,
                    'product_id'      => $vitrinaProduct->id,
                    'boveda_entry_id' => $entry->id,
                    'quantity_kg'     => $kg,
                    'waste_kg'        => 0,
                    'location'        => 'vitrina',
                    'notes'           => 'Surtido directo desde bóveda — ' . $entry->product_type . ' (entrada #' . $entry->id . ')',
                    'entered_at'      => now(),
                    'created_by'      => $userId,
                ]);
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'action'      => 'boveda.surte',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $entry->id,
                'new_values'  => [
                    'kg_surtir'         => $kg,
                    'requires_despiece' => $requiresDespiece,
                ],
            ]);
        });

        return response()->json([
            'message'           => 'Surtido registrado.',
            'requires_despiece' => $requiresDespiece,
            'product_type'      => $entry->product_type,
            'kg_surtir'         => $kg,
        ]);
    }

    public function close(BovedaEntry $entry): \Illuminate\Http\JsonResponse
    {
        abort_unless($entry->business_id === Auth::user()->business_id, 403);
        abort_if($entry->closed_at !== null, 422, 'Entrada ya cerrada.');

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();

        return DB::transaction(function () use ($entry, $businessId, $userId): \Illuminate\Http\JsonResponse {
            $entry->update([
                'closed_at' => now(),
                'waste_kg'  => max(0, (float) $entry->kg_entrada
                               - (float) $entry->kg_surtido_vitrina),
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $userId,
                'action'      => 'boveda.close',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $entry->id,
                'description' => 'Entrada bóveda #' . $entry->id . ' cerrada.',
            ]);

            return response()->json(['message' => 'Entrada cerrada.']);
        });
    }

    public function registerMerma(Request $request, BovedaEntry $entry): \Illuminate\Http\JsonResponse
    {
        abort_unless($entry->business_id === Auth::user()->business_id, 403);
        abort_if($entry->closed_at !== null, 422, 'Entrada ya cerrada.');

        $disponible = round(
            (float) $entry->kg_entrada
            - (float) $entry->kg_surtido_vitrina
            - (float) $entry->waste_kg,
            3
        );

        $data = $request->validate([
            'peso_actual' => ['required', 'numeric', 'min:0'],
        ]);

        $pesoActual = round((float) $data['peso_actual'], 3);

        if ($pesoActual >= $disponible) {
            return response()->json(
                ['errors' => ['peso_actual' => ["El peso actual ({$pesoActual} kg) debe ser menor que el disponible ({$disponible} kg)."]]],
                422
            );
        }

        $mermaCalculada = round($disponible - $pesoActual, 3);

        return DB::transaction(function () use ($entry, $mermaCalculada): \Illuminate\Http\JsonResponse {
            $entry->increment('waste_kg', $mermaCalculada);

            ActivityLog::create([
                'business_id' => Auth::user()->business_id,
                'user_id'     => Auth::id(),
                'action'      => 'boveda.merma',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $entry->id,
            ]);

            return response()->json([
                'message'          => 'Merma registrada.',
                'merma_calculada'  => $mermaCalculada,
            ]);
        });
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
            'unit'               => ['nullable', 'string', 'max:20'],
            'requires_despiece'  => ['boolean'],
            'vitrina_product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        return DB::transaction(function () use ($businessId, $data): \Illuminate\Http\JsonResponse {
            $product = BovedaProduct::create([
                'business_id'        => $businessId,
                'name'               => $data['name'],
                'unit'               => $data['unit'] ?? 'kg',
                'active'             => true,
                'requires_despiece'  => $data['requires_despiece'] ?? true,
                'vitrina_product_id' => $data['vitrina_product_id'] ?? null,
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => Auth::id(),
                'action'      => 'boveda.product.create',
                'model_type'  => 'BovedaProduct',
                'model_id'    => $product->id,
            ]);

            return response()->json(['product' => $this->formatBovedaProduct($product)], 201);
        });
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
            'unit'               => ['nullable', 'string', 'max:20'],
            'requires_despiece'  => ['boolean'],
            'vitrina_product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        return DB::transaction(function () use ($product, $businessId, $data): \Illuminate\Http\JsonResponse {
            $product->update([
                'name'               => $data['name'],
                'unit'               => $data['unit'] ?? $product->unit,
                'requires_despiece'  => $data['requires_despiece'] ?? $product->requires_despiece,
                'vitrina_product_id' => array_key_exists('vitrina_product_id', $data)
                    ? $data['vitrina_product_id']
                    : $product->vitrina_product_id,
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => Auth::id(),
                'action'      => 'boveda.product.update',
                'model_type'  => 'BovedaProduct',
                'model_id'    => $product->id,
            ]);

            return response()->json(['product' => $this->formatBovedaProduct($product)]);
        });
    }

    public function plantillaDespiece(BovedaEntry $entry): View
    {
        abort_unless($entry->business_id === Auth::user()->business_id, 403);

        $businessId = $entry->business_id;
        $business   = Business::find($businessId);

        // Productos vitrina activos agrupados por categoría (excluye utensilios/despensa)
        $despieceCats = ['Res', 'Cerdo', 'Pollo'];

        $catMap = [
            'RES - Medio Canal'        => 'Res',
            'CERDO - Canal'            => 'Cerdo',
            'POLLO - Entero Congelado' => 'Pollo',
        ];
        $catName  = $catMap[$entry->product_type] ?? null;
        $resOrder = ['Carne del Canal', 'Costilla', 'Hueso Redondo', 'Hueso Rojo'];

        $productos = Product::with('category')
            ->where('business_id', $businessId)
            ->where('location', 'vitrina')
            ->where('active', true)
            ->where('fabricable', false)
            ->when($catName, fn ($q) => $q->whereHas('category', fn ($q2) => $q2->where('name', $catName)))
            ->get()
            ->sortBy(fn ($p) => $catName === 'Res'
                ? (($pos = array_search($p->name, $resOrder)) !== false ? $pos : 999)
                : $p->name
            )
            ->values();

        // Kg registrados por producto si el despiece ya fue completado
        $kgRegistrados = $entry->despiece_completado_at
            ? InventoryEntry::where('boveda_entry_id', $entry->id)
                ->where('location', 'vitrina')
                ->where('quantity_kg', '>', 0)
                ->pluck('quantity_kg', 'product_id')
            : collect();

        $totalDocumentado = round($kgRegistrados->sum(), 3);
        $mermaReal        = round((float) $entry->kg_surtido_vitrina - $totalDocumentado, 3);
        $rendimiento      = $totalDocumentado > 0 && (float) $entry->kg_surtido_vitrina > 0
            ? round(($totalDocumentado / (float) $entry->kg_surtido_vitrina) * 100, 1)
            : null;

        return view('boveda.plantilla_despiece', compact(
            'entry', 'business', 'catName', 'productos',
            'kgRegistrados', 'totalDocumentado', 'mermaReal', 'rendimiento'
        ));
    }

    public function destroyProduct(BovedaProduct $product): \Illuminate\Http\JsonResponse
    {
        abort_unless($product->business_id === Auth::user()->business_id, 403);

        return DB::transaction(function () use ($product): \Illuminate\Http\JsonResponse {
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
        });
    }

    private function formatBovedaProduct(BovedaProduct $p): array
    {
        return [
            'id'                 => $p->id,
            'name'               => $p->name,
            'unit'               => $p->unit,
            'active'             => $p->active,
            'requires_despiece'  => $p->requires_despiece,
            'vitrina_product_id' => $p->vitrina_product_id,
        ];
    }

    // F4: expone waste_kg y cost_per_kg_usd calculado
    private function format(BovedaEntry $e): array
    {
        $kgEntrada = (float) $e->kg_entrada;

        $bovedaProduct = BovedaProduct::where('business_id', $e->business_id)
            ->where('name', $e->product_type)
            ->first();

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
            'requires_despiece'  => $bovedaProduct?->requires_despiece ?? true,
        ];
    }
}
