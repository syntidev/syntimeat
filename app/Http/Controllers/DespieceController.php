<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DespieceItem;
use App\Models\DespieceLog;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DespieceController extends Controller
{
    public function index(): Response
    {
        $businessId = Auth::user()->business_id;

        // Productos con stock en bóveda (location = boveda)
        $bovedaStock = InventoryEntry::query()
            ->where('business_id', $businessId)
            ->where('location', 'boveda')
            ->with('product.category')
            ->selectRaw('product_id, SUM(net_kg) as total_kg')
            ->groupBy('product_id')
            ->having('total_kg', '>', 0)
            ->get()
            ->map(function ($entry) {
                return [
                    'product_id'   => $entry->product_id,
                    'product_name' => $entry->product->name ?? '—',
                    'category'     => $entry->product->category->name ?? '—',
                    'total_kg'     => (float) $entry->total_kg,
                ];
            });

        // Todos los productos activos del negocio (para los ítems destino)
        $products = Product::where('business_id', $businessId)
            ->where('active', true)
            ->where('sale_mode', 'weight')
            ->where('location', 'vitrina')
            ->with('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        // Historial de despieces (últimos 50)
        $history = DespieceLog::where('business_id', $businessId)
            ->with([
                'product:id,name',
                'user:id,name',
                'items.product:id,name',
            ])
            ->orderByDesc('processed_at')
            ->limit(50)
            ->get();

        return Inertia::render('Despiece/Index', [
            'bovedaStock' => $bovedaStock,
            'products'    => $products,
            'history'     => $history,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tipoValues = ['corte_principal', 'subproducto_vendible', 'subproducto_fabricado', 'waste'];

        $validated = $request->validate([
            'product_id'              => ['required', 'integer', 'exists:products,id'],
            'quantity_kg_from'        => ['required', 'numeric', 'min:0.001'],
            'notes'                   => ['nullable', 'string', 'max:500'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.tipo'            => ['required', Rule::in($tipoValues)],
            'items.*.product_id'      => ['nullable', 'integer', 'exists:products,id'],
            'items.*.quantity_kg'     => ['required', 'numeric', 'min:0.001'],
        ]);

        // Items no-waste deben tener product_id
        $invalid = collect($validated['items'])->filter(
            fn ($i) => $i['tipo'] !== 'waste' && empty($i['product_id'])
        );
        if ($invalid->isNotEmpty()) {
            return back()->withErrors(['items' => 'Los ítems de corte/subproducto deben tener un producto destino.']);
        }

        $businessId = Auth::user()->business_id;
        $userId     = Auth::id();

        // Verificar que la suma de TODOS los kg (incluyendo waste) no supera el origen
        $totalItems = collect($validated['items'])->sum('quantity_kg');
        if ($totalItems > $validated['quantity_kg_from']) {
            return back()->withErrors([
                'items' => 'La suma de los cortes (' . number_format($totalItems, 3) . ' kg) supera el origen (' . number_format($validated['quantity_kg_from'], 3) . ' kg).',
            ]);
        }

        // Verificar stock disponible en bóveda
        $stockBoveda = InventoryEntry::where('business_id', $businessId)
            ->where('product_id', $validated['product_id'])
            ->where('location', 'boveda')
            ->sum(DB::raw('net_kg'));

        if ($stockBoveda < $validated['quantity_kg_from']) {
            return back()->withErrors([
                'quantity_kg_from' => 'Stock insuficiente en bóveda. Disponible: ' . number_format($stockBoveda, 3) . ' kg.',
            ]);
        }

        DB::transaction(function () use ($validated, $businessId, $userId): void {
            // Crear el log
            $log = DespieceLog::create([
                'business_id'      => $businessId,
                'user_id'          => $userId,
                'product_id'       => $validated['product_id'],
                'location_from'    => 'boveda',
                'quantity_kg_from' => $validated['quantity_kg_from'],
                'notes'            => $validated['notes'] ?? null,
                'processed_at'     => now(),
            ]);

            // Descontar de bóveda: entrada negativa (incluye waste — sale del stock)
            InventoryEntry::create([
                'business_id' => $businessId,
                'product_id'  => $validated['product_id'],
                'quantity_kg' => -$validated['quantity_kg_from'],
                'waste_kg'    => 0,
                'location'    => 'boveda',
                'notes'       => 'Despiece #' . $log->id,
                'entered_at'  => now(),
                'created_by'  => $userId,
            ]);

            foreach ($validated['items'] as $item) {
                // Registrar el ítem (waste → product_id null)
                DespieceItem::create([
                    'despiece_log_id' => $log->id,
                    'product_id'      => $item['tipo'] === 'waste' ? null : $item['product_id'],
                    'quantity_kg'     => $item['quantity_kg'],
                    'tipo'            => $item['tipo'],
                ]);

                // Solo los no-waste van a vitrina
                if ($item['tipo'] !== 'waste') {
                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'product_id'  => $item['product_id'],
                        'quantity_kg' => $item['quantity_kg'],
                        'waste_kg'    => 0,
                        'location'    => 'vitrina',
                        'notes'       => 'Despiece #' . $log->id . ' — ' . $item['tipo'],
                        'entered_at'  => now(),
                        'created_by'  => $userId,
                    ]);
                }
            }
        });

        return redirect()->route('despiece.index')->with('success', 'Despiece procesado correctamente.');
    }
}
