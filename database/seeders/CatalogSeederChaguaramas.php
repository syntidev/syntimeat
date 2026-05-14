<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeederChaguaramas extends Seeder
{


    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('subcategories')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $bid = Business::first()->id;

        // ─── Bóveda ───────────────────────────────────────────────────────────
        $boveda = Category::create(['business_id'=>$bid,'name'=>'Bóveda','color'=>'#64748B','sort_order'=>0]);
        foreach (['Medio Canal Res','Canal Cerdo','Pollo Entero Congelado','Jamón Pierna Sellado'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$boveda->id,'name'=>$n,'sale_mode'=>'weight','base_unit_label'=>'kg','price_per_kg_usd'=>0,'location'=>'boveda','active'=>true,'sort_order'=>$i]);
        }

        // ─── Res ──────────────────────────────────────────────────────────────
        $res = Category::create(['business_id'=>$bid,'name'=>'Res','color'=>'#EF4444','sort_order'=>1]);
        foreach (['Premium','Primera','Segunda','Costilla','Hueso Redondo','Hueso Rojo','Rabo','Chorizo Criollo'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$res->id,'name'=>$n,'sale_mode'=>'weight','base_unit_label'=>'kg','price_per_kg_usd'=>1.50,'location'=>'vitrina','active'=>true,'sort_order'=>$i]);
        }

        // ─── Pollo ────────────────────────────────────────────────────────────
        $pollo = Category::create(['business_id'=>$bid,'name'=>'Pollo','color'=>'#2563EB','sort_order'=>2]);
        foreach (['Pollo Entero Tipo A','Pollo Entero Tipo B','Pollo Picado','Muslo','Pechuga','Ala','Molleja','Hígado','Patas de Pollo'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$pollo->id,'name'=>$n,'sale_mode'=>'weight','base_unit_label'=>'kg','price_per_kg_usd'=>1.20,'location'=>'vitrina','active'=>true,'sort_order'=>$i]);
        }

        // ─── Cerdo ────────────────────────────────────────────────────────────
        $cerdo = Category::create(['business_id'=>$bid,'name'=>'Cerdo','color'=>'#8B5CF6','sort_order'=>3]);
        foreach (['Chuleta de Cerdo','Costilla de Cerdo'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$cerdo->id,'name'=>$n,'sale_mode'=>'weight','base_unit_label'=>'kg','price_per_kg_usd'=>1.40,'location'=>'vitrina','active'=>true,'sort_order'=>$i]);
        }

        // ─── Charcutería ──────────────────────────────────────────────────────
        $charc = Category::create(['business_id'=>$bid,'name'=>'Charcutería','color'=>'#06B6D4','sort_order'=>4]);
        foreach (['Jamón de Pierna','Queso Amarillo','Mortadela','Jamón de Espalda'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$charc->id,'name'=>$n,'sale_mode'=>'weight','base_unit_label'=>'kg','price_per_kg_usd'=>2.20,'location'=>'vitrina','active'=>true,'sort_order'=>$i]);
        }

        // ─── Trastes ──────────────────────────────────────────────────────────
        $trastes = Category::create(['business_id'=>$bid,'name'=>'Trastes','color'=>'#F97316','sort_order'=>5]);
        foreach (['Hígado de Res','Lengua de Res','Corazón de Res','Bofe','Riñón','Pajarilla','Mondongo','Pata de Res','Chinchurria'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$trastes->id,'name'=>$n,'sale_mode'=>'weight','base_unit_label'=>'kg','price_per_kg_usd'=>1.00,'location'=>'vitrina','active'=>true,'sort_order'=>$i]);
        }

        // ─── Embutidos ────────────────────────────────────────────────────────
        $emb = Category::create(['business_id'=>$bid,'name'=>'Embutidos','color'=>'#F59E0B','sort_order'=>6]);
        foreach (['Chorizo Nacional','Salchichón'] as $i=>$n) {
            Product::create(['business_id'=>$bid,'category_id'=>$emb->id,'name'=>$n,'sale_mode'=>'unit','base_unit_label'=>'und','price_per_unit_usd'=>0.56,'location'=>'vitrina','active'=>true,'sort_order'=>$i]);
        }

        // ─── Víveres ────────────────────────────────────────────────────────
        $abarr = Category::create(['business_id'=>$bid,'name'=>'Víveres','color'=>'#10B981','sort_order'=>7]);
        foreach ([['Malta',0.28],['Refresco 2L',0.83],['Aceite 1L',1.11],['Mayonesa 445g',1.67]] as $i=>[$n,$p]) {
            Product::create(['business_id'=>$bid,'category_id'=>$abarr->id,'name'=>$n,'sale_mode'=>'unit','base_unit_label'=>'und','price_per_unit_usd'=>$p,'location'=>'despensa','active'=>true,'sort_order'=>$i]);
        }
    }


}
