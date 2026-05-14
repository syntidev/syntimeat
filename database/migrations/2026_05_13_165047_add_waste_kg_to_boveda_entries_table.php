<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boveda_entries', function (Blueprint $table) {
            $table->decimal('waste_kg', 8, 3)->default(0)->after('kg_surtido_vitrina');
        });
    }

    public function down(): void
    {
        Schema::table('boveda_entries', function (Blueprint $table) {
            $table->dropColumn('waste_kg');
        });
    }
};