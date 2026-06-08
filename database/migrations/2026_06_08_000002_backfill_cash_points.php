<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Por cada (business_id, branch_id) con sesiones reales (opened_at != null),
        // crear UNA caja física "Caja 1" y enlazar esas sesiones.
        // Las filas config sin opened_at (cajas viejas no usadas) quedan intactas.
        $groups = DB::table('cash_registers')
            ->whereNotNull('opened_at')
            ->whereNotNull('branch_id')
            ->whereNull('cash_point_id')
            ->select('business_id', 'branch_id')
            ->distinct()
            ->get();

        foreach ($groups as $g) {
            $cashPointId = DB::table('cash_points')->insertGetId([
                'business_id' => $g->business_id,
                'branch_id'   => $g->branch_id,
                'name'        => 'Caja 1',
                'is_active'   => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            DB::table('cash_registers')
                ->where('business_id', $g->business_id)
                ->where('branch_id', $g->branch_id)
                ->whereNotNull('opened_at')
                ->whereNull('cash_point_id')
                ->update(['cash_point_id' => $cashPointId]);
        }
    }

    public function down(): void
    {
        // cash_points solo existe por esta feature: desenlazar y vaciar.
        DB::table('cash_registers')->update(['cash_point_id' => null]);
        DB::table('cash_points')->delete();
    }
};
