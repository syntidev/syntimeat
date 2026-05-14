<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Límite de sucursales visibles en el Panel Empresarial.
        // Default 2 para el plan piloto — soporte lo amplía sin desplegar código.
        Schema::table('businesses', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_branches')->default(2)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('max_branches');
        });
    }
};
