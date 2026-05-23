<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: columnas NOT NULL sin default en la tabla businesses.
 *
 * PROBLEMA: rif y legal_name fueron creadas sin ->nullable() y sin default.
 * updateGeneral() valida ambas como ['nullable', ...], por lo que un formulario
 * que las envía vacías genera un 500 "Column cannot be null" en MySQL.
 *
 * FIX: hacer ambas nullable. Los registros existentes que ya tienen valor
 * no se ven afectados — solo cambia el constraint de la columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('legal_name', 255)->nullable()->change();
            $table->string('rif', 20)->nullable()->change();
            $table->string('theme_color', 20)->nullable()->change();
            $table->string('phone', 30)->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('state', 100)->nullable()->change();
            $table->string('address', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('legal_name', 255)->nullable(false)->change();
            $table->string('rif', 20)->nullable(false)->change();
            $table->string('theme_color', 20)->nullable(false)->change();
            $table->string('phone', 30)->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->string('state', 100)->nullable(false)->change();
            $table->string('address', 500)->nullable(false)->change();
        });
    }
};
