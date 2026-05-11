<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    // Parámetros por categoría: [qty_min, qty_max, waste_min_pct, waste_max_pct, cost_usd_per_kg]
    private array $config = [
        'Res'         => [40,  90, 0.04, 0.07, 4.50],
        'Cerdo'       => [30,  75, 0.03, 0.06, 3.20],
        'Pollo'       => [25,  60, 0.05, 0.08, 2.40],
        'Charcutería' => [12,  30, 0.02, 0.04, 5.00],
        'Embutidos'   => [12,  36, 0.00, 0.01, 3.80],
        'Abarrotes'   => [12,  48, 0.00, 0.00, 1.50],
    ];

    public function run(): void
    {
        $business = Business::first();

        if (! $business) {
            $this->command->warn('InventorySeeder: no hay negocio registrado.');
            return;
        }

        $user = User::where('business_id', $business->id)->first();

        if (! $user) {
            $this->command->warn('InventorySeeder: no hay usuario registrado.');
            return;
        }

        $products = Product::with('category')
            ->where('business_id', $business->id)
            ->where('active', true)
            ->get();

        $enteredAt = now()->setTime(7, 30, 0); // Ingreso a las 7:30 AM de hoy
        $count     = 0;

        foreach ($products as $product) {
            $categoryName = $product->category?->name ?? 'Abarrotes';
            $cfg          = $this->config[$categoryName] ?? $this->config['Abarrotes'];

            [$qMin, $qMax, $wMin, $wMax, $cost] = $cfg;

            // Cantidad aleatoria con decimal de 3 dígitos
            $qty   = round($qMin + mt_rand(0, ($qMax - $qMin) * 1000) / 1000, 3);
            $wPct  = $wMin + mt_rand(0, (int) (($wMax - $wMin) * 1000)) / 1000;
            $waste = round($qty * $wPct, 3);

            // Escalonar las horas de ingreso (varios en el día)
            $enteredAt = $enteredAt->copy()->addMinutes(mt_rand(3, 18));

            InventoryEntry::firstOrCreate(
                [
                    'business_id' => $business->id,
                    'product_id'  => $product->id,
                    'entered_at'  => $enteredAt->format('Y-m-d H:i:s'),
                ],
                [
                    'quantity_kg'     => $qty,
                    'waste_kg'        => $waste,
                    'cost_per_kg_usd' => $cost,
                    'supplier'        => 'Matadero Chaguaramas',
                    'notes'           => null,
                    'created_by'      => $user->id,
                ]
            );

            $count++;
        }

        $this->command->info("InventorySeeder: {$count} entradas de inventario creadas para hoy.");
    }
}
