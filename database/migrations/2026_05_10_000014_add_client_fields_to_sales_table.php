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
            $table->string('client_name', 100)->nullable()->after('notes');
            $table->string('client_phone', 30)->nullable()->after('client_name');
            $table->unsignedBigInteger('client_id')->nullable()->after('client_phone');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['client_name', 'client_phone', 'client_id']);
        });
    }
};
