<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boveda_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('boveda_product', [
                'medio_canal_res',
                'canal_cerdo',
                'pollo_entero',
                'jamon_pierna',
                'otros',
            ]);
            $table->string('description', 100)->nullable();
            $table->decimal('kg_entrada', 10, 3);
            $table->decimal('costo_usd', 10, 2);
            $table->decimal('kg_surtido_vitrina', 10, 3)->default(0);
            $table->decimal('kg_disponible', 10, 3)->virtualAs('kg_entrada - kg_surtido_vitrina');
            $table->string('supplier', 100)->nullable();
            $table->dateTime('entered_at');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boveda_entries');
    }
};
