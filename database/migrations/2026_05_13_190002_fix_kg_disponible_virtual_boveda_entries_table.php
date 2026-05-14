<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // waste_kg fue añadido después de crear la columna virtual.
        // La fórmula correcta descuenta también la merma registrada en bóveda.
        DB::statement('
            ALTER TABLE boveda_entries
            MODIFY COLUMN kg_disponible DECIMAL(10,3)
            GENERATED ALWAYS AS (kg_entrada - kg_surtido_vitrina - waste_kg) VIRTUAL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE boveda_entries
            MODIFY COLUMN kg_disponible DECIMAL(10,3)
            GENERATED ALWAYS AS (kg_entrada - kg_surtido_vitrina) VIRTUAL
        ');
    }
};
