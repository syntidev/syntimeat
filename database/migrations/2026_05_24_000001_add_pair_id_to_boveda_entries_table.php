<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boveda_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('pair_id')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('boveda_entries', function (Blueprint $table): void {
            $table->dropColumn('pair_id');
        });
    }
};
