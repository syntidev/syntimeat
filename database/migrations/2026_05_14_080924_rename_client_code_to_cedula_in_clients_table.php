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
        Schema::table('clients', function (Blueprint $table): void {
            $table->renameColumn('client_code', 'cedula');
        });

        // Limpia valores auto-generados CLI-XXXX que ya no tienen sentido
        DB::table('clients')
            ->where('cedula', 'like', 'CLI-%')
            ->update(['cedula' => null]);

        Schema::table('clients', function (Blueprint $table): void {
            $table->string('cedula', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->renameColumn('cedula', 'client_code');
        });
    }
};
