<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('branch_id');
            $table->string('session_token', 64)->nullable()->after('is_active');
            $table->time('access_start')->nullable()->after('session_token');
            $table->time('access_end')->nullable()->after('access_start');
        });

        // email ya existe como NOT NULL — hacerlo nullable para cajeros sin correo
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'session_token', 'access_start', 'access_end']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
