<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\BovedaEntry;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\DespieceItem;
use App\Models\DespieceLog;
use App\Models\InventoryEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\DollarRateService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestFlowSeeder extends Seeder
{
    private Business $business;
    private User $user;
    private float $tasa;
    private BovedaEntry $bovedaEntry;
    private DespieceLog $despieceLog;

    /** @var array<string, Product> */
    private array $products = [];

    public function run(): void
    {
        $this->business = Business::firstOr(fn () => Business::factory()->create(['name' => 'SYNTImeat Test']));
        $this->user = User::firstOr(fn () => User::factory()->create(['name' => 'Admin', 'email' => 'admin@syntimeat.test', 'business_id' => $this->business->id]));

        $rateService = app(DollarRateService::class);
        $this->tasa = $rateService->getTodayRate();

        $this->ensureProducts();

        $this->step1Boveda();
        $this->step2Despiece();
        $this->step3Inventario();
        $this->step4Ventas();
        $this->step5ActivityLog();

        $this->showResumen();
    }

    // ─── Productos ─────────────────────────────────────────────────────────────

    private function ensureProducts(): void
    {
        $catRes = Category::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'Res'],
            ['color' => '#EF4444', 'sort_order' => 1, 'active' => true],
        );

        $catDespensa = Category::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'Víveres'],
            ['color' => '#10B981', 'sort_order' => 6, 'active' => true],
        );

        $catBoveda = Category::firstOrCreate(
            ['business_id' => $this->business->id, 'name' => 'Bóveda'],
            ['color' => '#64748B', 'sort_order' => 0, 'active' => true],
        );

        $definitions = [
            ['name' => 'Medio Canal Res', 'category_id' => $catBoveda->id, 'sale_mode' => 'weight', 'price_per_kg_usd' => 0, 'location' => 'boveda', 'base_unit_label' => 'kg', 'fraction_allowed' => false],
            ['name' => 'Premium',         'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 2.36, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Primera',         'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 1.81, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Segunda',         'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 1.33, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Costilla',        'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 1.17, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Hueso Rojo',      'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 0.80, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Hueso Redondo',   'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 0.60, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Chorizo Criollo', 'category_id' => $catRes->id,     'sale_mode' => 'weight', 'price_per_kg_usd' => 3.50, 'location' => 'vitrina', 'base_unit_label' => 'kg', 'fraction_allowed' => true],
            ['name' => 'Malta',           'category_id' => $catDespensa->id, 'sale_mode' => 'unit',   'price_per_unit_usd' => 0.28, 'location' => 'despensa', 'base_unit_label' => 'und', 'fraction_allowed' => false],
        ];

        foreach ($definitions as $def) {
            $product = Product::firstOrCreate(
                ['business_id' => $this->business->id, 'name' => $def['name']],
                [
                    'category_id'         => $def['category_id'],
                    'sale_mode'           => $def['sale_mode'],
                    'price_per_kg_usd'    => $def['price_per_kg_usd'] ?? null,
                    'price_per_unit_usd'  => $def['price_per_unit_usd'] ?? null,
                    'location'            => $def['location'],
                    'base_unit_label'     => $def['base_unit_label'],
                    'fraction_allowed'    => $def['fraction_allowed'],
                    'min_stock'           => 0,
                    'sort_order'          => 0,
                    'active'              => true,
                ],
            );
            $this->products[$def['name']] = $product;
        }
    }

    private function p(string $name): Product
    {
        return $this->products[$name];
    }

    // ─── PASO 1 — BÓVEDA ───────────────────────────────────────────────────────

    private function step1Boveda(): void
    {
        $this->bovedaEntry = BovedaEntry::create([
            'business_id'    => $this->business->id,
            'product_type'   => 'Medio Canal Res',
            'description'    => 'Media Canal Res de primera calidad',
            'kg_entrada'     => 180,
            'costo_usd'      => 270,
            'supplier'       => 'Frigorífico El Llano',
            'entered_at'     => Carbon::today()->setHour(6)->setMinute(0),
        ]);

        $this->command->info('PASO 1 ✓ Bóveda: Medio Canal Res 180kg, $270.00');
    }

    // ─── PASO 2 — DESPIECE ─────────────────────────────────────────────────────

    private function step2Despiece(): void
    {
        $this->despieceLog = DespieceLog::create([
            'business_id'      => $this->business->id,
            'user_id'          => $this->user->id,
            'product_id'       => $this->p('Medio Canal Res')->id,
            'location_from'    => 'boveda',
            'quantity_kg_from' => 180,
            'notes'            => 'Despiece completo de Media Canal Res',
            'processed_at'     => Carbon::today()->setHour(6)->setMinute(30),
        ]);

        $items = [
            ['tipo' => 'corte_principal',       'product' => 'Premium',         'kg' => 25],
            ['tipo' => 'corte_principal',       'product' => 'Primera',         'kg' => 45],
            ['tipo' => 'corte_principal',       'product' => 'Segunda',         'kg' => 35],
            ['tipo' => 'corte_principal',       'product' => 'Costilla',        'kg' => 20],
            ['tipo' => 'subproducto_vendible',   'product' => 'Hueso Rojo',     'kg' => 12],
            ['tipo' => 'subproducto_vendible',   'product' => 'Hueso Redondo',  'kg' => 10],
            ['tipo' => 'subproducto_fabricado',  'product' => 'Chorizo Criollo','kg' => 8],
            ['tipo' => 'waste',                  'product' => null,              'kg' => 15],
            ['tipo' => 'waste',                  'product' => null,              'kg' => 10],
        ];

        foreach ($items as $item) {
            DespieceItem::create([
                'despiece_log_id' => $this->despieceLog->id,
                'product_id'      => $item['product'] ? $this->p($item['product'])->id : null,
                'quantity_kg'     => $item['kg'],
                'tipo'            => $item['tipo'],
            ]);
        }

        DB::table('boveda_entries')
            ->where('id', $this->bovedaEntry->id)
            ->update([
                'kg_surtido_vitrina' => 155,
                'waste_kg'           => 25,
            ]);

        $this->command->info('PASO 2 ✓ Despiece: 180kg distribuidos, waste=25kg, vitrina=155kg');
    }

    // ─── PASO 3 — INVENTARIO VITRINA ───────────────────────────────────────────

    private function step3Inventario(): void
    {
        $enteredAt = Carbon::today()->setHour(7)->setMinute(0);

        $entries = [
            ['product' => 'Premium',         'kg' => 25],
            ['product' => 'Primera',         'kg' => 45],
            ['product' => 'Segunda',         'kg' => 35],
            ['product' => 'Costilla',        'kg' => 20],
            ['product' => 'Hueso Rojo',      'kg' => 12],
            ['product' => 'Hueso Redondo',   'kg' => 10],
            ['product' => 'Chorizo Criollo', 'kg' => 8],
        ];

        foreach ($entries as $entry) {
            InventoryEntry::create([
                'business_id'     => $this->business->id,
                'product_id'      => $this->p($entry['product'])->id,
                'quantity_kg'     => $entry['kg'],
                'cost_per_kg_usd' => $this->p($entry['product'])->price_per_kg_usd,
                'location'        => 'vitrina',
                'notes'           => 'Entrada desde despiece de Media Canal Res',
                'entered_at'      => $enteredAt,
                'created_by'      => $this->user->id,
            ]);
        }

        $this->command->info('PASO 3 ✓ Inventario: 7 productos en vitrina = 155kg');
    }

    // ─── PASO 4 — VENTAS ───────────────────────────────────────────────────────

    private function step4Ventas(): void
    {
        $register = CashRegister::where('business_id', $this->business->id)
            ->whereNull('closed_at')
            ->first();

        if (! $register) {
            $register = CashRegister::create([
                'business_id'        => $this->business->id,
                'name'               => 'Caja Principal',
                'opened_at'          => Carbon::today()->setHour(6)->setMinute(0),
                'opening_amount_usd' => round(500 / $this->tasa, 2),
                'rate_at_opening'    => $this->tasa,
                'notes'              => 'Apertura automática TestFlowSeeder',
                'opened_by'          => $this->user->id,
            ]);
        }

        // Ticket VEN-T001
        $this->crearTicket('VEN-T001', 'efectivo', Carbon::today()->setHour(8)->setMinute(0), $register->id, [
            ['product' => 'Premium',         'kg' => 25],
            ['product' => 'Chorizo Criollo', 'kg' => 3],
        ]);

        // Ticket VEN-T002
        $this->crearTicket('VEN-T002', 'pago_movil', Carbon::today()->setHour(8)->setMinute(30), $register->id, [
            ['product' => 'Primera',  'kg' => 30],
            ['product' => 'Costilla', 'kg' => 10],
        ]);

        // Ticket VEN-T003
        $this->crearTicket('VEN-T003', 'efectivo', Carbon::today()->setHour(9)->setMinute(0), $register->id, [
            ['product' => 'Premium',    'kg' => 20],
            ['product' => 'Hueso Rojo', 'kg' => 5],
            ['product' => 'Malta',      'unidades' => 2],
        ]);

        $this->command->info('PASO 4 ✓ Ventas: 3 tickets pagados');
    }

    /**
     * @param array<int, array{product: string, kg?: float, unidades?: int}> $itemsData
     */
    private function crearTicket(string $ticket, string $metodo, Carbon $soldAt, int $cashRegisterId, array $itemsData): void
    {
        $totalUsd = 0;
        $items = [];

        foreach ($itemsData as $data) {
            $product  = $this->p($data['product']);
            $isWeight = $product->sale_mode === 'weight';
            $qty      = (float) ($data['kg'] ?? $data['unidades'] ?? 0);
            $price    = $isWeight
                ? (float) $product->price_per_kg_usd
                : (float) $product->price_per_unit_usd;
            $subtotal = round($qty * $price, 2);

            $items[] = [
                'product_id'          => $product->id,
                'product_name'        => $product->name,
                'input_type'          => $isWeight ? 'weight' : 'unit',
                'quantity_value'      => $qty,
                'unit_label'          => $isWeight ? 'kg' : 'und',
                'price_per_kg_usd'    => $isWeight ? $price : null,
                'price_per_unit_usd'  => $isWeight ? null : $price,
                'subtotal_usd'        => $subtotal,
                'discount_usd'        => 0,
            ];

            $totalUsd += $subtotal;
        }

        $totalUsd = round($totalUsd, 2);

        $sale = Sale::create([
            'business_id'        => $this->business->id,
            'ticket_number'      => $ticket,
            'status'             => 'paid',
            'total_usd'          => $totalUsd,
            'payment_method'     => $metodo,
            'amount_received_usd'=> $totalUsd,
            'change_usd'         => 0,
            'rate_used'          => $this->tasa,
            'total_bs'           => round($totalUsd * $this->tasa, 2),
            'origin'             => 'onsite',
            'channel'            => 'physical',
            'sold_at'            => $soldAt,
            'cashier_id'         => $this->user->id,
            'cash_register_id'   => $cashRegisterId,
        ]);

        foreach ($items as $item) {
            $item['sale_id'] = $sale->id;
            SaleItem::create($item);
        }
    }

    // ─── PASO 5 — ACTIVITY LOG ─────────────────────────────────────────────────

    private function step5ActivityLog(): void
    {
        $logs = [
            [
                'action'     => 'boveda.entry',
                'model_type' => BovedaEntry::class,
                'model_id'   => $this->bovedaEntry->id,
                'new_values' => [
                    'product_type' => 'Medio Canal Res',
                    'kg_entrada'   => 180,
                    'costo_usd'    => 270,
                    'supplier'     => 'Frigorífico El Llano',
                ],
            ],
            [
                'action'     => 'despiece.processed',
                'model_type' => DespieceLog::class,
                'model_id'   => $this->despieceLog->id,
                'new_values' => [
                    'product'      => 'Medio Canal Res',
                    'kg_procesado' => 180,
                    'items'        => 9,
                    'waste_kg'     => 25,
                    'vitrina_kg'   => 155,
                ],
            ],
            [
                'action'     => 'inventory.entries',
                'model_type' => InventoryEntry::class,
                'model_id'   => null,
                'new_values' => [
                    'entries' => 7,
                    'total_kg'=> 155,
                    'location'=> 'vitrina',
                ],
            ],
            [
                'action'     => 'sales.paid',
                'model_type' => Sale::class,
                'model_id'   => null,
                'new_values' => [
                    'tickets' => 3,
                    'total_usd' => 166.66,
                ],
            ],
        ];

        foreach ($logs as $log) {
            ActivityLog::create([
                'business_id' => $this->business->id,
                'user_id'     => $this->user->id,
                'action'      => $log['action'],
                'model_type'  => $log['model_type'],
                'model_id'    => $log['model_id'],
                'new_values'  => $log['new_values'],
                'ip_address'  => '127.0.0.1',
            ]);
        }

        $this->command->info('PASO 5 ✓ Activity Log: 4 eventos registrados');
    }

    // ─── RESUMEN ───────────────────────────────────────────────────────────────

    private function showResumen(): void
    {
        $this->command->newLine();
        $this->command->info('══════════════════════════════════════════');
        $this->command->info('      RESUMEN TestFlowSeeder');
        $this->command->info('══════════════════════════════════════════');
        $this->command->info('  Total kg entrados bóveda:    180');
        $this->command->info('  Total kg a vitrina:          155');
        $this->command->info('  Waste:                        25');
        $this->command->info('  Ventas generadas:              3 tickets');
        $this->command->info('  Total USD vendido estimado: $166.66');
        $this->command->info('══════════════════════════════════════════');
    }
}
