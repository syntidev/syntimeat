<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BovedaEntry;
use App\Models\BovedaProduct;
use App\Models\Business;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetDemoData extends Command
{
    protected $signature   = 'demo:reset {--business=1 : ID del negocio} {--force : Omitir confirmación}';
    protected $description = 'Limpia inventario y bóveda y crea 3 entradas demo (res, cerdo, pollo)';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');

        $business = Business::find($businessId);
        if (! $business) {
            $this->error("Negocio ID {$businessId} no encontrado.");
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("¿Borrar TODOS los registros de inventario y bóveda de \"{$business->name}\"? Esto no se puede deshacer.")) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($businessId): void {
            // ── Limpiar tablas transaccionales ───────────────────────────────
            DB::table('despiece_items')
                ->whereIn('despiece_log_id', fn ($q) => $q->select('id')->from('despiece_logs')->where('business_id', $businessId))
                ->delete();
            DB::table('despiece_logs')->where('business_id', $businessId)->delete();
            DB::table('fabrica_inputs')
                ->whereIn('fabrica_batch_id', fn ($q) => $q->select('id')->from('fabrica_batches')->where('business_id', $businessId))
                ->delete();
            DB::table('fabrica_batches')->where('business_id', $businessId)->delete();
            DB::table('inventory_entries')->where('business_id', $businessId)->delete();
            DB::table('boveda_entries')->where('business_id', $businessId)->delete();
            DB::table('activity_logs')->where('business_id', $businessId)->whereIn('action', [
                'boveda.entry', 'boveda.surte', 'boveda.close', 'boveda.merma',
                'despiece', 'fabrica.batch',
            ])->delete();

            $this->info('Tablas limpiadas.');

            // ── Asegurar productos bóveda en catálogo ────────────────────────
            $catRes   = DB::table('categories')->where('business_id', $businessId)->where('name', 'Res')->value('id');
            $catCerdo = DB::table('categories')->where('business_id', $businessId)->where('name', 'Cerdo')->value('id');
            $catPollo = DB::table('categories')->where('business_id', $businessId)->where('name', 'Pollo')->value('id');

            $productosBoveda = [
                ['name' => 'Medio Canal Res',       'category_id' => $catRes],
                ['name' => 'Canal Cerdo',            'category_id' => $catCerdo],
                ['name' => 'Pollo Entero Congelado', 'category_id' => $catPollo],
            ];

            foreach ($productosBoveda as $pb) {
                Product::firstOrCreate(
                    ['business_id' => $businessId, 'name' => $pb['name'], 'location' => 'boveda'],
                    [
                        'category_id'        => $pb['category_id'],
                        'sale_mode'          => 'weight',
                        'active'             => true,
                        'price_per_kg_usd'   => 0,
                        'fabricable'         => false,
                    ]
                );
            }

            $this->info('Productos bóveda verificados en catálogo.');

            $userId = DB::table('users')->where('business_id', $businessId)->value('id');
            $now    = now();

            // ── Entrada 1: Medio Canal Res ───────────────────────────────────
            $this->crearEntrada(
                businessId: $businessId,
                userId:     $userId,
                productType: 'Medio Canal Res',
                description: 'Media canal #1 — Proveedor García',
                kgEntrada:   82.500,
                costoUsd:    206.25,
                supplier:    'Matadero Central García',
                enteredAt:   $now->copy()->subDays(2),
            );

            // ── Entrada 2: Canal Cerdo ───────────────────────────────────────
            $this->crearEntrada(
                businessId: $businessId,
                userId:     $userId,
                productType: 'Canal Cerdo',
                description: 'Canal cerdo #2 — Granja Los Pinos',
                kgEntrada:   48.000,
                costoUsd:    96.00,
                supplier:    'Granja Los Pinos',
                enteredAt:   $now->copy()->subDay(),
            );

            // ── Entrada 3: Pollo ─────────────────────────────────────────────
            $this->crearEntrada(
                businessId: $businessId,
                userId:     $userId,
                productType: 'Pollo Entero Congelado',
                description: 'Caja pollos congelados — 10 unid',
                kgEntrada:   22.000,
                costoUsd:    44.00,
                supplier:    'Avícola El Rey',
                enteredAt:   $now,
            );

            $this->info('3 entradas bóveda creadas.');
        });

        $this->newLine();
        $this->table(
            ['Producto', 'Kg entrada', 'Costo USD', 'Estado'],
            BovedaEntry::where('business_id', $businessId)
                ->orderByDesc('entered_at')
                ->limit(3)
                ->get()
                ->map(fn ($e) => [
                    $e->product_type,
                    number_format((float) $e->kg_entrada, 3) . ' kg',
                    '$ ' . number_format((float) $e->costo_usd, 2),
                    'ACTIVA',
                ])->toArray()
        );

        $this->newLine();
        $this->line('<fg=green>✓ Demo listo. Entra a Bóveda y valida el flujo: Merma → Surtir → Plantilla → Fábrica.</>');

        return self::SUCCESS;
    }

    private function crearEntrada(
        int $businessId,
        int $userId,
        string $productType,
        string $description,
        float $kgEntrada,
        float $costoUsd,
        string $supplier,
        \Carbon\Carbon $enteredAt,
    ): void {
        $entry = BovedaEntry::create([
            'business_id'  => $businessId,
            'product_type' => $productType,
            'description'  => $description,
            'kg_entrada'   => $kgEntrada,
            'costo_usd'    => $costoUsd,
            'supplier'     => $supplier,
            'entered_at'   => $enteredAt,
        ]);

        // Espejo en inventory_entries para que Fábrica/Despiece pueda leerlo
        $product = Product::where('business_id', $businessId)
            ->where('location', 'boveda')
            ->where('name', $productType)
            ->first();

        if ($product) {
            InventoryEntry::create([
                'business_id'     => $businessId,
                'product_id'      => $product->id,
                'boveda_entry_id' => $entry->id,
                'quantity_kg'     => $kgEntrada,
                'waste_kg'        => 0,
                'cost_per_kg_usd' => $kgEntrada > 0 ? round($costoUsd / $kgEntrada, 4) : null,
                'location'        => 'boveda',
                'notes'           => 'Entrada bóveda #' . $entry->id,
                'entered_at'      => $enteredAt,
                'created_by'      => $userId,
            ]);
        }

        DB::table('activity_logs')->insert([
            'business_id' => $businessId,
            'user_id'     => $userId,
            'action'      => 'boveda.entry',
            'model_type'  => 'BovedaEntry',
            'model_id'    => $entry->id,
            'new_values'  => json_encode(['kg' => $kgEntrada, 'producto' => $productType]),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
