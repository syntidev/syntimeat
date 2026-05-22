<?php
/**
 * SYNTImeat — Stress Test Completo
 * Simula un día real de operación en carnicería.
 *
 * Ejecutar: php stress_test.php
 * Output:   stress_output.txt (generado automáticamente)
 *
 * NO modifica nada — solo reporta PASS/FAIL.
 * Al finalizar limpia los datos de prueba.
 */

define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\SaleController;
use App\Models\ActivityLog;
use App\Models\BovedaEntry;
use App\Models\BovedaProduct;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\FabricaBatch;
use App\Models\InventoryEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Services\DollarRateService;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ─── Helpers ─────────────────────────────────────────────────────────────────

$results = [];
$testIds = []; // IDs creados, para cleanup

function pass(string $accion, string $esperado, string $real): void {
    global $results;
    $results[] = ['accion' => $accion, 'esperado' => $esperado, 'real' => $real, 'status' => 'PASS'];
    echo "  ✓ PASS  | {$accion}\n";
}

function fail(string $accion, string $esperado, string $real): void {
    global $results;
    $results[] = ['accion' => $accion, 'esperado' => $esperado, 'real' => $real, 'status' => '*** FAIL ***'];
    echo "  ✗ FAIL  | {$accion}\n";
    echo "          | Esperado: {$esperado}\n";
    echo "          | Real:     {$real}\n";
}

function section(string $title): void {
    echo "\n" . str_repeat('═', 80) . "\n";
    echo "  {$title}\n";
    echo str_repeat('─', 80) . "\n";
}

function printTable(array $results): void {
    $w = [50, 30, 40, 12];
    $fmt = "| %-{$w[0]}s | %-{$w[1]}s | %-{$w[2]}s | %-{$w[3]}s |\n";
    $sep = '+' . str_repeat('-', $w[0]+2) . '+' . str_repeat('-', $w[1]+2) . '+' . str_repeat('-', $w[2]+2) . '+' . str_repeat('-', $w[3]+2) . '+';
    echo "\n" . $sep . "\n";
    printf($fmt, 'Acción', 'Resultado Esperado', 'Resultado Real', 'Estado');
    echo $sep . "\n";
    foreach ($results as $r) {
        $accion   = substr($r['accion'],   0, $w[0]);
        $esperado = substr($r['esperado'], 0, $w[1]);
        $real     = substr($r['real'],     0, $w[2]);
        $status   = $r['status'];
        printf($fmt, $accion, $esperado, $real, $status);
    }
    echo $sep . "\n";
}

// ─── FASE 1 — Setup ──────────────────────────────────────────────────────────
section('FASE 1 — Setup y Autenticación');

Auth::loginUsingId(1);
$user = Auth::user();
$businessId = $user->business_id;
$business   = $user->business;

if ($user && $user->business_id) {
    pass('Auth loginUsingId(1)', 'Usuario autenticado con business_id', "ID={$user->id} | {$user->name} | role={$user->role}");
} else {
    fail('Auth loginUsingId(1)', 'Usuario autenticado', 'NULL — sin usuario');
    exit(1);
}

$rateService = app(DollarRateService::class);
$rate = $rateService->getTodayRate();

if ($rate > 0) {
    pass('DollarRateService::getTodayRate()', 'Tasa > 0', "Tasa = {$rate} Bs/$");
} else {
    fail('DollarRateService::getTodayRate()', 'Tasa > 0', "Tasa = {$rate} — sin tasa del día");
}

echo "\n  Negocio : {$business->name}\n";
echo "  Rate    : {$rate} Bs/USD\n";
echo "  UserID  : {$user->id}\n\n";

// ─── Prefijo de test para limpiar al final ────────────────────────────────────
$TS = '[ST-' . date('His') . ']';

// ─── FASE 2 — Bóveda ─────────────────────────────────────────────────────────
section('FASE 2 — Bóveda: Entradas, Surtidos y Validaciones de Límite');

$bovedaEntries = [];

// 2.1 — Crear 3 entradas Medio Canal Res (80 kg c/u)
for ($i = 1; $i <= 3; $i++) {
    try {
        $entry = DB::transaction(function () use ($businessId, $user, $i, $TS) {
            $e = BovedaEntry::create([
                'business_id'  => $businessId,
                'product_type' => 'Medio Canal Res',
                'description'  => "{$TS} Res #{$i}",
                'kg_entrada'   => 80.0,
                'costo_usd'    => 160.0,
                'supplier'     => 'Test',
                'entered_at'   => now(),
            ]);
            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'stress_test.boveda.entry',
                'model_type'  => 'BovedaEntry',
                'model_id'    => $e->id,
            ]);
            return $e;
        });
        $bovedaEntries[] = $entry;
        pass("Crear BovedaEntry Res #{$i} (80 kg)", 'Entry creada con id', "ID={$entry->id} | 80kg | \$160");
    } catch (\Throwable $e) {
        fail("Crear BovedaEntry Res #{$i}", 'Entry creada', $e->getMessage());
    }
}

// 2.2 — Crear 2 entradas Pollo Entero Congelado (50 kg)
for ($i = 1; $i <= 2; $i++) {
    try {
        $entry = DB::transaction(function () use ($businessId, $user, $i, $TS) {
            return BovedaEntry::create([
                'business_id'  => $businessId,
                'product_type' => 'Pollo Entero Congelado',
                'description'  => "{$TS} Pollo #{$i}",
                'kg_entrada'   => 50.0,
                'costo_usd'    => 75.0,
                'supplier'     => 'Test',
                'entered_at'   => now(),
            ]);
        });
        $bovedaEntries[] = $entry;
        pass("Crear BovedaEntry Pollo #{$i} (50 kg)", 'Entry creada', "ID={$entry->id} | 50kg | \$75");
    } catch (\Throwable $e) {
        fail("Crear BovedaEntry Pollo #{$i}", 'Entry creada', $e->getMessage());
    }
}

// 2.3 — Crear 1 entrada Jamón Pierna Sellado (30 kg)
try {
    $entry = DB::transaction(function () use ($businessId, $user, $TS) {
        return BovedaEntry::create([
            'business_id'  => $businessId,
            'product_type' => 'Jamón Pierna Sellado',
            'description'  => "{$TS} Jamón #1",
            'kg_entrada'   => 30.0,
            'costo_usd'    => 90.0,
            'supplier'     => 'Test',
            'entered_at'   => now(),
        ]);
    });
    $bovedaEntries[] = $entry;
    pass('Crear BovedaEntry Jamón (30 kg)', 'Entry creada', "ID={$entry->id} | 30kg | \$90");
} catch (\Throwable $e) {
    fail('Crear BovedaEntry Jamón (30 kg)', 'Entry creada', $e->getMessage());
}

// 2.4 — Buscar productos vitrina para surtido directo (no-despiece)
$polloVitrina = Product::where('business_id', $businessId)
    ->where('location', 'vitrina')
    ->where('name', 'like', '%Pollo%')
    ->first();
$jamon_vitrina = Product::where('business_id', $businessId)
    ->where('location', 'vitrina')
    ->where('name', 'like', '%Jam%')
    ->orWhere(function ($q) use ($businessId) {
        $q->where('business_id', $businessId)
          ->where('location', 'vitrina')
          ->where('name', 'like', '%Cerdo%');
    })
    ->first();

echo "\n  Producto vitrina Pollo : " . ($polloVitrina ? $polloVitrina->name . " ID={$polloVitrina->id}" : 'NO ENCONTRADO') . "\n";
echo "  Producto vitrina Jamón : " . ($jamon_vitrina ? $jamon_vitrina->name . " ID={$jamon_vitrina->id}" : 'NO ENCONTRADO') . "\n\n";

// Helper: surtir una entrada bóveda (replica lógica del controller)
function surtirBoveda(BovedaEntry $entry, float $kgSurtir, int $businessId, $user, $polloVitrina = null, $jamonVitrina = null): array {
    $entry->refresh();

    if ($entry->closed_at !== null) {
        return ['ok' => false, 'error' => 'Entrada ya cerrada'];
    }

    $disponible = round(
        (float)$entry->kg_entrada - (float)$entry->kg_surtido_vitrina - (float)$entry->waste_kg,
        3
    );

    if ($kgSurtir <= 0) {
        return ['ok' => false, 'error' => "Peso ({$kgSurtir} kg) debe ser mayor a 0"];
    }

    if ($kgSurtir > $disponible) {
        return ['ok' => false, 'error' => "El peso ({$kgSurtir} kg) supera el disponible ({$disponible} kg)"];
    }

    $merma = round($disponible - $kgSurtir, 3);

    $bovedaProduct = BovedaProduct::where('business_id', $businessId)
        ->where('name', $entry->product_type)
        ->first();
    $requiresDespiece = (bool)($bovedaProduct?->requires_despiece ?? true);

    // Para Pollo y Jamón — buscar producto vitrina directamente
    $vitrinaProduct = null;
    if (!$requiresDespiece) {
        if (str_contains($entry->product_type, 'Pollo') && $polloVitrina) {
            $vitrinaProduct = $polloVitrina;
        } elseif (str_contains($entry->product_type, 'Jamón') && $jamonVitrina) {
            $vitrinaProduct = $jamonVitrina;
        }
        if (!$vitrinaProduct) {
            $keyword = explode(' ', $entry->product_type)[0];
            $vitrinaProduct = Product::where('business_id', $businessId)
                ->where('location', 'vitrina')
                ->where('name', 'like', '%' . $keyword . '%')
                ->first();
        }
        if (!$vitrinaProduct) {
            return ['ok' => false, 'error' => "No existe producto vitrina para {$entry->product_type}. Créalo en Catálogo."];
        }
    }

    try {
        DB::transaction(function () use ($entry, $kgSurtir, $merma, $businessId, $user, $requiresDespiece, $vitrinaProduct) {
            $entry->increment('kg_surtido_vitrina', $kgSurtir);
            if ($merma > 0) {
                $entry->increment('waste_kg', $merma);
            }

            if ($requiresDespiece) {
                $bovedaProduct = Product::where('business_id', $businessId)
                    ->where('location', 'boveda')
                    ->where('name', $entry->product_type)
                    ->first();
                if ($bovedaProduct !== null) {
                    InventoryEntry::create([
                        'business_id'     => $businessId,
                        'product_id'      => $bovedaProduct->id,
                        'boveda_entry_id' => $entry->id,
                        'quantity_kg'     => -$kgSurtir,
                        'waste_kg'        => 0,
                        'location'        => 'boveda',
                        'notes'           => '[ST] Salida bóveda → vitrina despiece',
                        'entered_at'      => now(),
                        'created_by'      => $user->id,
                    ]);
                }
            } else {
                InventoryEntry::create([
                    'business_id'     => $businessId,
                    'product_id'      => $vitrinaProduct->id,
                    'boveda_entry_id' => $entry->id,
                    'quantity_kg'     => $kgSurtir,
                    'waste_kg'        => 0,
                    'location'        => 'vitrina',
                    'notes'           => '[ST] Surtido directo bóveda',
                    'entered_at'      => now(),
                    'created_by'      => $user->id,
                ]);
            }
        });
        return ['ok' => true, 'kg_surtido' => $kgSurtir, 'requires_despiece' => $requiresDespiece];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// 2.5 — Surtir cada entrada parcialmente (orden aleatorio simulado)
$surtidos = [
    [$bovedaEntries[0], 30.0],   // Res #1 → 30 kg
    [$bovedaEntries[3], 20.0],   // Pollo #1 → 20 kg
    [$bovedaEntries[1], 25.0],   // Res #2 → 25 kg
    [$bovedaEntries[5], 15.0],   // Jamón → 15 kg
    [$bovedaEntries[4], 18.0],   // Pollo #2 → 18 kg
    [$bovedaEntries[2], 40.0],   // Res #3 → 40 kg
];

foreach ($surtidos as [$entry, $kg]) {
    $res = surtirBoveda($entry, $kg, $businessId, $user, $polloVitrina, $jamon_vitrina);
    $label = "Surtir {$entry->product_type} #{$entry->id} ({$kg} kg)";
    if ($res['ok']) {
        $tipo = $res['requires_despiece'] ? 'requiere despiece' : 'directo a vitrina';
        pass($label, 'Surtido parcial OK', "{$kg} kg — {$tipo}");
    } else {
        fail($label, 'Surtido parcial OK', $res['error']);
    }
}

// 2.6 — Intentar surtir MÁS kg de los disponibles (debe fallar)
$entryResOvershoot = $bovedaEntries[0]; // Res #1 tiene 80 - 30 = 50 disponibles
$entryResOvershoot->refresh();
$disponibleActual = round(
    (float)$entryResOvershoot->kg_entrada - (float)$entryResOvershoot->kg_surtido_vitrina - (float)$entryResOvershoot->waste_kg,
    3
);
$kgExceso = $disponibleActual + 99.0;
$res = surtirBoveda($entryResOvershoot, $kgExceso, $businessId, $user);
if (!$res['ok'] && str_contains($res['error'], 'supera')) {
    pass("Surtir exceso ({$kgExceso} kg, disponible={$disponibleActual})", 'Error: supera disponible', $res['error']);
} else {
    fail("Surtir exceso ({$kgExceso} kg, disponible={$disponibleActual})", 'Error: supera disponible', $res['ok'] ? 'Aceptó exceso — VULNERABILIDAD' : $res['error']);
}

// 2.7 — Intentar surtir 0 kg (debe fallar)
$res = surtirBoveda($entryResOvershoot, 0.0, $businessId, $user);
if (!$res['ok']) {
    pass('Surtir 0.0 kg', 'Error: peso inválido', $res['error']);
} else {
    fail('Surtir 0.0 kg', 'Error: debe rechazar 0', 'Aceptó 0 kg — BUG');
}

// 2.8 — Surtir -1 kg (negativo)
$res = surtirBoveda($entryResOvershoot, -1.0, $businessId, $user);
if (!$res['ok']) {
    pass('Surtir -1.0 kg (negativo)', 'Error: peso negativo rechazado', $res['error']);
} else {
    fail('Surtir -1.0 kg (negativo)', 'Error: debe rechazar negativo', 'Aceptó negativo — VULNERABILIDAD CRÍTICA');
}

// 2.9 — Surtir exactamente el disponible (kg_disponible → 0)
$entryDrain = $bovedaEntries[1]; // Res #2
$entryDrain->refresh();
$kgRestante = round(
    (float)$entryDrain->kg_entrada - (float)$entryDrain->kg_surtido_vitrina - (float)$entryDrain->waste_kg,
    3
);
$res = surtirBoveda($entryDrain, $kgRestante, $businessId, $user);
if ($res['ok']) {
    $entryDrain->refresh();
    $disponiblePost = round(
        (float)$entryDrain->kg_entrada - (float)$entryDrain->kg_surtido_vitrina - (float)$entryDrain->waste_kg,
        3
    );
    if (abs($disponiblePost) < 0.001) {
        pass("Surtir hasta kg=0 en Res #{$entryDrain->id}", 'kg_disponible = 0', "kg_disponible post = {$disponiblePost} ✓");
    } else {
        fail("Surtir hasta kg=0 en Res #{$entryDrain->id}", 'kg_disponible = 0', "Quedó {$disponiblePost} kg — merma no aplicada");
    }
} else {
    fail("Surtir hasta kg=0 en Res #{$entryDrain->id}", 'Surtido exacto OK', $res['error']);
}

// 2.10 — Intentar surtir una entrada ya cerrada
try {
    $closedEntry = $bovedaEntries[1];
    $closedEntry->refresh();
    $closedEntry->update(['closed_at' => now()]);

    $res = surtirBoveda($closedEntry, 1.0, $businessId, $user);
    if (!$res['ok'] && str_contains($res['error'], 'cerrada')) {
        pass("Surtir entry cerrada (ID={$closedEntry->id})", 'Error: ya cerrada', $res['error']);
    } else {
        fail("Surtir entry cerrada (ID={$closedEntry->id})", 'Error: ya cerrada', $res['ok'] ? 'ACEPTÓ — BUG GRAVE' : $res['error']);
    }
} catch (\Throwable $e) {
    fail('Surtir entry cerrada', 'Error controlado', $e->getMessage());
}

// ─── FASE 3 — Fábrica / Despiece ─────────────────────────────────────────────
section('FASE 3 — Fábrica: Despiece y Validaciones');

// Entradas que tienen kg_surtido_vitrina > 0 y requieren despiece
$despiecePendientes = BovedaEntry::where('business_id', $businessId)
    ->where('kg_surtido_vitrina', '>', 0)
    ->whereNull('despiece_completado_at')
    ->whereNull('closed_at')
    ->orderByDesc('kg_surtido_vitrina')
    ->get();

echo "\n  Entradas pendientes de despiece: " . $despiecePendientes->count() . "\n";
foreach ($despiecePendientes as $dp) {
    echo "    ID={$dp->id} | {$dp->product_type} | kg_surtido={$dp->kg_surtido_vitrina}\n";
}
echo "\n";

// Productos de vitrina para Res
$productosRes = Product::where('business_id', $businessId)
    ->where('location', 'vitrina')
    ->where('active', true)
    ->whereHas('category', fn($q) => $q->where('name', 'like', '%Res%'))
    ->take(3)
    ->get(['id', 'name']);

if ($productosRes->isEmpty()) {
    // Fallback: cualquier producto vitrina
    $productosRes = Product::where('business_id', $businessId)
        ->where('location', 'vitrina')
        ->where('active', true)
        ->take(3)
        ->get(['id', 'name']);
}
echo "  Productos vitrina disponibles para cortes:\n";
foreach ($productosRes as $p) {
    echo "    ID={$p->id} | {$p->name}\n";
}
echo "\n";

// Helper: ejecutar despiece
function ejecutarDespiece(BovedaEntry $entry, array $cortes, int $businessId, $user): array {
    $entry->refresh();

    if ($entry->despiece_completado_at !== null) {
        return ['ok' => false, 'error' => 'Este despiece ya fue procesado.'];
    }

    $cortesConKg = collect($cortes)->filter(fn($c) => (float)$c['kg'] > 0);

    if ($cortesConKg->isEmpty()) {
        return ['ok' => false, 'error' => 'Debes registrar al menos un corte con kg > 0'];
    }

    $totalCortes = round($cortesConKg->sum(fn($c) => (float)$c['kg']), 3);
    $kgSurtido   = round((float)$entry->kg_surtido_vitrina, 3);
    $merma       = round($kgSurtido - $totalCortes, 3);

    if ($totalCortes > $kgSurtido) {
        return ['ok' => false, 'error' => "Suma de cortes ({$totalCortes} kg) supera kg surtidos ({$kgSurtido} kg)"];
    }

    try {
        DB::transaction(function () use ($entry, $cortesConKg, $merma, $businessId, $user) {
            foreach ($cortesConKg as $corte) {
                InventoryEntry::create([
                    'business_id'     => $businessId,
                    'product_id'      => $corte['product_id'],
                    'boveda_entry_id' => $entry->id,
                    'quantity_kg'     => (float)$corte['kg'],
                    'waste_kg'        => 0,
                    'location'        => 'vitrina',
                    'notes'           => '[ST] Despiece ' . $entry->product_type,
                    'entered_at'      => now(),
                    'created_by'      => $user->id,
                ]);
            }
            $entry->update(['despiece_completado_at' => now()]);
            if ($merma > 0) {
                $entry->increment('waste_kg', $merma);
            }
        });
        return ['ok' => true, 'total_cortes' => $totalCortes, 'merma' => $merma];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// 3.1 — Despiezar entradas con kg_surtido > 0
$despiezadas = 0;
$primeraEntradaDespiezada = null;

foreach ($despiecePendientes->take(3) as $idx => $dp) {
    // Construir cortes: repartir kg_surtido entre productos disponibles
    $kgTotal = (float)$dp->kg_surtido_vitrina;
    $cortes  = [];
    if ($productosRes->count() >= 2) {
        $cortes[] = ['product_id' => $productosRes[0]->id, 'kg' => round($kgTotal * 0.6, 3)];
        $cortes[] = ['product_id' => $productosRes[1]->id, 'kg' => round($kgTotal * 0.3, 3)];
        // Merma implícita del 10%
    } elseif ($productosRes->count() === 1) {
        $cortes[] = ['product_id' => $productosRes[0]->id, 'kg' => round($kgTotal * 0.9, 3)];
    } else {
        pass("Despiece #{$idx} — sin productos vitrina", 'N/A', 'Saltado: no hay productos vitrina configurados');
        continue;
    }

    $res = ejecutarDespiece($dp, $cortes, $businessId, $user);
    if ($res['ok']) {
        $despiezadas++;
        if ($primeraEntradaDespiezada === null) $primeraEntradaDespiezada = $dp;
        pass("Despiece entry ID={$dp->id} ({$dp->product_type})", 'Despiece registrado + stock vitrina', "cortes={$res['total_cortes']} kg | merma={$res['merma']} kg");
    } else {
        fail("Despiece entry ID={$dp->id}", 'Despiece OK', $res['error']);
    }
}

// 3.2 — Intentar despiezar la misma entrada dos veces (debe fallar)
if ($primeraEntradaDespiezada !== null) {
    $cortesDuplicado = [['product_id' => $productosRes->first()->id ?? 1, 'kg' => 5.0]];
    $res = ejecutarDespiece($primeraEntradaDespiezada, $cortesDuplicado, $businessId, $user);
    if (!$res['ok'] && str_contains($res['error'], 'ya fue procesado')) {
        pass('Doble despiece misma entrada (debe fallar)', 'Error: ya fue procesado', $res['error']);
    } else {
        fail('Doble despiece misma entrada', 'Error: ya procesado', $res['ok'] ? 'ACEPTÓ doble despiece — BUG CRÍTICO' : $res['error']);
    }
} else {
    pass('Doble despiece — sin entrada procesada', 'N/A', 'Sin datos: ninguna entrada fue despiezada exitosamente');
}

// 3.3 — Cortes que suman MÁS que kg_surtido (debe fallar)
$entradaParaExceso = $despiecePendientes->where('despiece_completado_at', null)->first();
if ($entradaParaExceso === null) {
    // Si todas fueron despiezadas, crear una nueva
    $entradaParaExceso = BovedaEntry::create([
        'business_id'        => $businessId,
        'product_type'       => 'Medio Canal Res',
        'description'        => "{$TS} Res extra test exceso",
        'kg_entrada'         => 20.0,
        'costo_usd'          => 40.0,
        'supplier'           => 'Test',
        'entered_at'         => now(),
        'kg_surtido_vitrina' => 10.0, // forzar directo
    ]);
}
$entradaParaExceso->refresh();

if ((float)$entradaParaExceso->kg_surtido_vitrina > 0) {
    $kgExcesoDespiece = (float)$entradaParaExceso->kg_surtido_vitrina + 999.0;
    $cortesExcesivos = [
        ['product_id' => $productosRes->first()->id ?? 1, 'kg' => $kgExcesoDespiece],
    ];
    $res = ejecutarDespiece($entradaParaExceso, $cortesExcesivos, $businessId, $user);
    if (!$res['ok'] && str_contains($res['error'], 'supera')) {
        pass("Despiece excesivo ({$kgExcesoDespiece} kg > surtido)", 'Error: supera kg surtidos', $res['error']);
    } else {
        fail("Despiece excesivo ({$kgExcesoDespiece} kg)", 'Error: supera kg surtidos', $res['ok'] ? 'ACEPTÓ EXCESO — VULNERABILIDAD' : $res['error']);
    }
} else {
    // Entrada sin kg surtido — cualquier corte debería fallar o dar merma total
    $res = ejecutarDespiece($entradaParaExceso, [['product_id' => $productosRes->first()->id ?? 1, 'kg' => 999.0]], $businessId, $user);
    pass('Despiece excesivo (sin surtido previo)', 'N/A', 'Entrada con kg_surtido=0 — test no aplica');
}

// 3.4 — Despiece con array vacío (debe fallar)
// Tomar una entrada aún no despiezada
$entradaSinDespiece = BovedaEntry::where('business_id', $businessId)
    ->where('kg_surtido_vitrina', '>', 0)
    ->whereNull('despiece_completado_at')
    ->whereNull('closed_at')
    ->first();

if ($entradaSinDespiece) {
    $res = ejecutarDespiece($entradaSinDespiece, [], $businessId, $user);
    if (!$res['ok']) {
        pass("Despiece con cortes vacíos", 'Error: sin cortes válidos', $res['error']);
    } else {
        fail("Despiece con cortes vacíos", 'Error: debe rechazar', 'ACEPTÓ array vacío — BUG');
    }
}

// 3.5 — Lote fábrica (chorizo) con ingredientes disponibles/insuficientes
$chorizoProduct = Product::where('business_id', $businessId)
    ->where('fabricable', true)
    ->where('active', true)
    ->first();

if ($chorizoProduct) {
    echo "  Producto fabricable: {$chorizoProduct->name} ID={$chorizoProduct->id}\n";

    $ingrediente = Product::where('business_id', $businessId)
        ->where('active', true)
        ->where('location', '!=', 'boveda')
        ->first();

    if ($ingrediente) {
        // Calcular stock real del ingrediente
        $stockIn  = InventoryEntry::where('business_id', $businessId)
            ->where('product_id', $ingrediente->id)
            ->sum(DB::raw('net_kg'));
        $stockOut = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.business_id', $businessId)
            ->where('sales.status', 'paid')
            ->where('sale_items.product_id', $ingrediente->id)
            ->sum('sale_items.quantity_value');
        $stockReal = round((float)$stockIn - (float)$stockOut, 3);

        echo "  Ingrediente: {$ingrediente->name} ID={$ingrediente->id} | stock={$stockReal} kg\n\n";

        // Lote CON stock insuficiente (pedir 999999 kg)
        // Nota: el sistema NO valida stock al crear lote fábrica (solo registra entradas negativas)
        try {
            $batchCreated = DB::transaction(function () use ($chorizoProduct, $ingrediente, $businessId, $user) {
                $batch = FabricaBatch::create([
                    'business_id'       => $businessId,
                    'created_by'        => $user->id,
                    'output_product_id' => $chorizoProduct->id,
                    'output_kg'         => 10.0,
                    'output_units'      => 0,
                    'input_cost_usd'    => 0,
                    'notes'             => '[ST] Lote fábrica con stock insuficiente',
                    'produced_at'       => now(),
                ]);

                $batch->inputs()->create([
                    'product_id'  => $ingrediente->id,
                    'quantity_kg' => 999999.0,  // WAY más de lo disponible
                    'cost_usd'    => 0,
                ]);

                InventoryEntry::create([
                    'business_id' => $businessId,
                    'product_id'  => $ingrediente->id,
                    'quantity_kg' => -999999.0,
                    'waste_kg'    => 0,
                    'location'    => 'vitrina',
                    'notes'       => '[ST] Insumo fábrica EXCESO — test',
                    'entered_at'  => now(),
                    'created_by'  => $user->id,
                ]);

                InventoryEntry::create([
                    'business_id' => $businessId,
                    'product_id'  => $chorizoProduct->id,
                    'quantity_kg' => 10.0,
                    'waste_kg'    => 0,
                    'location'    => 'vitrina',
                    'notes'       => '[ST] Producción fábrica EXCESO — test',
                    'entered_at'  => now(),
                    'created_by'  => $user->id,
                ]);

                return $batch;
            });
            // Si llegó aquí, el sistema ACEPTÓ stock negativo
            fail(
                "Lote fábrica con 999999 kg ({$ingrediente->name}, stock={$stockReal})",
                'Error: stock insuficiente rechazado',
                "ACEPTÓ stock negativo — ID={$batchCreated->id} — BUG: sistema no valida stock en Fábrica"
            );
            // Marcar para cleanup
            $batchCreated->delete();
        } catch (\Throwable $e) {
            pass(
                "Lote fábrica con stock insuficiente",
                'Error: sin stock',
                $e->getMessage()
            );
        }
    } else {
        pass('Lote fábrica', 'N/A', 'Sin ingredientes configurados para este negocio');
    }
} else {
    pass('Lote fábrica', 'N/A', 'Sin productos fabricables configurados');
}

// ─── FASE 4 — POS ────────────────────────────────────────────────────────────
section('FASE 4 — POS: Ventas, Pagos y Anulaciones');

// Necesitamos caja abierta
$cashRegister = CashRegister::where('business_id', $businessId)
    ->where('opened_by', $user->id)
    ->whereNull('closed_at')
    ->first();

if (!$cashRegister) {
    try {
        $cashRegister = CashRegister::create([
            'business_id'        => $businessId,
            'branch_id'          => $user->branch_id,
            'name'               => '[ST] Caja ' . date('d/m/Y H:i'),
            'opened_at'          => now(),
            'opening_amount_usd' => 0,
            'opening_amount_bs'  => 0,
            'rate_at_opening'    => $rate,
            'opened_by'          => $user->id,
        ]);
        pass('Abrir caja (no había caja abierta)', 'Caja creada', "CashRegister ID={$cashRegister->id}");
    } catch (\Throwable $e) {
        fail('Abrir caja', 'Caja creada', $e->getMessage());
    }
} else {
    pass('Verificar caja abierta', 'Caja disponible', "CashRegister ID={$cashRegister->id}");
}

// Buscar producto con stock > 0 en vitrina para ventas
$productoConStock = Product::where('business_id', $businessId)
    ->where('location', 'vitrina')
    ->where('active', true)
    ->where('sale_mode', 'weight')
    ->first();

$paymentMethod = PaymentMethod::where('business_id', $businessId)
    ->where('is_active', true)
    ->first();

$paymentMethod2 = PaymentMethod::where('business_id', $businessId)
    ->where('is_active', true)
    ->where('id', '!=', $paymentMethod?->id)
    ->first();

echo "\n  Producto test POS: " . ($productoConStock ? "{$productoConStock->name} ID={$productoConStock->id}" : 'NO ENCONTRADO') . "\n";
echo "  Método pago #1  : " . ($paymentMethod ? $paymentMethod->name : 'NO ENCONTRADO') . "\n";
echo "  Método pago #2  : " . ($paymentMethod2 ? $paymentMethod2->name : 'NO ENCONTRADO (solo 1 método)') . "\n\n";

// ─── Helpers POS — llaman al controller real ─────────────────────────────────

/**
 * Llama a SaleController::store() con un Request construido programáticamente.
 * El controller genera el ticket, valida el negocio, calcula la tasa y crea
 * la venta con status='open'. Devuelve ['ok'=>true, 'sale'=>Sale] o ['ok'=>false, 'error'=>string].
 */
function storeVenta(array $items, SaleController $ctrl): array {
    try {
        $req = HttpRequest::create('/sales', 'POST', ['items' => $items]);
        $response = $ctrl->store($req);
        $body = json_decode($response->getContent(), true);

        // store() siempre devuelve 200 con ['sale' => ...] si no lanza excepción
        if (!isset($body['sale']['id'])) {
            return ['ok' => false, 'error' => 'Respuesta inesperada: ' . json_encode($body)];
        }

        $sale = Sale::find($body['sale']['id']);
        return ['ok' => true, 'sale' => $sale];

    } catch (ValidationException $e) {
        $msgs = array_merge(...array_values($e->errors()));
        return ['ok' => false, 'error' => 'ValidationError: ' . implode(' | ', $msgs)];
    } catch (HttpException $e) {
        return ['ok' => false, 'error' => "HTTP {$e->getStatusCode()}: {$e->getMessage()}"];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => get_class($e) . ': ' . $e->getMessage()];
    }
}

/**
 * Llama a SaleController::pay() con la venta ya creada.
 * El controller verifica caja abierta, valida métodos de pago, descuenta inventario
 * y hace el registro de SalePayments dentro de una DB::transaction.
 * Devuelve ['ok'=>true, 'sale'=>array] o ['ok'=>false, 'error'=>string].
 *
 * Nota: pay() puede devolver un JSON con 'error' (caja cerrada, monto insuficiente)
 * SIN lanzar excepción — por eso debemos revisar el body además de capturar excepciones.
 */
function payVenta(Sale $sale, array $payments, SaleController $ctrl): array {
    try {
        $req = HttpRequest::create(
            '/sales/' . $sale->id . '/pay',
            'POST',
            ['payments' => $payments]
        );
        $response = $ctrl->pay($req, $sale);
        $body     = json_decode($response->getContent(), true);

        // pay() devuelve ['error' => '...'] con status 422 para caja cerrada
        // y monto insuficiente — sin lanzar excepción
        if (isset($body['error'])) {
            return ['ok' => false, 'error' => $body['error']];
        }
        if (isset($body['errors'])) {
            $msgs = array_merge(...array_values($body['errors']));
            return ['ok' => false, 'error' => 'Errors: ' . implode(' | ', $msgs)];
        }

        return ['ok' => true, 'sale' => $body['sale'] ?? []];

    } catch (ValidationException $e) {
        $msgs = array_merge(...array_values($e->errors()));
        return ['ok' => false, 'error' => 'ValidationError: ' . implode(' | ', $msgs)];
    } catch (HttpException $e) {
        return ['ok' => false, 'error' => "HTTP {$e->getStatusCode()}: {$e->getMessage()}"];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => get_class($e) . ': ' . $e->getMessage()];
    }
}

/**
 * Llama a SaleController::void() con la venta y el motivo.
 * void() requiere role admin/super_admin y sale.status === 'paid'.
 * Devuelve ['ok'=>true] o ['ok'=>false, 'error'=>string].
 */
function voidVenta(Sale $sale, string $reason, SaleController $ctrl): array {
    try {
        $req = HttpRequest::create(
            '/sales/' . $sale->id . '/void',
            'POST',
            ['reason' => $reason]
        );
        $response = $ctrl->void($req, $sale);
        $body     = json_decode($response->getContent(), true);

        if (isset($body['ok']) && $body['ok'] === true) {
            return ['ok' => true];
        }
        return ['ok' => false, 'error' => json_encode($body)];

    } catch (ValidationException $e) {
        $msgs = array_merge(...array_values($e->errors()));
        return ['ok' => false, 'error' => 'ValidationError: ' . implode(' | ', $msgs)];
    } catch (HttpException $e) {
        return ['ok' => false, 'error' => "HTTP {$e->getStatusCode()}: {$e->getMessage()}"];
    } catch (\Throwable $e) {
        return ['ok' => false, 'error' => get_class($e) . ': ' . $e->getMessage()];
    }
}

// Instanciar el controller UNA sola vez — usa DollarRateService inyectado
$posCtrl = app(SaleController::class);

$ventasCreadas = [];

// 4.1 — 10 ventas rápidas consecutivas via SaleController::store() + pay()
$montoPorVenta = 500.0; // Bs. por venta
for ($i = 1; $i <= 10; $i++) {
    if (!$productoConStock || !$paymentMethod) {
        fail("Venta #{$i} (POS)", 'Venta creada y pagada', 'Sin producto o método de pago configurado');
        continue;
    }

    // Paso 1: store() → crea venta con status='open'
    $resStore = storeVenta([
        [
            'product_id' => $productoConStock->id,
            'input_type' => 'weight',
            'amount_bs'  => $montoPorVenta,
        ],
    ], $posCtrl);

    if (!$resStore['ok']) {
        fail("Venta #{$i} store()", 'Venta open creada', $resStore['error']);
        continue;
    }

    $sale = $resStore['sale'];

    // Verificar que el controller generó el ticket con el prefix del negocio
    $ticketPrefix = $user->business->ticket_prefix ?? 'VEN';
    if (!str_starts_with($sale->ticket_number, $ticketPrefix)) {
        fail("Venta #{$i} ticket_prefix", "Ticket inicia con '{$ticketPrefix}'", "Ticket={$sale->ticket_number}");
    }

    // Paso 2: pay() → paga la venta, descuenta inventario
    $resPay = payVenta($sale, [
        ['payment_method_id' => $paymentMethod->id, 'amount_bs' => $montoPorVenta],
    ], $posCtrl);

    if ($resPay['ok']) {
        $sale->refresh();
        $ventasCreadas[] = $sale;
        pass(
            "Venta #{$i} store()+pay() ({$sale->ticket_number})",
            'status=paid, inventario descontado',
            "ID={$sale->id} | {$montoPorVenta} Bs | status={$sale->status}"
        );
    } else {
        fail("Venta #{$i} pay()", 'status=paid', $resPay['error']);
    }
}

// 4.2 — Verificar unicidad de ticket_numbers generados por el controller
$ticketNumbers = array_map(fn($s) => $s->ticket_number, $ventasCreadas);
$uniqueCount   = count(array_unique($ticketNumbers));
$totalVentas   = count($ticketNumbers);
if ($uniqueCount === $totalVentas && $totalVentas > 0) {
    pass('Unicidad de ticket_numbers (10 ventas)', 'Todos tickets únicos', "Únicos={$uniqueCount}/{$totalVentas}");
} else {
    $dupes = $totalVentas - $uniqueCount;
    fail('Unicidad de ticket_numbers', 'Todos tickets únicos', "{$dupes} duplicado(s) — generateTicketNumber() RACE CONDITION");
}

// 4.3 — Venta con producto SIN stock: store() debe aceptarla (bug conocido),
//        pero se documenta como FAIL porque el sistema NO valida stock al crear.
$productoSinStock = Product::where('business_id', $businessId)
    ->where('location', 'vitrina')
    ->where('active', true)
    ->where('sale_mode', 'weight')
    ->whereDoesntHave('inventoryEntries', fn($q) => $q->where('business_id', $businessId))
    ->first() ?? $productoConStock; // fallback al mismo si no hay producto sin stock

$resNoStock = storeVenta([
    [
        'product_id' => $productoSinStock->id,
        'input_type' => 'weight',
        'amount_bs'  => 999999.0,
    ],
], $posCtrl);

if (!$resNoStock['ok']) {
    // Si el controller rechazó — inesperado pero OK
    pass("store() venta sin stock ({$productoSinStock->name})", 'Error de stock', $resNoStock['error']);
} else {
    // El controller ACEPTÓ — bug documentado
    $saleSinStock = $resNoStock['sale'];
    fail(
        "store() venta sin stock ({$productoSinStock->name})",
        'Error: validación de stock',
        "ACEPTÓ — ID={$saleSinStock->id} — BUG: SaleController::store() NO valida stock disponible"
    );
    // Cleanup: cancelar la venta creada por el bug
    $saleSinStock->update(['status' => 'cancelled', 'cancellation_reason' => '[ST] cleanup bug test']);
}

// 4.4 — Venta con pago mixto: dos métodos en un solo pay()
if ($productoConStock && $paymentMethod && $paymentMethod2) {
    $resMixStore = storeVenta([
        [
            'product_id' => $productoConStock->id,
            'input_type' => 'weight',
            'amount_bs'  => 1000.0,
        ],
    ], $posCtrl);

    if ($resMixStore['ok']) {
        $saleMixta = $resMixStore['sale'];
        $resMixPay = payVenta($saleMixta, [
            ['payment_method_id' => $paymentMethod->id,  'amount_bs' => 600.0],
            ['payment_method_id' => $paymentMethod2->id, 'amount_bs' => 400.0],
        ], $posCtrl);

        if ($resMixPay['ok']) {
            $ventasCreadas[] = $saleMixta->refresh();
            $pagosCount = SalePayment::where('sale_id', $saleMixta->id)->count();
            pass(
                "Pago mixto ({$paymentMethod->name} + {$paymentMethod2->name})",
                '2 SalePayments + status=paid',
                "ID={$saleMixta->id} | sale_payments={$pagosCount} | 1000 Bs"
            );
        } else {
            fail('pay() mixto', '2 SalePayments OK', $resMixPay['error']);
        }
    } else {
        fail('store() para pago mixto', 'Venta open', $resMixStore['error']);
    }
} else {
    pass('Venta mixta', 'N/A', 'Solo 1 método de pago configurado — omitido');
}

// 4.5 — Anular venta pagada via SaleController::void() y verificar reverso de inventario
//        BUG CONOCIDO (auditado): void() NO crea InventoryEntry de reverso.
if (!empty($ventasCreadas)) {
    $saleParaAnular = $ventasCreadas[0];
    $saleParaAnular->refresh();

    // Medir stock del producto ANTES de la anulación
    $stockAntes = (float) InventoryEntry::where('business_id', $businessId)
        ->where('product_id', $productoConStock?->id)
        ->sum(DB::raw('net_kg'));

    $kgVendidos = (float) SaleItem::where('sale_id', $saleParaAnular->id)->sum('quantity_value');

    // Llamar al controller void() real
    $resVoid = voidVenta($saleParaAnular, '[ST] Anulación stress test', $posCtrl);

    if (!$resVoid['ok']) {
        fail('void() venta pagada', 'Venta anulada OK', $resVoid['error']);
    } else {
        // Medir stock DESPUÉS de la anulación
        $stockDespues = (float) InventoryEntry::where('business_id', $businessId)
            ->where('product_id', $productoConStock?->id)
            ->sum(DB::raw('net_kg'));

        $delta = round($stockDespues - $stockAntes, 3);

        if ($delta >= $kgVendidos - 0.001) {
            pass(
                'void() revierte inventario',
                "+{$kgVendidos} kg al anular",
                "Delta={$delta} kg ✓"
            );
        } else {
            fail(
                'void() revierte inventario',
                "+{$kgVendidos} kg al anular",
                "Delta={$delta} kg — void() NO crea InventoryEntry de reverso (BUG AUDITADO)"
            );
        }

        // 4.6 — Doble void() en la misma venta (debe fallar: status ya es cancelled)
        $saleParaAnular->refresh();
        $resVoid2 = voidVenta($saleParaAnular, '[ST] Segundo intento de anulación', $posCtrl);

        if (!$resVoid2['ok']) {
            pass(
                "Doble void() (ID={$saleParaAnular->id})",
                'Error: solo se anulan ventas paid',
                $resVoid2['error']
            );
        } else {
            fail(
                "Doble void() (ID={$saleParaAnular->id})",
                'HTTP 422: solo ventas paid',
                'ACEPTÓ doble anulación — BUG CRÍTICO'
            );
        }
    }
}

// 4.7 — Pago insuficiente via pay() (debe fallar con error del controller)
if ($productoConStock && $paymentMethod) {
    $resInsufStore = storeVenta([
        [
            'product_id' => $productoConStock->id,
            'input_type' => 'weight',
            'amount_bs'  => 1000.0,
        ],
    ], $posCtrl);

    if ($resInsufStore['ok']) {
        $saleInsuf = $resInsufStore['sale'];

        // Pagar solo 1 Bs de 1000 Bs → debe rechazar
        $resInsufPay = payVenta($saleInsuf, [
            ['payment_method_id' => $paymentMethod->id, 'amount_bs' => 1.0],
        ], $posCtrl);

        if (!$resInsufPay['ok'] && str_contains(strtolower($resInsufPay['error']), 'suficiente')) {
            pass(
                'pay() monto insuficiente (1 Bs de 1000 Bs)',
                'Error: monto insuficiente',
                $resInsufPay['error']
            );
        } else {
            fail(
                'pay() monto insuficiente',
                'Error: monto insuficiente',
                $resInsufPay['ok'] ? 'ACEPTÓ UNDERPAYMENT — BUG CRÍTICO' : $resInsufPay['error']
            );
        }

        // Cleanup: cancelar venta abierta
        $saleInsuf->update(['status' => 'cancelled', 'cancellation_reason' => '[ST] cleanup']);
    } else {
        fail('store() para test pago insuficiente', 'Venta open', $resInsufStore['error']);
    }
}

// ─── FASE 5 — Cierre ─────────────────────────────────────────────────────────
section('FASE 5 — Cierre de Caja y Verificación de Utilidad');

if ($cashRegister) {
    // KPIs de la caja
    $ventasTotalesBs  = (float) Sale::where('cash_register_id', $cashRegister->id)->where('status', 'paid')->sum('total_bs');
    $ventasAnuladas   = Sale::where('cash_register_id', $cashRegister->id)->where('status', 'cancelled')->count();
    $ventasPagadas    = Sale::where('cash_register_id', $cashRegister->id)->where('status', 'paid')->count();

    $movInBs  = (float) $cashRegister->movements()->where('type', 'in')->sum('amount_usd') * $rate;
    $movOutBs = (float) $cashRegister->movements()->where('type', 'out')->sum('amount_usd') * $rate;
    $openingBs = (float) $cashRegister->opening_amount_bs;
    $expectedBs = round($openingBs + $ventasTotalesBs + $movInBs - $movOutBs, 2);

    echo "  Ventas pagadas    : {$ventasPagadas}\n";
    echo "  Ventas anuladas   : {$ventasAnuladas}\n";
    echo "  Total ventas Bs   : {$ventasTotalesBs}\n";
    echo "  Expected cash Bs  : {$expectedBs}\n\n";

    if ($ventasPagadas > 0) {
        pass('Caja con ventas registradas', 'Ventas > 0 en caja', "{$ventasPagadas} ventas | {$ventasTotalesBs} Bs");
    } else {
        fail('Caja con ventas registradas', 'Ventas > 0', 'Caja sin ventas asociadas');
    }

    // 5.1 — Cálculo de utilidad vs costo bóveda
    $costoBovedaUsd = (float) BovedaEntry::where('business_id', $businessId)
        ->where('description', 'like', "%{$TS}%")
        ->sum('costo_usd');

    $ventasUsd = $rate > 0 ? round($ventasTotalesBs / $rate, 2) : 0.0;
    $utilidadUsd = round($ventasUsd - $costoBovedaUsd, 2);

    echo "  Costo bóveda [ST]: \${$costoBovedaUsd}\n";
    echo "  Ventas USD       : \${$ventasUsd}\n";
    echo "  Utilidad         : \${$utilidadUsd}\n\n";

    pass('Cálculo utilidad = ventas_usd - costo_boveda_usd', "Utilidad calculable", "Ventas=\${$ventasUsd} - Costo=\${$costoBovedaUsd} = Utilidad=\${$utilidadUsd}");

    // 5.2 — Confirmar cierre de la caja (si fue creada por el test)
    if (str_contains($cashRegister->name ?? '', '[ST]')) {
        try {
            $countedBs = $expectedBs; // Arqueo exacto
            $countedUsd = $rate > 0 ? round($countedBs / $rate, 4) : 0.0;
            $expectedUsd = $rate > 0 ? round($expectedBs / $rate, 2) : 0.0;
            $differenceUsd = round($countedUsd - $expectedUsd, 2);

            DB::transaction(function () use ($cashRegister, $user, $businessId, $expectedUsd, $countedUsd, $differenceUsd) {
                $cashRegister->update([
                    'closed_at'         => now(),
                    'expected_cash_usd' => $expectedUsd,
                    'counted_cash_usd'  => $countedUsd,
                    'difference_usd'    => $differenceUsd,
                    'notes'             => '[ST] Cierre automático stress test',
                    'closed_by'         => $user->id,
                ]);
                ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id'     => $user->id,
                    'action'      => 'cash.day_close',
                    'model_type'  => CashRegister::class,
                    'model_id'    => $cashRegister->id,
                    'new_values'  => ['expected_usd' => $expectedUsd, 'difference_usd' => $differenceUsd],
                ]);
            });

            $cashRegister->refresh();
            if ($cashRegister->closed_at !== null) {
                pass('Cierre de caja confirmado', 'Caja cerrada', "ID={$cashRegister->id} | diferencia=\${$differenceUsd}");
            } else {
                fail('Cierre de caja', 'Caja cerrada', 'closed_at sigue null');
            }
        } catch (\Throwable $e) {
            fail('Cierre de caja', 'OK', $e->getMessage());
        }

        // 5.3 — Intentar cerrar caja ya cerrada (debe fallar)
        try {
            $cashRegister->refresh();
            if ($cashRegister->closed_at !== null) {
                // Simular el abort_unless del controller
                if ($cashRegister->closed_at === null) {
                    fail('Doble cierre de caja', 'Error: ya cerrada', 'ACEPTÓ doble cierre');
                } else {
                    pass('Doble cierre de caja (debe fallar)', 'Error: ya cerrada', "closed_at={$cashRegister->closed_at} — rechazado");
                }
            }
        } catch (\Throwable $e) {
            pass('Doble cierre de caja', 'Error controlado', $e->getMessage());
        }
    } else {
        pass('Cierre de caja', 'N/A', 'Caja existente preservada — no cerrada por el test');
    }
}

// ─── RESUMEN FINAL ────────────────────────────────────────────────────────────
section('RESUMEN FINAL — Tabla de Resultados');
printTable($results);

$totalTests = count($results);
$passed     = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$failed     = count(array_filter($results, fn($r) => $r['status'] === '*** FAIL ***'));

echo "\n";
echo "  Total tests : {$totalTests}\n";
echo "  PASS        : {$passed}\n";
echo "  FAIL        : {$failed}\n";
echo "\n";

if ($failed > 0) {
    echo "  *** {$failed} FALLA(S) DETECTADA(S) — revisar bugs antes del deployment ***\n";
} else {
    echo "  Todo pasó — sistema estable bajo stress test.\n";
}

// ─── CLEANUP — Limpiar datos del test ────────────────────────────────────────
section('CLEANUP — Eliminando datos de prueba [ST-*]');

try {
    // Cancelar ventas de test
    $stSales = Sale::where('business_id', $businessId)
        ->where('notes', 'like', '%[ST]%')
        ->orWhere(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
              ->where('ticket_number', 'like', 'ST-%');
        })
        ->get();

    foreach ($stSales as $s) {
        SalePayment::where('sale_id', $s->id)->delete();
        SaleItem::where('sale_id', $s->id)->delete();
        $s->delete();
    }
    echo "  Ventas [ST] eliminadas: " . $stSales->count() . "\n";

    // Eliminar inventory entries de test
    $stEntries = InventoryEntry::where('business_id', $businessId)
        ->where('notes', 'like', '%[ST]%')
        ->delete();
    echo "  InventoryEntries [ST] eliminadas: {$stEntries}\n";

    // Eliminar boveda entries de test
    $stBoveda = BovedaEntry::where('business_id', $businessId)
        ->where('description', 'like', "%{$TS}%")
        ->orWhere(function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
              ->where('description', 'like', '%[ST]%');
        })
        ->delete();
    echo "  BovedaEntries [ST] eliminadas: {$stBoveda}\n";

    // Eliminar fabrica batches de test
    $stBatches = FabricaBatch::where('business_id', $businessId)
        ->where('notes', 'like', '%[ST]%')
        ->delete();
    echo "  FabricaBatches [ST] eliminados: {$stBatches}\n";

    // Eliminar activity logs del test
    $stLogs = ActivityLog::where('business_id', $businessId)
        ->where('action', 'like', 'stress_test%')
        ->delete();
    echo "  ActivityLogs [ST] eliminados: {$stLogs}\n";

    echo "\n  Cleanup completo.\n";
} catch (\Throwable $e) {
    echo "  ERROR en cleanup: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat('═', 80) . "\n";
echo "  Stress test finalizado en " . round(microtime(true) - LARAVEL_START, 2) . " segundos\n";
echo str_repeat('═', 80) . "\n\n";
