<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Crédito temporal: 'paid' = cobrado, 'pendiente_cobro' = despachado sin pago
            $table->enum('payment_status', ['paid', 'pendiente_cobro'])
                  ->default('paid')
                  ->after('status');

            // Vínculo trazable con el pedido que originó esta venta
            $table->foreignId('order_id')
                  ->nullable()
                  ->after('cash_register_id')
                  ->constrained('orders')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropColumn(['payment_status', 'order_id']);
        });
    }
};
