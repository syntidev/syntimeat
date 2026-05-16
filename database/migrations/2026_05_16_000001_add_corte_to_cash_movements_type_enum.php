<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cash_movements MODIFY COLUMN type ENUM('in', 'out', 'corte') NOT NULL");
    }

    public function down(): void
    {
        // Eliminar filas corte antes de revertir el ENUM
        DB::statement("DELETE FROM cash_movements WHERE type = 'corte'");
        DB::statement("ALTER TABLE cash_movements MODIFY COLUMN type ENUM('in', 'out') NOT NULL");
    }
};
