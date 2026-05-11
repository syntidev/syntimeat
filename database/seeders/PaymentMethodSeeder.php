<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Business;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::first();

        if (! $business) {
            $this->command->warn('PaymentMethodSeeder: no hay negocio registrado. Ejecuta el onboarding primero.');
            return;
        }

        $methods = [
            ['name' => 'Efectivo',                'type' => 'cash',       'bank_name' => null,       'sort_order' => 1],
            ['name' => 'Pago Móvil Banesco',      'type' => 'mobile',     'bank_name' => 'Banesco',  'sort_order' => 2],
            ['name' => 'Pago Móvil Venezuela',    'type' => 'mobile',     'bank_name' => 'Venezuela','sort_order' => 3],
            ['name' => 'Transferencia',            'type' => 'transfer',   'bank_name' => null,       'sort_order' => 4],
            ['name' => 'BioPago Mercantil',       'type' => 'biometric',  'bank_name' => 'Mercantil','sort_order' => 5],
            ['name' => 'Zelle',                   'type' => 'other',      'bank_name' => null,       'sort_order' => 6],
            ['name' => 'Dólares en Efectivo',     'type' => 'cash',       'bank_name' => null,       'sort_order' => 7],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['business_id' => $business->id, 'name' => $method['name']],
                [...$method, 'business_id' => $business->id, 'is_active' => true],
            );
        }

        $count = PaymentMethod::where('business_id', $business->id)->count();
        $this->command->info("PaymentMethodSeeder: {$count} métodos de pago creados.");
    }
}
