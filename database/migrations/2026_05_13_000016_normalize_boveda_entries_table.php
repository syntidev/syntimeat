<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Añadir product_type (nullable mientras se migran los datos)
        Schema::table('boveda_entries', function (Blueprint $table): void {
            $table->string('product_type', 80)->nullable()->after('business_id');
        });

        // 2. Poblar product_type desde los valores del enum anterior
        DB::statement("
            UPDATE boveda_entries
            SET product_type = CASE boveda_product
                WHEN 'medio_canal_res' THEN 'Medio Canal Res'
                WHEN 'canal_cerdo'     THEN 'Canal Cerdo'
                WHEN 'pollo_entero'    THEN 'Pollo Entero Congelado'
                WHEN 'jamon_pierna'    THEN 'Jamón Pierna Sellado'
                ELSE 'Otros'
            END
        ");

        // 3. Eliminar el enum anterior
        Schema::table('boveda_entries', function (Blueprint $table): void {
            $table->dropColumn('boveda_product');
        });
    }

    public function down(): void
    {
        Schema::table('boveda_entries', function (Blueprint $table): void {
            $table->enum('boveda_product', ['medio_canal_res', 'canal_cerdo', 'pollo_entero', 'jamon_pierna', 'otros'])
                ->nullable()
                ->after('business_id');
        });

        Schema::table('boveda_entries', function (Blueprint $table): void {
            $table->dropColumn('product_type');
        });
    }
};
