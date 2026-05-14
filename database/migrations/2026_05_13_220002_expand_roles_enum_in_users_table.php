<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Expandir ENUM con los nuevos roles del esquema multi-sucursal
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'super_admin',
            'owner',
            'branch_admin',
            'analyst',
            'supervisor',
            'cashier',
            'admin'
        ) NOT NULL DEFAULT 'cashier'");
    }

    public function down(): void
    {
        // Revertir a roles anteriores (los nuevos roles caen a cashier)
        DB::statement("UPDATE users SET role = 'cashier' WHERE role IN ('owner','branch_admin','analyst')");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM(
            'super_admin','admin','cashier'
        ) NOT NULL DEFAULT 'cashier'");
    }
};
