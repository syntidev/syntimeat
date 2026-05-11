<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name');
            $table->string('rif');
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('phone')->nullable();
            $table->enum('currency_default', ['USD', 'VES', 'mixed'])->default('USD');
            $table->enum('rate_source', ['bcv', 'parallel', 'negotiated', 'manual'])->default('bcv');
            $table->decimal('rate_margin', 5, 2)->default(0);
            $table->string('weight_unit', 5)->default('kg');
            $table->string('ticket_prefix', 10)->default('VEN');
            $table->text('ticket_footer')->nullable();
            $table->enum('sale_capture_mode', ['classic', 'preticket'])->default('classic');
            $table->enum('line_input_mode', ['weight', 'unit', 'mixed'])->default('weight');
            $table->boolean('preticket_enabled')->default(false);
            $table->integer('preticket_expiry_minutes')->default(30);
            $table->enum('price_lock_policy', ['at_capture', 'at_payment'])->default('at_capture');
            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
