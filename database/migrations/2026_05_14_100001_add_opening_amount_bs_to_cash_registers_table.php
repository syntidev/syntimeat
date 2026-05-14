<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            // Guardar el monto de apertura en Bs. para no depender de reconversiones
            $table->decimal('opening_amount_bs', 12, 2)->default(0)->after('opening_amount_usd');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('opening_amount_bs');
        });
    }
};
