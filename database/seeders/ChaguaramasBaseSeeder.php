<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ChaguaramasBaseSeeder extends Seeder
{
    public function run(): void
    {
        $business = DB::table('businesses')->where('rif', 'J-12345678-9')->first();

        if (! $business) {
            $businessId = DB::table('businesses')->insertGetId([
                'name'                 => 'Carnicería Chaguaramas',
                'legal_name'           => 'Carnicería Chaguaramas C.A.',
                'rif'                  => 'J-12345678-9',
                'ticket_prefix'        => 'VEN',
                'currency_default'     => 'USD',
                'rate_source'          => 'bcv',
                'weight_unit'          => 'kg',
                'onboarding_completed' => true,
                'active'               => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } else {
            $businessId = $business->id;
        }

        $branch = DB::table('branches')
            ->where('business_id', $businessId)
            ->where('name', 'Chaguaramas')
            ->first();

        if (! $branch) {
            $branchId = DB::table('branches')->insertGetId([
                'business_id' => $businessId,
                'name'        => 'Chaguaramas',
                'address'     => 'Valle de la Pascua, Guárico',
                'phone'       => '+58 4222654827',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        } else {
            $branchId = $branch->id;
        }

        $user = User::where('email', 'carbolivar@gmail.com')->first();

        if (! $user) {
            User::create([
                'name'        => 'Carlos Bolívar',
                'email'       => 'carbolivar@gmail.com',
                'password'    => Hash::make('Admin001'),
                'role'        => 'super_admin',
                'business_id' => $businessId,
                'branch_id'   => $branchId,
                'is_active'   => true,
                'theme'       => 'dark',
            ]);
        }
    }
}
