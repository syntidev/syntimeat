<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill branch_id en descuentos de venta históricos.
     * pay() creaba el InventoryEntry sin branch_id (NULL); ya corregido.
     * Reconstruimos el branch desde la venta vía la nota "Venta {ticket}".
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE inventory_entries ie
            JOIN sales s ON ie.notes = CONCAT('Venta ', s.ticket_number)
            SET ie.branch_id = s.branch_id
            WHERE ie.branch_id IS NULL AND ie.quantity_kg < 0
        SQL);
    }

    public function down(): void
    {
        // Backfill de datos — no se revierte.
    }
};
