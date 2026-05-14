<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalizar valores existentes antes de cambiar a ENUM
        DB::statement("UPDATE users SET role = 'admin'   WHERE role = 'admin'");
        DB::statement("UPDATE users SET role = 'cashier' WHERE role NOT IN ('super_admin', 'admin')");

        // Cambiar columna a ENUM
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin','admin','cashier') NOT NULL DEFAULT 'cashier'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'cashier'");
    }
};
