<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::first();

        if (! $business) {
            $this->command->warn('CatalogSeeder: No hay ningún negocio en DB. Ejecuta el onboarding primero.');
            return;
        }

        $this->seedRes($business->id);
        $this->seedCerdo($business->id);
        $this->seedPollo($business->id);
        $this->seedCharcuteria($business->id);
        $this->seedEmbutidos($business->id);
        $this->seedVíveres($business->id);

        $this->command->info('CatalogSeeder: ' . Product::where('business_id', $business->id)->count() . ' productos creados.');
    }

    // ─── Res ──────────────────────────────────────────────────────────────────

    private function seedRes(int $businessId): void
    {
        $cat = $this->category($businessId, 'Res', '#EF4444', '🥩', 1);

        $premium  = $this->subcategory($cat->id, 'Premium', 1);
        $primera  = $this->subcategory($cat->id, 'Primera', 2);
        $segunda  = $this->subcategory($cat->id, 'Segunda', 3);

        $this->product($businessId, $cat->id, $premium->id, 'Lomito',              'weight', 2.36);
        $this->product($businessId, $cat->id, $premium->id, 'Solomo Abierto',      'weight', 2.00);
        $this->product($businessId, $cat->id, $premium->id, 'Punta Trasera',       'weight', 1.94);

        $this->product($businessId, $cat->id, $primera->id, 'Ganso',               'weight', 1.81);
        $this->product($businessId, $cat->id, $primera->id, 'Chocozuela',          'weight', 1.72);
        $this->product($businessId, $cat->id, $primera->id, 'Muchacho Cuadrado',   'weight', 1.67);

        $this->product($businessId, $cat->id, $segunda->id, 'Lagarto',             'weight', 1.33);
        $this->product($businessId, $cat->id, $segunda->id, 'Falda',               'weight', 1.17);
        $this->product($businessId, $cat->id, $segunda->id, 'Pecho',               'weight', 1.08);
    }

    // ─── Cerdo ────────────────────────────────────────────────────────────────

    private function seedCerdo(int $businessId): void
    {
        $cat = $this->category($businessId, 'Cerdo', '#8B5CF6', '🐷', 2);

        $this->product($businessId, $cat->id, null, 'Pernil',   'weight', 1.53);
        $this->product($businessId, $cat->id, null, 'Costilla', 'weight', 1.33);
        $this->product($businessId, $cat->id, null, 'Chuleta',  'weight', 1.25);
    }

    // ─── Pollo ────────────────────────────────────────────────────────────────

    private function seedPollo(int $businessId): void
    {
        $cat = $this->category($businessId, 'Pollo', '#2563EB', '🐔', 3);

        $this->product($businessId, $cat->id, null, 'Pollo Entero', 'weight', 1.17);
        $this->product($businessId, $cat->id, null, 'Pechuga',      'weight', 1.53);
        $this->product($businessId, $cat->id, null, 'Muslo',        'weight', 1.06);
        $this->product($businessId, $cat->id, null, 'Hígado',       'weight', 0.72);
    }

    // ─── Charcutería ──────────────────────────────────────────────────────────

    private function seedCharcuteria(int $businessId): void
    {
        $cat = $this->category($businessId, 'Charcutería', '#06B6D4', '🧀', 4);

        $this->product($businessId, $cat->id, null, 'Jamón de Pierna', 'weight', 2.22);
        $this->product($businessId, $cat->id, null, 'Queso Amarillo',  'weight', 2.50);
        $this->product($businessId, $cat->id, null, 'Mortadela',       'weight', 1.44);
        $this->product($businessId, $cat->id, null, 'Pernil Ahumado',  'weight', 2.78);
    }

    // ─── Embutidos ────────────────────────────────────────────────────────────

    private function seedEmbutidos(int $businessId): void
    {
        $cat = $this->category($businessId, 'Embutidos', '#F59E0B', '🌭', 5);

        $this->product($businessId, $cat->id, null, 'Chorizo',    'unit', null, 0.56);
        $this->product($businessId, $cat->id, null, 'Salchichón', 'unit', null, 1.11);
        $this->product($businessId, $cat->id, null, 'Queso de Mano', 'unit', null, 2.22);
    }

    // ─── Víveres ────────────────────────────────────────────────────────────

    private function seedVíveres(int $businessId): void
    {
        $cat = $this->category($businessId, 'Víveres', '#10B981', '🛒', 6);

        $this->product($businessId, $cat->id, null, 'Malta',         'unit', null, 0.28);
        $this->product($businessId, $cat->id, null, 'Refresco 2L',   'unit', null, 0.83);
        $this->product($businessId, $cat->id, null, 'Aceite 1L',     'unit', null, 1.11);
        $this->product($businessId, $cat->id, null, 'Mayonesa 445g', 'unit', null, 1.67);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function category(int $businessId, string $name, string $color, string $icon, int $sort): Category
    {
        return Category::firstOrCreate(
            ['business_id' => $businessId, 'name' => $name],
            ['color' => $color, 'icon' => $icon, 'sort_order' => $sort, 'active' => true],
        );
    }

    private function subcategory(int $categoryId, string $name, int $sort): Subcategory
    {
        return Subcategory::firstOrCreate(
            ['category_id' => $categoryId, 'name' => $name],
            ['sort_order' => $sort, 'active' => true],
        );
    }

    private function product(
        int $businessId,
        int $categoryId,
        ?int $subcategoryId,
        string $name,
        string $saleMode,
        ?float $priceKg = null,
        ?float $priceUnit = null,
    ): void {
        Product::firstOrCreate(
            ['business_id' => $businessId, 'name' => $name, 'category_id' => $categoryId],
            [
                'subcategory_id'     => $subcategoryId,
                'sale_mode'          => $saleMode,
                'base_unit_label'    => $saleMode === 'weight' ? 'kg' : 'und',
                'fraction_allowed'   => $saleMode === 'weight',
                'price_per_kg_usd'   => $priceKg,
                'price_per_unit_usd' => $priceUnit,
                'min_stock'          => 0,
                'sort_order'         => 0,
                'active'             => true,
            ],
        );
    }
}
