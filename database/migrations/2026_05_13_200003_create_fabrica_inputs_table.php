<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fabrica_inputs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fabrica_batch_id')->constrained('fabrica_batches')->cascadeOnDelete();

            // Fuente del insumo — despiece_item(tipo=waste) o un inventory_entry directo
            $table->foreignId('despiece_item_id')->nullable()->constrained('despiece_items')->nullOnDelete();
            $table->foreignId('inventory_entry_id')->nullable()->constrained('inventory_entries')->nullOnDelete();

            $table->string('label', 100);          // descripción del insumo (ej: "merma res")
            $table->decimal('quantity_kg', 10, 3);
            $table->decimal('cost_usd', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fabrica_inputs');
    }
};
