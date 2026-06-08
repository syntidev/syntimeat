<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cajas físicas permanentes por sucursal (Caja 1, Caja 2, ...)
        Schema::create('cash_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Cada sesión (cash_registers) pertenece a una caja física
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->foreignId('cash_point_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('cash_points')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cash_point_id');
        });

        Schema::dropIfExists('cash_points');
    }
};
