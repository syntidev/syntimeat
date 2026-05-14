<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('despiece_items', function (Blueprint $table): void {
            // Waste items no tienen producto destino → nullable
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            $table->enum('tipo', [
                'corte_principal',
                'subproducto_vendible',
                'subproducto_fabricado',
                'waste',
            ])->default('corte_principal')->after('quantity_kg');
        });
    }

    public function down(): void
    {
        Schema::table('despiece_items', function (Blueprint $table): void {
            $table->dropColumn('tipo');
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
        });
    }
};
