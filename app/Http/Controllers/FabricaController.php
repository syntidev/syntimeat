<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FabricaBatch;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return Inertia::render('Fabrica/Index', [
            'fabricables' => $fabricables,
            'ingredientes'=> $ingredientes,
            'stockMap'    => $stockMap,
            'historial'   => $historial,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        $data = $request->validate([
            'output_product_id'          => ['required', 'integer', 'exists:products,id'],
            'output_kg'                  => ['required', 'numeric', 'min:0.001'],
            'output_units'               => ['sometimes', 'numeric', 'min:0'],
            'notes'                      => ['nullable', 'string', 'max:500'],
            'produced_at'                => ['required', 'date'],
            'inputs'                     => ['required', 'array', 'min:1'],
            'inputs.*.product_id'        => ['required', 'integer', 'exists:products,id'],
            'inputs.*.quantity_kg'       => ['required', 'numeric', 'min:0.001'],
            'inputs.*.cost_usd'          => ['sometimes', 'numeric', 'min:0'],
        ]);

        // Verificar que el producto output pertenece al negocio y es fabricable
        abort_unless(
            Product::where('id', $data['output_product_id'])
                ->where('business_id', $businessId)
                ->where('fabricable', true)
                ->exists(),
            403,
            'Producto no es fabricable.'
        );

        // Verificar que todos los ingredientes pertenecen al negocio
        $inputIds = array_column($data['inputs'], 'product_id');
        $validIds = Product::whereIn('id', $inputIds)
            ->where('business_id', $businessId)
            ->pluck('id')
            ->all();

        foreach ($inputIds as $pid) {
            abort_unless(in_array($pid, $validIds, true), 403, 'Ingrediente no pertenece al negocio.');
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
}
