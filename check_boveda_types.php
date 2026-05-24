<?php
/**
 * check_boveda_types.php — Script de diagnóstico de solo lectura
 * Propósito: listar product_type únicos en boveda_entries pendientes de despiece
 * Uso: php check_boveda_types.php (desde la raíz del proyecto)
 * BORRAR después de usar.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$catMap = [
    'Medio Canal Res'        => 'Res',
    'Canal Cerdo'            => 'Cerdo',
    'Pollo Entero Congelado' => 'Pollo',
];

echo "\n=== BOVEDA_ENTRIES: kg_surtido_vitrina > 0 AND despiece_completado_at IS NULL ===\n\n";

$rows = DB::table('boveda_entries')
    ->where('kg_surtido_vitrina', '>', 0)
    ->whereNull('despiece_completado_at')
    ->selectRaw('product_type, COUNT(*) as n, ROUND(SUM(kg_surtido_vitrina),3) as kg_total')
    ->groupBy('product_type')
    ->orderBy('product_type')
    ->get();

if ($rows->isEmpty()) {
    echo "  (sin filas que cumplan la condición)\n";
} else {
    printf("  %-35s %6s %12s %s\n", 'product_type', 'filas', 'kg_total', 'catName');
    printf("  %-35s %6s %12s %s\n", str_repeat('-',35), '------', '------------', '-------');
    foreach ($rows as $r) {
        $catName = $catMap[$r->product_type] ?? '*** NULL ***';
        printf("  %-35s %6d %12.3f %s\n", $r->product_type, $r->n, $r->kg_total, $catName);
    }
}

echo "\n=== TIPOS NO CUBIERTOS POR catMap ===\n\n";
$fuera = $rows->filter(fn($r) => !array_key_exists($r->product_type, $catMap));
if ($fuera->isEmpty()) {
    echo "  Ninguno — todos los product_type están en el catMap.\n";
} else {
    foreach ($fuera as $r) {
        echo "  >> '{$r->product_type}' ({$r->n} fila/s, {$r->kg_total} kg) → catName = null → productos_vitrina = []\n";
    }
}

echo "\n=== QUÉ VE EL OPERADOR cuando catName = null ===\n";
echo "  productos_vitrina devuelve collect() vacío.\n";
echo "  El panel de despiece se renderiza SIN productos para asignar cortes.\n";
echo "  El operador no recibe error: simplemente no puede registrar ningún corte.\n";
echo "\n";
