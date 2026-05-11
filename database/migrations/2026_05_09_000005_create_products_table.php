<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('subcategory_id')->nullable()->constrained('subcategories')->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->enum('sale_mode', ['weight', 'unit'])->default('weight');
            $table->string('base_unit_label', 10)->default('kg');
            $table->boolean('fraction_allowed')->default(true);
            $table->decimal('price_per_kg_usd', 10, 2)->nullable();
            $table->decimal('price_per_unit_usd', 10, 2)->nullable();
            $table->decimal('cost_per_kg_usd', 10, 2)->nullable();
            $table->decimal('min_stock', 8, 3)->default(0);
            $table->string('image_path')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
