<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boveda_products', function (Blueprint $table): void {
            // true  = Canal/pieza entera → requiere despiece antes de sumarse a vitrina
            // false = Producto terminado  → va directo al inventario de vitrina
            $table->boolean('requires_despiece')->default(true)->after('unit');

            // Solo aplica cuando requires_despiece = false.
            // Apunta al producto de vitrina que recibe el stock directamente.
            $table->foreignId('vitrina_product_id')
                ->nullable()
                ->after('requires_despiece')
                ->constrained('products')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boveda_products', function (Blueprint $table): void {
            $table->dropForeign(['vitrina_product_id']);
            $table->dropColumn(['requires_despiece', 'vitrina_product_id']);
        });
    }
};
