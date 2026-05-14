<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->enum('origin', ['onsite', 'delivery'])->default('onsite')->after('notes');
            $table->enum('channel', ['physical', 'online'])->default('physical')->after('origin');
            $table->enum('delivery_status', ['pending', 'dispatched', 'collected'])->nullable()->after('channel');
            $table->dateTime('delivery_confirmed_at')->nullable()->after('delivery_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'channel', 'delivery_status', 'delivery_confirmed_at']);
        });
    }
};
