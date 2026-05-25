<?php

declare(strict_types=1);

define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';

$app    = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$business   = Business::firstOrFail();
$businessId = $business->id;

echo "Business: {$business->name} (ID={$businessId})\n\n";

$users = [
    [
        'name'     => 'Dueño Chaguaramas',
        'email'    => 'dueno@chaguaramas.com',
        'role'     => 'owner',
        'password' => Hash::make('Chaguaramas2026!'),
    ],
    [
        'name'     => 'Contable',
        'email'    => 'contable1@chaguaramas.com',
        'role'     => 'analyst',
        'password' => Hash::make('Chaguaramas2026!'),
    ],
    [
        'name'     => 'Cajera 1',
        'email'    => 'cajera1@chaguaramas.com',
        'role'     => 'cashier',
        'password' => Hash::make('Chaguaramas2026!'),
    ],
    [
        'name'     => 'Cajera 2',
        'email'    => 'cajera2@chaguaramas.com',
        'role'     => 'cashier',
        'password' => Hash::make('Chaguaramas2026!'),
    ],
];

foreach ($users as $data) {
    $user = User::updateOrCreate(
        ['email' => $data['email']],
        [
            'name'              => $data['name'],
            'password'          => $data['password'],
            'role'              => $data['role'],
            'business_id'       => $businessId,
            'is_active'         => true,
            'email_verified_at' => now(),
            'session_token'     => null,
            'branch_id'         => null,
        ]
    );

    $action = $user->wasRecentlyCreated ? 'CREADO   ' : 'ACTUALIZADO';
    echo "  [{$action}] {$user->name} <{$user->email}> role={$user->role} ID={$user->id}\n";
}

echo "\nListo.\n";
