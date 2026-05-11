<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('ticket_number', 20);
            $table->enum('status', ['open', 'pending', 'paid', 'cancelled'])->default('open');
            $table->decimal('total_usd', 10, 2)->default(0);
            $table->string('payment_method', 30)->nullable();
            $table->decimal('amount_received_usd', 10, 2)->nullable();
            $table->decimal('change_usd', 10, 2)->nullable();
            $table->decimal('rate_used', 10, 4)->nullable();
            $table->decimal('total_bs', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('sold_at')->nullable();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cash_register_id')->nullable()->constrained('cash_registers')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
