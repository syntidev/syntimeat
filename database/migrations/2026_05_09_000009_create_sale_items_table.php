<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name', 120);
            $table->enum('input_type', ['weight', 'unit'])->default('weight');
            $table->decimal('quantity_value', 10, 3);
            $table->string('unit_label', 10)->default('kg');
            $table->decimal('price_per_kg_usd', 10, 2)->nullable();
            $table->decimal('price_per_unit_usd', 10, 2)->nullable();
            $table->decimal('subtotal_usd', 10, 2);
            $table->decimal('discount_usd', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
