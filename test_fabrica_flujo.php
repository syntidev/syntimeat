<?php

declare(strict_types=1);

/**
 * TEST: Flujo Fábrica — catMap + productos_vitrina
 *
 * Valida que los product_type de bóveda mapeen correctamente a categoría vitrina
 * y que los productos devueltos por la lógica de FabricaController::index()
 * sean los correctos para cada tipo de canal.
 *
 * Uso: php test_fabrica_flujo.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BovedaEntry;
use App\Models\BovedaProduct;
use App\Models\InventoryEntry;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

// ── Auth ──────────────────────────────────────────────────────────────────────
$user = \App\Models\User::find(1);
if (! $user) {
    echo "FATAL: Usuario ID=1 no existe.\n";
    exit(1);
}
Auth::login($user);
$businessId = $user->business_id;

// ── Constantes del test ───────────────────────────────────────────────────────
$tiposCanal = [
    'RES - Medio Canal',
    'POLLO - Entero Congelado',
    'CERDO - Canal',
];

// catMap EXACTO de FabricaController::index() y BovedaController::plantillaDespiece()
$catMap = [
    'RES - Medio Canal'        => 'Res',
    'CERDO - Canal'            => 'Cerdo',
    'POLLO - Entero Congelado' => 'Pollo',
];

// resOrder EXACTO post-actualización (FabricaController::index() y BovedaController::plantillaDespiece())
$resOrderEsperado = ['Carne del Canal', 'Costilla', 'Hueso Redondo', 'Hueso Rojo'];

// ── Tracking para cleanup ─────────────────────────────────────────────────────
$createdEntryIds      = [];
$createdBovedaProdIds = [];
$results              = [];

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║    TEST: FLUJO FÁBRICA — catMap + productos_vitrina          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
printf("  Business: #%d | Usuario: %s\n\n", $businessId, $user->name);

foreach ($tiposCanal as $tipo) {
    echo "──────────────────────────────────────────────────────────────\n";
    echo "▶ TIPO: {$tipo}\n";

    // a. Garantizar BovedaProduct para que el whereHas de FabricaController funcione
    $bp = BovedaProduct::where('business_id', $businessId)
        ->where('name', $tipo)
        ->first();

    if (! $bp) {
        $bp = BovedaProduct::create([
            'business_id'       => $businessId,
            'name'              => $tipo,
            'unit'              => 'kg',
            'active'            => true,
            'requires_despiece' => true,
        ]);
        $createdBovedaProdIds[] = $bp->id;
        echo "  [SETUP] BovedaProduct temporal creado: #{$bp->id}\n";
    } else {
        echo "  [SETUP] BovedaProduct existente: #{$bp->id} (requires_despiece={$bp->requires_despiece})\n";
    }

    // b. Crear boveda_entry
    $entry = BovedaEntry::create([
        'business_id'  => $businessId,
        'product_type' => $tipo,
        'description'  => '[TEST] test_fabrica_flujo.php',
        'kg_entrada'   => 100.000,
        'costo_usd'    => 50.00,
        'supplier'     => 'TEST_SCRIPT',
        'entered_at'   => now(),
    ]);
    $createdEntryIds[] = $entry->id;
    echo "  [a] BovedaEntry creada: #{$entry->id} — {$tipo} 100.000 kg\n";

    // c. Surtir 80 kg — lógica de BovedaController::surte() sin capa HTTP
    //    (CSRF y guards no aplican en script; la lógica de negocio es la misma)
    $disponible = round(
        (float) $entry->kg_entrada - (float) $entry->kg_surtido_vitrina - (float) $entry->waste_kg,
        3
    );

    if (80.0 > $disponible) {
        echo "  [b] FAIL: 80 kg supera disponible ({$disponible} kg)\n";
        $results[$tipo] = 'FAIL (surte — stock insuficiente)';
        continue;
    }

    $entry->increment('kg_surtido_vitrina', 80);
    $entry->refresh();
    echo "  [b] Surtido OK — kg_surtido_vitrina: {$entry->kg_surtido_vitrina}\n";

    // d. Extraer productos_vitrina — LÓGICA EXACTA de FabricaController::index()
    //    (incluyendo el whereIn añadido por el linter para Res)
    $catName  = $catMap[$entry->product_type] ?? null;
    $resOrder = ['Carne del Canal', 'Costilla', 'Hueso Redondo', 'Hueso Rojo'];

    $productosCollection = $catName
        ? Product::with('category')
            ->where('business_id', $businessId)
            ->where('location', 'vitrina')
            ->where('active', true)
            ->where('fabricable', false)
            ->whereHas('category', fn ($q) => $q->where('name', $catName))
            ->when($catName === 'Res', fn ($q) => $q->whereIn('name', $resOrder))
            ->get(['id', 'name'])
            ->sortBy(fn ($p) => $catName === 'Res'
                ? (array_search($p->name, $resOrder) !== false ? array_search($p->name, $resOrder) : 999)
                : $p->name
            )
            ->values()
        : collect();

    $productosNombres = $productosCollection->pluck('name')->toArray();

    echo "  [c] catName mapeado: " . ($catName ?? 'NULL (¡ERROR!)') . "\n";
    echo "  [c] productos_vitrina (" . count($productosNombres) . "): [" . implode(', ', $productosNombres) . "]\n";

    // e. Validar
    $pass   = true;
    $detail = [];

    // Validación 0: catMap debe reconocer el tipo
    if ($catName === null) {
        $pass     = false;
        $detail[] = "catMap NO reconoce '{$tipo}' — devuelve NULL";
    }

    if ($tipo === 'RES - Medio Canal') {
        // RES: debe tener EXACTAMENTE los 4 cortes del $resOrderEsperado, ni más ni menos
        $faltantes = array_values(array_diff($resOrderEsperado, $productosNombres));
        $extras    = array_values(array_diff($productosNombres, $resOrderEsperado));

        if (! empty($faltantes)) {
            $pass     = false;
            $detail[] = "Faltan en DB (crear en vitrina cat=Res): [" . implode(', ', $faltantes) . "]";
        }
        if (! empty($extras)) {
            $pass     = false;
            $detail[] = "Extras inesperados (no deberían aparecer): [" . implode(', ', $extras) . "]";
        }
        if (empty($faltantes) && empty($extras)) {
            $detail[] = "Lista exacta correcta ✓";
        }

        // Verificar orden
        if ($productosNombres === array_values(array_intersect($resOrder, $productosNombres))) {
            $detail[] = "Orden correcto ✓";
        } else {
            $detail[] = "Orden incorrecto (esperado: " . implode(' → ', $resOrder) . ")";
        }

    } else {
        // POLLO / CERDO: todos los productos deben pertenecer a su categoría
        $catEsperada = $catMap[$tipo];

        if ($catName !== $catEsperada) {
            $pass     = false;
            $detail[] = "catMap devolvió '{$catName}', esperado '{$catEsperada}'";
        }

        if (empty($productosNombres)) {
            $detail[] = "Sin productos en DB para categoría '{$catEsperada}' (catálogo vacío — no es error de código)";
        } else {
            // Verificar que ningún producto sea de categoría incorrecta
            $productosConCat = Product::with('category')
                ->where('business_id', $businessId)
                ->whereIn('name', $productosNombres)
                ->get();

            $catIncorrecta = $productosConCat
                ->filter(fn ($p) => ($p->category?->name ?? '') !== $catEsperada)
                ->map(fn ($p) => $p->name . ' (cat=' . ($p->category?->name ?? 'NULL') . ')')
                ->toArray();

            if (! empty($catIncorrecta)) {
                $pass     = false;
                $detail[] = "Productos con categoría incorrecta: [" . implode(', ', $catIncorrecta) . "]";
            } else {
                $detail[] = "Todos los productos son de categoría '{$catEsperada}' ✓";
            }
        }
    }

    $icon            = $pass ? '✓ PASS' : '✗ FAIL';
    $results[$tipo]  = $pass ? 'PASS' : 'FAIL';

    echo "  [e] {$icon}\n";
    foreach ($detail as $d) {
        echo "      → {$d}\n";
    }
}

// ── Cleanup ───────────────────────────────────────────────────────────────────
echo "\n──────────────────────────────────────────────────────────────\n";
echo "CLEANUP\n";

if (! empty($createdEntryIds)) {
    InventoryEntry::whereIn('boveda_entry_id', $createdEntryIds)->delete();
    $deleted = BovedaEntry::whereIn('id', $createdEntryIds)->delete();
    echo "  BovedaEntries eliminadas: " . $deleted . " — IDs [" . implode(', ', $createdEntryIds) . "]\n";
}

if (! empty($createdBovedaProdIds)) {
    $deleted = BovedaProduct::whereIn('id', $createdBovedaProdIds)->delete();
    echo "  BovedaProducts temporales eliminados: " . $deleted . " — IDs [" . implode(', ', $createdBovedaProdIds) . "]\n";
}

// ── Resumen final ─────────────────────────────────────────────────────────────
$allPass = ! in_array('FAIL', $results, true)
        && ! in_array('FAIL (surte — stock insuficiente)', $results, true);

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║                        RESUMEN                              ║\n";
echo "╠══════════════════════════════════════════════════════════════╣\n";

foreach ($results as $tipo => $res) {
    $icon = ($res === 'PASS') ? '✓' : '✗';
    printf("║  %s  %-56s║\n", $icon, substr($tipo . ' → ' . $res, 0, 56));
}

echo "╠══════════════════════════════════════════════════════════════╣\n";
$final = $allPass
    ? '✓  TODOS LOS CHECKS PASARON'
    : '✗  HAY FAILURES — ver detalle arriba';
printf("║  %-60s║\n", $final);
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

exit($allPass ? 0 : 1);
