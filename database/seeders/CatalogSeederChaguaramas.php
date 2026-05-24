<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BovedaProduct;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeederChaguaramas extends Seeder
{
    public function run(): void
    {
        $business = Business::firstOrFail();
        $bid      = $business->id;
        $branchId = Branch::where('business_id', $bid)->first()?->id;

        $this->command->info('Sembrando catálogo Chaguaramas…');

        // ─── Categorías ───────────────────────────────────────────────────────

        $catBoveda    = $this->cat($bid, 'Bóveda',       '#64748B', 0, 'BOVEDA');
        $catRes       = $this->cat($bid, 'Res',           '#EF4444', 1, 'RES');
        $catPollo     = $this->cat($bid, 'Pollo',         '#2563EB', 2, 'POLLO');
        $catCerdo     = $this->cat($bid, 'Cerdo',         '#8B5CF6', 3, 'CERDO');
        $catCharc     = $this->cat($bid, 'Charcutería',   '#06B6D4', 4, 'CHARCUTERIA');
        $catTrastes   = $this->cat($bid, 'Trastes',       '#F97316', 5, 'TRASTES');
        $catViveres  = $this->cat($bid, 'Víveres',      '#10B981', 6, 'DESPENSA');

        // ─── BÓVEDA ───────────────────────────────────────────────────────────

        $bovedaItems = [
            'Medio Canal Res',
            'Canal Cerdo',
            'Pollo Entero Congelado',
            'Jamón Pierna Sellado',
        ];

        foreach ($bovedaItems as $i => $nombre) {
            $this->producto($bid, $branchId, $catBoveda->id, $nombre, 'weight', 'kg', 'boveda', $i);

            BovedaProduct::firstOrCreate(
                ['business_id' => $bid, 'name' => $nombre],
                ['unit' => 'kg', 'requires_despiece' => true, 'active' => true],
            );
        }

        // ─── RES (vitrina, weight) ────────────────────────────────────────────

        // [nombre => precio_usd]
        $resItems = [
            'Premium'        => 12.00,
            'Primera'        =>  8.00,
            'Segunda'        =>  6.00,
            'Costilla'       =>  5.00,
            'Hueso Redondo'  =>  2.00,
            'Hueso Rojo'     =>  1.50,
            'Rabo'           =>  4.00,
            'Chorizo Criollo'=>  7.00,
        ];

        foreach ($resItems as $nombre => $precio) {
            $fabricable = $nombre === 'Chorizo Criollo';
            $this->producto($bid, $branchId, $catRes->id, $nombre, 'weight', 'kg', 'vitrina', array_search($nombre, array_keys($resItems)), $fabricable, $precio);
        }

        // ─── POLLO (vitrina, weight) ──────────────────────────────────────────

        $polloItems = [
            'Pollo Entero Tipo A' => 4.00,
            'Pollo Entero Tipo B' => 3.50,
            'Pollo Picado'        => 2.00,
            'Muslo'               => 2.00,
            'Pechuga'             => 2.00,
            'Ala'                 => 2.00,
            'Molleja'             => 2.00,
            'Hígado de Pollo'     => 2.00,
            'Patas'               => 2.00,
        ];

        foreach ($polloItems as $nombre => $precio) {
            $this->producto($bid, $branchId, $catPollo->id, $nombre, 'weight', 'kg', 'vitrina', array_search($nombre, array_keys($polloItems)), false, $precio);
        }

        // ─── CERDO (vitrina, weight) ──────────────────────────────────────────

        $cerdoItems = [
            'Chuleta de Cerdo'  => 6.00,
            'Costilla de Cerdo' => 5.00,
        ];

        foreach ($cerdoItems as $nombre => $precio) {
            $this->producto($bid, $branchId, $catCerdo->id, $nombre, 'weight', 'kg', 'vitrina', array_search($nombre, array_keys($cerdoItems)), false, $precio);
        }

        // ─── CHARCUTERÍA (vitrina, weight + unit) ────────────────────────────

        $charcItems = [
            'Jamón de Pierna' => 2.00,
            'Queso Amarillo'  => 2.00,
            'Mortadela'       => 2.00,
            'Jamón de Espalda'=> 2.00,
        ];

        foreach ($charcItems as $nombre => $precio) {
            $this->producto($bid, $branchId, $catCharc->id, $nombre, 'weight', 'kg', 'vitrina', array_search($nombre, array_keys($charcItems)), false, $precio);
        }

        $charcUnit = [
            'Chorizo Nacional' => 2.00,
            'Salchichón'       => 2.00,
        ];

        foreach ($charcUnit as $nombre => $precio) {
            $this->producto($bid, $branchId, $catCharc->id, $nombre, 'unit', 'und', 'vitrina', count($charcItems) + array_search($nombre, array_keys($charcUnit)), false, $precio);
        }

        // ─── TRASTES (vitrina, weight) ────────────────────────────────────────

        $trastes = [
            'Hígado de Res'  => 2.00,
            'Lengua de Res'  => 2.00,
            'Corazón de Res' => 2.00,
            'Bofe'           => 2.00,
            'Riñón'          => 2.00,
            'Pajarilla'      => 2.00,
            'Mondongo'       => 2.00,
            'Pata de Res'    => 2.00,
            'Chinchurria'    => 2.00,
        ];

        foreach ($trastes as $nombre => $precio) {
            $this->producto($bid, $branchId, $catTrastes->id, $nombre, 'weight', 'kg', 'vitrina', array_search($nombre, array_keys($trastes)), false, $precio);
        }

        // ─── DESPENSA (despensa, unit) ────────────────────────────────────────

        $despensa = [
            'Malta',
            'Refresco 2L',
            'Aceite 1L',
            'Mayonesa 445g',
        ];

        foreach ($despensa as $i => $nombre) {
            $this->producto($bid, $branchId, $catViveres->id, $nombre, 'unit', 'und', 'despensa', $i);
        }

        // ─── Actualizar macro_category en registros existentes ───────────────

        $macroMap = [
            'Bóveda'      => 'BOVEDA',
            'Res'         => 'RES',
            'Pollo'       => 'POLLO',
            'Cerdo'       => 'CERDO',
            'Charcutería' => 'CHARCUTERIA',
            'Trastes'     => 'TRASTES',
            'Víveres'     => 'VIVERES',
        ];

        foreach ($macroMap as $name => $macro) {
            DB::table('categories')
                ->where('business_id', $bid)
                ->where('name', $name)
                ->update(['macro_category' => $macro]);
        }

        $total = Product::where('business_id', $bid)->count();
        $this->command->info("✅ Catálogo listo — {$total} productos registrados.");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function cat(int $bid, string $name, string $color, int $order, string $macro): Category
    {
        return Category::updateOrCreate(
            ['business_id' => $bid, 'name' => $name],
            ['color' => $color, 'sort_order' => $order, 'active' => true, 'macro_category' => $macro],
        );
    }

    private function producto(
        int $bid,
        ?int $branchId,
        int $categoryId,
        string $name,
        string $saleMode,
        string $unitLabel,
        string $location,
        int $sortOrder,
        bool $fabricable = false,
        float $price = 2.00,
    ): Product {
        return Product::updateOrCreate(
            ['business_id' => $bid, 'name' => $name],
            [
                'branch_id'          => $branchId,
                'category_id'        => $categoryId,
                'sale_mode'          => $saleMode,
                'base_unit_label'    => $unitLabel,
                'location'           => $location,
                'price_per_kg_usd'   => $saleMode === 'weight' ? $price : null,
                'price_per_unit_usd' => $saleMode === 'unit'   ? $price : null,
                'fraction_allowed'   => $saleMode === 'weight',
                'fabricable'         => $fabricable,
                'active'             => true,
                'sort_order'         => $sortOrder,
                'min_stock'          => 0,
            ],
        );
    }
}
