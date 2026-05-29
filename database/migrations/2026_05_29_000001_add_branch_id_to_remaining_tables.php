<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'orders',
            'despiece_logs',
            'fabrica_batches',
            'clients',
            'payment_terminals',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('branch_id')
                    ->nullable()
                    ->after('business_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'orders',
            'despiece_logs',
            'fabrica_batches',
            'clients',
            'payment_terminals',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(["{$table}_branch_id_foreign"]);
                $blueprint->dropColumn('branch_id');
            });
        }
    }
};
