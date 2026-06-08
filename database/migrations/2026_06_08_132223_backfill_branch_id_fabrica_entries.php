<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill branch_id en inventory_entries de despiece/fábrica históricos.
     * FabricaController creaba los entries de despiece (con boveda_entry_id) sin branch_id;
     * ya corregido. El branch correcto es el del BovedaEntry padre (la carne pertenece a un branch).
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE inventory_entries ie
            JOIN boveda_entries be ON ie.boveda_entry_id = be.id
            SET ie.branch_id = be.branch_id
            WHERE ie.branch_id IS NULL AND ie.boveda_entry_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        // Backfill de datos — no se revierte.
    }
};
