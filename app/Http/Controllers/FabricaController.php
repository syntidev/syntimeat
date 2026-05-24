<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BovedaEntry;
use App\Models\BovedaProduct;
use App\Models\FabricaBatch;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FabricaController extends Controller
{
    public function index(): Response
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        // Productos habilitados para fabricar (chorizo, cesta, combo…)
        $fabricables = Product::with('category')
            ->where('business_id', $businessId)
            ->where('fabricable', true)
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'sale_mode' => $p->sale_mode,
                'category'  => $p->category?->name,
                'image_path'=> $p->image_path,
            ]);

        // Todos los productos activos usables como ingredientes (excluye boveda)
        $ingredientes = Product::with('category')
            ->where('business_id', $businessId)
            ->where('active', true)
            ->where('location', '!=', 'boveda')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'sale_mode' => $p->sale_mode,
                'category'  => $p->category?->name,
            ]);

        // Stock disponible de cada ingrediente (mismo cálculo que POS)
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
        foreach ($ingredientes as $ing) {
            $net  = (float) ($stockIn[$ing['id']]  ?? 0);
            $sold = (float) ($stockOut[$ing['id']] ?? 0);
            $stockMap[$ing['id']] = round($net - $sold, 3);
        }

        // Historial de batches
        $historial = FabricaBatch::with(['outputProduct', 'creator', 'inputs'])
            ->where('business_id', $businessId)
            ->orderByDesc('produced_at')
            ->limit(50)
            ->get()
            ->map(fn ($b) => [
                'id'             => $b->id,
                'output_product' => $b->outputProduct?->name,
                'output_kg'      => (float) $b->output_kg,
                'output_units'   => (float) $b->output_units,
                'input_cost_usd' => (float) $b->input_cost_usd,
                'notes'          => $b->notes,
                'produced_at'    => $b->produced_at?->format('d/m/Y H:i'),
                'creator'        => $b->creator?->name,
                'inputs_count'   => $b->inputs->count(),
                'inputs_kg'      => round($b->inputs->sum('quantity_kg'), 3),
            ]);

        // Piezas surtidas desde bóveda que requieren despiece y aún no se procesaron
        $despiecePendiente = BovedaEntry::where('business_id', $businessId)
            ->where('kg_surtido_vitrina', '>', 0)
            ->whereNull('despiece_completado_at')
            ->whereHas('bovedaProduct', fn ($q) => $q->where('requires_despiece', true)->where('business_id', $businessId))
            ->orderBy('updated_at')
            ->get()
            ->map(function ($e) use ($businessId) {
                // Categoría → productos vitrina que reciben los cortes
                $catMap = [
                    'RES - Medio Canal'        => 'Res',
                    'Res Entera'               => 'Res',
                    'CERDO - Canal'            => 'Cerdo',
                    'POLLO - Entero Congelado' => 'Pollo',
                ];
                $catName  = $catMap[$e->product_type] ?? null;
                $resOrder = ['Premium', 'Primera', 'Segunda', 'Costilla', 'Hueso Redondo', 'Hueso Rojo', 'Rabo', 'Recortes de Res'];

                $productos = $catName
                    ? Product::with('category')
                        ->where('business_id', $businessId)
                        ->where('location', 'vitrina')
                        ->where('active', true)
                        ->where('fabricable', false)
                        ->whereHas('category', fn ($q) => $q->where('name', $catName))
                        ->get(['id', 'name'])
                        ->sortBy(fn ($p) => $catName === 'Res'
                            ? (array_search($p->name, $resOrder) !== false ? array_search($p->name, $resOrder) : 999)
                            : $p->name
                        )
                        ->values()
                        ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])
                    : collect();

                return [
                    'id'                 => $e->id,
                    'product_type'       => $e->product_type,
                    'description'        => $e->description,
                    'kg_surtido'         => (float) $e->kg_surtido_vitrina,
                    'supplier'           => $e->supplier,
                    'entered_at'         => $e->entered_at?->format('d/m/Y'),
                    'productos_vitrina'  => $productos,
                ];
            });

        $despieceHistorial = BovedaEntry::where('business_id', $businessId)
            ->whereNotNull('despiece_completado_at')
            ->orderByDesc('despiece_completado_at')
            ->limit(50)
            ->get()
            ->map(fn ($e) => [
                'id'                     => $e->id,
                'product_type'           => $e->product_type,
                'description'            => $e->description,
                'kg_entrada'             => (float) $e->kg_entrada,
                'kg_surtido'             => (float) $e->kg_surtido_vitrina,
                'waste_kg'               => (float) $e->waste_kg,
                'supplier'               => $e->supplier,
                'despiece_completado_at' => $e->despiece_completado_at?->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Fabrica/Index', [
            'fabricables'       => $fabricables,
            'ingredientes'      => $ingredientes,
            'stockMap'          => $stockMap,
            'historial'         => $historial,
            'despiecePendiente' => $despiecePendiente,
            'despieceHistorial' => $despieceHistorial,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        $data = $request->validate([
            'output_product_id'          => ['required', 'integer', 'exists:products,id'],
            'output_kg'                  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'output_units'               => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'                      => ['nullable', 'string', 'max:500'],
            'produced_at'                => ['required', 'date'],
            'inputs'                     => ['required', 'array', 'min:1'],
            'inputs.*.product_id'        => ['required', 'integer', 'exists:products,id'],
            'inputs.*.quantity_kg'       => ['required', 'numeric', 'min:0.001'],
            'inputs.*.cost_usd'          => ['sometimes', 'numeric', 'min:0'],
        ]);

        // Verificar que el producto output pertenece al negocio y es fabricable
        $outputProduct = Product::where('id', $data['output_product_id'])
            ->where('business_id', $businessId)
            ->where('fabricable', true)
            ->firstOrFail();

        abort_if($outputProduct === null, 403, 'Producto no es fabricable.');

        // Resolver cantidad según sale_mode
        $isUnit = $outputProduct->sale_mode === 'unit';

        if ($isUnit) {
            $units = (float) ($data['output_units'] ?? 0);
            abort_if($units < 1, 422, 'Debes indicar al menos 1 unidad producida.');
            $data['output_kg']    = $units; // unidades se almacenan en quantity_kg
            $data['output_units'] = $units;
        } else {
            abort_if(($data['output_kg'] ?? 0) <= 0, 422, 'Debes indicar los kg producidos.');
        }

        // Verificar que todos los ingredientes pertenecen al negocio
        $inputIds = array_column($data['inputs'], 'product_id');
        $validIds = Product::whereIn('id', $inputIds)
            ->where('business_id', $businessId)
            ->pluck('id')
            ->all();

        foreach ($inputIds as $pid) {
            abort_unless(in_array($pid, $validIds, true), 403, 'Ingrediente no pertenece al negocio.');
        }

        // Verificar stock suficiente para cada ingrediente
        $stockIn = InventoryEntry::where('business_id', $businessId)
            ->whereIn('product_id', $inputIds)
            ->selectRaw('product_id, SUM(net_kg) as total_net')
            ->groupBy('product_id')
            ->pluck('total_net', 'product_id');

        $stockOut = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->whereIn('sale_items.product_id', $inputIds)
            ->selectRaw('sale_items.product_id, SUM(sale_items.quantity_value) as total_sold')
            ->groupBy('sale_items.product_id')
            ->pluck('total_sold', 'product_id');

        $productNames = Product::whereIn('id', $inputIds)->pluck('name', 'id');

        foreach ($data['inputs'] as $input) {
            $pid        = $input['product_id'];
            $disponible = round((float) ($stockIn[$pid] ?? 0) - (float) ($stockOut[$pid] ?? 0), 3);
            if ((float) $input['quantity_kg'] > $disponible) {
                return back()->withErrors([
                    'inputs' => 'Stock insuficiente para ' . ($productNames[$pid] ?? 'producto #' . $pid) .
                                '. Disponible: ' . $disponible . ' kg',
                ]);
            }
        }

        $totalCostUsd = collect($data['inputs'])->sum(fn ($i) => (float) ($i['cost_usd'] ?? 0));

        DB::transaction(function () use ($data, $user, $businessId, $totalCostUsd, $inputIds): void {
            $batch = FabricaBatch::create([
                'business_id'       => $businessId,
                'created_by'        => $user->id,
                'output_product_id' => $data['output_product_id'],
                'output_kg'         => $data['output_kg'],
                'output_units'      => $data['output_units'] ?? 0,
                'input_cost_usd'    => round($totalCostUsd, 2),
                'notes'             => $data['notes'] ?? null,
                'produced_at'       => $data['produced_at'],
            ]);

            // Registrar ingredientes y descontar inventario por cada uno
            foreach ($data['inputs'] as $input) {
                $batch->inputs()->create([
                    'product_id'  => $input['product_id'],
                    'quantity_kg' => $input['quantity_kg'],
                    'cost_usd'    => $input['cost_usd'] ?? 0,
                ]);

                InventoryEntry::create([
                    'business_id' => $businessId,
                    'product_id'  => $input['product_id'],
                    'quantity_kg' => -(float) $input['quantity_kg'],
                    'waste_kg'    => 0,
                    'location'    => 'vitrina',
                    'notes'       => 'Insumo fábrica lote #' . $batch->id,
                    'entered_at'  => $data['produced_at'],
                    'created_by'  => $user->id,
                ]);
            }

            // Ingresar producto fabricado al inventario
            InventoryEntry::create([
                'business_id'     => $businessId,
                'product_id'      => $data['output_product_id'],
                'quantity_kg'     => (float) $data['output_kg'],
                'waste_kg'        => 0,
                'cost_per_kg_usd' => $data['output_kg'] > 0
                    ? round($totalCostUsd / $data['output_kg'], 4)
                    : null,
                'location'        => 'vitrina',
                'notes'           => 'Producción fábrica lote #' . $batch->id,
                'entered_at'      => $data['produced_at'],
                'created_by'      => $user->id,
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'fabrica.batch',
                'model_type'  => FabricaBatch::class,
                'model_id'    => $batch->id,
                'description' => 'Lote fábrica: ' . $data['output_kg'] . ' kg → #' . $data['output_product_id'],
            ]);
        });

        return back()->with('success', 'Lote registrado. Inventario actualizado.');
    }

    public function storeDespiece(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        $data = $request->validate([
            'boveda_entry_id' => ['required', 'integer', 'exists:boveda_entries,id'],
            'cortes'          => ['required', 'array', 'min:1'],
            'cortes.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')
                    ->where('business_id', auth()->user()->business_id),
            ],
            'cortes.*.kg'         => ['required', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ]);

        $entry = BovedaEntry::where('id', $data['boveda_entry_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        abort_if($entry->despiece_completado_at !== null, 422, 'Este despiece ya fue procesado.');

        $cortesConKg = collect($data['cortes'])->filter(fn ($c) => (float) $c['kg'] > 0);

        if ($cortesConKg->isEmpty()) {
            return response()->json(['error' => 'Debes registrar al menos un corte con kg mayor a 0.'], 422);
        }

        $totalCortes = round($cortesConKg->sum(fn ($c) => (float) $c['kg']), 3);
        $kgSurtido   = round((float) $entry->kg_surtido_vitrina, 3);
        $merma       = round($kgSurtido - $totalCortes, 3);

        if ($totalCortes > $kgSurtido) {
            return response()->json([
                'error' => "La suma de cortes ({$totalCortes} kg) supera los kg surtidos ({$kgSurtido} kg).",
            ], 422);
        }

        DB::transaction(function () use ($entry, $cortesConKg, $merma, $kgSurtido, $businessId, $user, $data): void {
            foreach ($cortesConKg as $corte) {
                InventoryEntry::create([
                    'business_id'     => $businessId,
                    'product_id'      => $corte['product_id'],
                    'boveda_entry_id' => $entry->id,
                    'quantity_kg'     => (float) $corte['kg'],
                    'waste_kg'        => 0,
                    'location'        => 'vitrina',
                    'notes'           => 'Despiece ' . $entry->product_type . ' #' . $entry->id,
                    'entered_at'      => now(),
                    'created_by'      => $user->id,
                ]);
            }

            $entry->update(['despiece_completado_at' => now()]);
            if ($merma > 0) {
                $entry->increment('waste_kg', $merma);
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'fabrica.despiece',
                'model_type'  => BovedaEntry::class,
                'model_id'    => $entry->id,
                'new_values'  => [
                    'total_cortes' => $kgSurtido,
                    'documentado'  => round($kgSurtido - $merma, 3),
                    'merma'        => $merma,
                ],
            ]);
        });

        return response()->json([
            'message' => 'Despiece registrado. Stock de vitrina actualizado.',
            'merma'   => $merma,
        ]);
    }
}
