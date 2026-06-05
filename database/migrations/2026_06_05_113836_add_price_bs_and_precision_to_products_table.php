<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price_per_kg_bs', 12, 2)->nullable()->after('price_per_kg_usd');
            $table->decimal('price_per_unit_bs', 12, 2)->nullable()->after('price_per_unit_usd');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_per_kg_bs', 'price_per_unit_bs']);
        });
    }
};
