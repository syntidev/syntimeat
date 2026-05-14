<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            // Persiste el monto Bs por línea al momento de la venta.
            // Necesario para reportes cuando la tasa del día ya no está disponible.
            $table->decimal('subtotal_bs', 12, 2)->default(0)->after('subtotal_usd');
            $table->decimal('rate_used', 10, 4)->nullable()->after('subtotal_bs');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn(['subtotal_bs', 'rate_used']);
        });
    }
};
