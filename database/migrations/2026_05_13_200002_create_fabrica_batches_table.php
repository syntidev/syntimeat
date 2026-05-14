<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabrica_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Producto fabricado resultante
            $table->foreignId('output_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('output_kg', 10, 3);            // kg producidos
            $table->decimal('output_units', 10, 3)->default(0); // unidades (si aplica)

            // Costo total de inputs (en USD)
            $table->decimal('input_cost_usd', 10, 2)->default(0);

            $table->string('notes', 500)->nullable();
            $table->timestamp('produced_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabrica_batches');
    }
};
