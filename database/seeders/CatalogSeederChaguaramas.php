<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BovedaProduct;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class CatalogSeederChaguaramas extends Seeder
{
    public function run(): void
    {
        $business = Business::firstOrFail();
        $bid      = $business->id;
        $branchId = Branch::where('business_id', $bid)->first()?->id;

        $this->command->info('Sembrando catálogo Chaguaramas…');

        // ─── Categorías ───────────────────────────────────────────────────────

        $catBoveda    = $this->cat($bid, 'Bóveda',       '#64748B', 0);
        $catRes       = $this->cat($bid, 'Res',           '#EF4444', 1);
        $catPollo     = $this->cat($bid, 'Pollo',         '#2563EB', 2);
        $catCerdo     = $this->cat($bid, 'Cerdo',         '#8B5CF6', 3);
        $catCharc     = $this->cat($bid, 'Charcutería',   '#06B6D4', 4);
        $catTrastes   = $this->cat($bid, 'Trastes',       '#F97316', 5);
        $catEmbutidos = $this->cat($bid, 'Embutidos',     '#F59E0B', 6);
        $catDespensa  = $this->cat($bid, 'Despensa',      '#10B981', 7);

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

        $resItems = [
            'Premium',
            'Primera',
            'Segunda',
            'Costilla',
            'Hueso Redondo',
            'Hueso Rojo',
            'Rabo',
            'Recortes de Res',
            'Chorizo Criollo',
        ];

        foreach ($resItems as $i => $nombre) {
            $fabricable = $nombre === 'Chorizo Criollo';
            $this->producto($bid, $branchId, $catRes->id, $nombre, 'weight', 'kg', 'vitrina', $i, $fabricable);
        }

        // ─── POLLO (vitrina, weight) ──────────────────────────────────────────

        $polloItems = [
            'Pollo Entero Tipo A',
            'Pollo Entero Tipo B',
            'Pollo Picado',
            'Muslo',
            'Pechuga',
            'Ala',
            'Molleja',
            'Hígado de Pollo',
            'Patas',
        ];

        foreach ($polloItems as $i => $nombre) {
            $this->producto($bid, $branchId, $catPollo->id, $nombre, 'weight', 'kg', 'vitrina', $i);
        }

        // ─── CERDO (vitrina, weight) ──────────────────────────────────────────

        $cerdoItems = [
            'Chuleta de Cerdo',
            'Costilla de Cerdo',
            'Recorte de Cerdo',
        ];

        foreach ($cerdoItems as $i => $nombre) {
            $this->producto($bid, $branchId, $catCerdo->id, $nombre, 'weight', 'kg', 'vitrina', $i);
        }

        // ─── CHARCUTERÍA (vitrina, weight) ────────────────────────────────────

        $charcItems = [
            'Jamón de Pierna',
            'Queso Amarillo',
            'Mortadela',
            'Jamón de Espalda',
        ];

        foreach ($charcItems as $i => $nombre) {
            $this->producto($bid, $branchId, $catCharc->id, $nombre, 'weight', 'kg', 'vitrina', $i);
        }

        // ─── TRASTES (vitrina, weight) ────────────────────────────────────────

        $trastes = [
            'Hígado de Res',
            'Lengua de Res',
            'Corazón de Res',
            'Bofe',
            'Riñón',
            'Pajarilla',
            'Mondongo',
            'Pata de Res',
            'Chinchurria',
        ];

        foreach ($trastes as $i => $nombre) {
            $this->producto($bid, $branchId, $catTrastes->id, $nombre, 'weight', 'kg', 'vitrina', $i);
        }

        // ─── EMBUTIDOS (vitrina, unit) ────────────────────────────────────────

        $embutidos = [
            'Chorizo Nacional',
            'Salchichón',
        ];

        foreach ($embutidos as $i => $nombre) {
            $this->producto($bid, $branchId, $catEmbutidos->id, $nombre, 'unit', 'und', 'vitrina', $i);
        }

        // ─── DESPENSA (despensa, unit) ────────────────────────────────────────

        $despensa = [
            'Malta',
            'Refresco 2L',
            'Aceite 1L',
            'Mayonesa 445g',
        ];

        foreach ($despensa as $i => $nombre) {
            $this->producto($bid, $branchId, $catDespensa->id, $nombre, 'unit', 'und', 'despensa', $i);
        }

        $total = Product::where('business_id', $bid)->count();
        $this->command->info("✅ Catálogo listo — {$total} productos registrados.");
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function cat(int $bid, string $name, string $color, int $order): Category
    {
        return Category::firstOrCreate(
            ['business_id' => $bid, 'name' => $name],
            ['color' => $color, 'sort_order' => $order, 'active' => true],
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
    ): Product {
        return Product::updateOrCreate(
            ['business_id' => $bid, 'name' => $name],
            [
                'branch_id'          => $branchId,
                'category_id'        => $categoryId,
                'sale_mode'          => $saleMode,
                'base_unit_label'    => $unitLabel,
                'location'           => $location,
                'price_per_kg_usd'   => $saleMode === 'weight' ? 0 : null,
                'price_per_unit_usd' => $saleMode === 'unit'   ? 0 : null,
                'fraction_allowed'   => $saleMode === 'weight',
                'fabricable'         => $fabricable,
                'active'             => true,
                'sort_order'         => $sortOrder,
                'min_stock'          => 0,
            ],
        );
    }
}
