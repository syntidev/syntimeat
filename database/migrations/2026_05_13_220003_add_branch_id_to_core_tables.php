<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Usuarios asignados a una sucursal (null = acceso a todas, owner/super_admin)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')
                  ->constrained('branches')->nullOnDelete();
        });

        // Ventas por sucursal
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')
                  ->constrained('branches')->nullOnDelete();
        });

        // Entradas de inventario por sucursal
        Schema::table('inventory_entries', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')
                  ->constrained('branches')->nullOnDelete();
        });

        // Cajas registradoras por sucursal
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('business_id')
                  ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers',    fn ($t) => $t->dropForeignIdFor(\App\Models\Branch::class));
        Schema::table('inventory_entries', fn ($t) => $t->dropForeignIdFor(\App\Models\Branch::class));
        Schema::table('sales',             fn ($t) => $t->dropForeignIdFor(\App\Models\Branch::class));
        Schema::table('users',             fn ($t) => $t->dropForeignIdFor(\App\Models\Branch::class));
    }
};
