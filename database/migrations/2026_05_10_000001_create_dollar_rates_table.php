<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dollar_rates', function (Blueprint $table): void {
            $table->id();
            $table->decimal('rate', 12, 4);
            $table->string('source', 50)->default('bcv');        // bcv | parallel | negotiated | manual
            $table->string('currency_type', 10)->default('USD'); // USD | EUR
            $table->datetime('effective_from');
            $table->datetime('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();         // sin updated_at (UPDATED_AT = null)

            $table->index(['currency_type', 'source', 'is_active', 'effective_from'], 'idx_rates_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dollar_rates');
    }
};
