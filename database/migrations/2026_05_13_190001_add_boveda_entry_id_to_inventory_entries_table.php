<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_entries', function (Blueprint $table): void {
            $table->foreignId('boveda_entry_id')
                ->nullable()
                ->after('location')
                ->constrained('boveda_entries')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_entries', function (Blueprint $table): void {
            $table->dropForeign(['boveda_entry_id']);
            $table->dropColumn('boveda_entry_id');
        });
    }
};
