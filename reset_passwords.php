<?php

declare(strict_types=1);

/**
 * reset_passwords.php — Resetea contraseñas de usuarios del negocio.
 *
 * Uso: php reset_passwords.php
 *
 * Afecta SOLO usuarios con business_id=1 e is_hidden=0.
 * No modifica session_token.
 */

define('LARAVEL_START', microtime(true));

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

// ─── Confirmación ─────────────────────────────────────────────────────────────

$usuarios = User::where('business_id', 1)
    ->where('is_hidden', 0)
    ->orderBy('id')
    ->get(['id', 'name', 'email']);

if ($usuarios->isEmpty()) {
    echo "No se encontraron usuarios con business_id=1 e is_hidden=0.\n";
    exit(0);
}

echo "\nUsuarios que serán afectados:\n";
echo str_repeat('─', 60) . "\n";
foreach ($usuarios as $u) {
    echo "  [{$u->id}] {$u->name} — {$u->email}\n";
}
echo str_repeat('─', 60) . "\n";
echo "Total: " . $usuarios->count() . " usuario(s)\n\n";

echo "¿Resetear passwords? (s/n): ";
$confirm = trim(fgets(STDIN));

if ($confirm !== 's') {
    echo "Operación cancelada. Sin cambios.\n";
    exit(0);
}

// ─── Reset ────────────────────────────────────────────────────────────────────

$newPass = Hash::make('Chaguaramas2026!');

DB::table('users')
    ->where('business_id', 1)
    ->where('is_hidden', 0)
    ->update(['password' => $newPass, 'remember_token' => null]);

// ─── Resultado ────────────────────────────────────────────────────────────────

echo "\nPasswords reseteados correctamente:\n";
echo str_repeat('─', 60) . "\n";
foreach ($usuarios as $u) {
    echo "  [{$u->id}] {$u->name} — {$u->email}\n";
}
echo str_repeat('─', 60) . "\n";
echo "✓ " . $usuarios->count() . " usuario(s) actualizados.\n";
echo "  Nueva contraseña: Chaguaramas2026!\n";
echo "  session_token:    sin modificar\n\n";
