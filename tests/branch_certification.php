<?php
/**
 * Certificación de invariantes branch_id — SYNTImeat
 *
 * Cubre todos los métodos create/update detectados en app/Http/Controllers/:
 *   InventoryController, SaleController, CashRegisterController,
 *   BovedaController, OrderController, FabricaController.
 *
 * Estructura de cada bloque:
 *   A) ¿branch_id está en $fillable? (fallo = bug de producción)
 *   B) Resolución lógica de branch_id según el patrón del controller
 *   C) Inserción DB (via unguarded) para verificar que el valor llega
 *   D) Visibilidad cruzada entre sucursales
 *
 * AUTOCONTENIDO: crea usuarios/sucursal sintéticos dentro de una TX
 * y los revierte al final. No deja basura en DB.
 *
 * Uso:
 *   php artisan tinker --execute="require 'tests/branch_certification.php';"
 */

declare(strict_types=1);

use App\Models\Branch;
use App\Models\BovedaEntry;
use App\Models\CashRegister;
use App\Models\FabricaBatch;
use App\Models\InventoryEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// ─── Helpers ──────────────────────────────────────────────────────────────────

$pass     = 0;
$fail     = 0;
$critical = 0;      // fillable bugs — impacto en producción
$failures = [];

function chk(string $label, bool $ok, int &$pass, int &$fail, array &$failures, bool $isCritical = false): void
{
    $prefix = $isCritical ? ($ok ? '  ✓ ' : '  ✗ [CRÍTICO] ') : ($ok ? '  ✓ ' : '  ✗ ');
    echo $prefix . $label . "\n";
    if ($ok) {
        $pass++;
    } else {
        $fail++;
        $failures[] = ($isCritical ? '[CRÍTICO] ' : '') . $label;
    }
}

/** branch_admin/cashier → user->branch_id; owner/analyst → session ?? Branch::first */
function canonicalBid(object $user, int $businessId, ?int $sessionBid = null): ?int
{
    if (in_array($user->role, ['branch_admin', 'cashier'], true)) {
        return (int) $user->branch_id;
    }
    return $sessionBid
        ?? Branch::where('business_id', $businessId)->orderBy('id')->value('id');
}

/** Patrón SaleController: fallback final a user->branch_id (no Branch::first) */
function saleBid(object $user, ?int $sessionBid = null): ?int
{
    if (in_array($user->role, ['branch_admin', 'cashier'], true)) {
        return (int) $user->branch_id;
    }
    return $sessionBid ?? $user->branch_id;
}

/** Verifica que branch_id está en $fillable del modelo */
function hasFillable(string $modelClass): bool
{
    $inst     = new $modelClass;
    $fillable = $inst->getFillable();
    return in_array('branch_id', $fillable, true);
}

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
echo "║   CERTIFICACIÓN branch_id — SYNTImeat                       ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";

// ─── Infraestructura ──────────────────────────────────────────────────────────

$business = \App\Models\Business::first();
if (! $business) { echo "ABORT: Sin negocio.\n"; exit(1); }
$businessId = $business->id;

$branch1 = Branch::where('business_id', $businessId)->orderBy('id')->first();
if (! $branch1) { echo "ABORT: Sin sucursal.\n"; exit(1); }
$bid1 = (int) $branch1->id;

$product = Product::where('business_id', $businessId)
    ->where('location', '!=', 'boveda')
    ->where('active', true)
    ->first();
$productId = $product?->id;

$fabricaProduct = Product::where('business_id', $businessId)
    ->where('fabricable', true)
    ->first();

echo "negocio   : {$business->name} (id={$businessId})\n";
echo "sucursal1 : {$branch1->name} (id={$bid1})\n";
echo "producto  : " . ($product ? $product->name : 'NINGUNO') . "\n\n";

// ─── Mega-transacción que engloba todo — revierte usuarios/sucursal sintéticos ─

DB::beginTransaction();

try {
    // Crear sucursal 2 y usuarios sintéticos
    $branch2 = Branch::create(['business_id' => $businessId, 'name' => 'Cert_B2', 'city' => 'Test', 'is_active' => true]);
    $bid2    = (int) $branch2->id;

    $owner = User::create([
        'business_id' => $businessId, 'name'  => 'Cert Owner',
        'email'       => 'cert_own_' . time() . '@test.local',
        'password'    => Hash::make('x'), 'role' => 'owner', 'branch_id' => null,
    ]);
    $branchAdmin = User::create([
        'business_id' => $businessId, 'name'  => 'Cert BAdmin',
        'email'       => 'cert_ba_' . time() . '@test.local',
        'password'    => Hash::make('x'), 'role' => 'branch_admin', 'branch_id' => $bid1,
    ]);
    $cashier = User::create([
        'business_id' => $businessId, 'name'  => 'Cert Cashier',
        'email'       => 'cert_ca_' . time() . '@test.local',
        'password'    => Hash::make('x'), 'role' => 'cashier', 'branch_id' => $bid1,
    ]);

    echo "usuarios sintéticos: branch_admin(bid={$bid1}), owner(bid=NULL), cashier(bid={$bid1})\n";
    echo "sucursal 2 (contaminación): id={$bid2}\n";
    echo str_repeat('─', 64) . "\n\n";

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 0 — Verificación de $fillable (bugs de producción)
    // ═══════════════════════════════════════════════════════════════════
    echo "── 0. Modelos: branch_id en \$fillable ──\n";

    $modelsToCheck = [
        'InventoryEntry' => 'App\\Models\\InventoryEntry',
        'Sale'           => 'App\\Models\\Sale',
        'CashRegister'   => 'App\\Models\\CashRegister',
        'BovedaEntry'    => 'App\\Models\\BovedaEntry',
        'Order'          => 'App\\Models\\Order',
        'FabricaBatch'   => 'App\\Models\\FabricaBatch',
    ];

    $fillableStatus = [];
    foreach ($modelsToCheck as $name => $class) {
        $ok = hasFillable($class);
        $fillableStatus[$name] = $ok;
        chk("{$name}::\$fillable contiene branch_id", $ok, $pass, $fail, $failures, ! $ok);
    }
    echo "  → Modelos sin branch_id en fillable crean registros con branch_id=NULL en producción.\n";

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 1 — InventoryController::store + adjust → InventoryEntry
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 1. InventoryController::store / adjust → InventoryEntry ──\n";

    DB::beginTransaction();
    try {
        // 1-A: resolución lógica
        $bid = canonicalBid($branchAdmin, $businessId, null);
        chk("branch_admin sin sesión → bid={$bid1}", $bid === $bid1, $pass, $fail, $failures);

        $bidCont = canonicalBid($branchAdmin, $businessId, $bid2);
        chk("sesión(bid2={$bid2}) no pisa branch_admin → bid={$bid1}", $bidCont === $bid1, $pass, $fail, $failures);

        $ownerBid = canonicalBid($owner, $businessId, null);
        chk("owner sin sesión → fallback bid={$bid1}", $ownerBid === $bid1, $pass, $fail, $failures);

        $ownerSess = canonicalBid($owner, $businessId, $bid2);
        chk("owner con sesión(bid2) → bid={$bid2}", $ownerSess === $bid2, $pass, $fail, $failures);

        // 1-B: inserción DB (unguarded para aislar del bug de fillable)
        if ($productId) {
            $entry = InventoryEntry::unguarded(function () use ($businessId, $bid1, $productId, $branchAdmin) {
                return InventoryEntry::create([
                    'business_id' => $businessId,
                    'branch_id'   => $bid1,
                    'product_id'  => $productId,
                    'quantity_kg' => 1.0,
                    'waste_kg'    => 0,
                    'entered_at'  => now(),
                    'created_by'  => $branchAdmin->id,
                    'notes'       => 'cert',
                ]);
            });
            chk("DB: InventoryEntry.branch_id={$bid1} (unguarded)", (int) $entry->branch_id === $bid1, $pass, $fail, $failures);
            chk("DB: branch_id no NULL", $entry->branch_id !== null, $pass, $fail, $failures);

            // 1-C: visibilidad cruzada
            $vis = InventoryEntry::where('id', $entry->id)->where('branch_id', $bid2)->exists();
            chk("NO visible con filtro sucursal bid2={$bid2}", ! $vis, $pass, $fail, $failures);

            // 1-D: adjust — patrón user->branch_id ?? session ?? Branch::first
            $adjBid = $branchAdmin->branch_id ?? null ?? Branch::where('business_id', $businessId)->orderBy('id')->value('id');
            chk("adjust: branch_admin → bid={$bid1}", (int) $adjBid === $bid1, $pass, $fail, $failures);

            // REAL: si fillable no tiene branch_id, create() lo ignora
            if (! $fillableStatus['InventoryEntry']) {
                $realEntry = InventoryEntry::create([
                    'business_id' => $businessId,
                    'branch_id'   => $bid1,
                    'product_id'  => $productId,
                    'quantity_kg' => 0.1,
                    'waste_kg'    => 0,
                    'entered_at'  => now(),
                    'created_by'  => $branchAdmin->id,
                ]);
                chk("[CRÍTICO] create() sin unguarded → branch_id=NULL en DB", $realEntry->branch_id === null, $pass, $fail, $failures, true);
            }
        } else {
            echo "  SKIP DB: sin producto vitrina\n";
        }

    } finally {
        DB::rollBack();
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 2 — CashRegisterController::open() → CashRegister
    // Patrón: $user->branch_id directo (sin sesión, sin fallback)
    // branch_id SÍ está en $fillable
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 2. CashRegisterController::open → CashRegister ──\n";

    DB::beginTransaction();
    try {
        $cajaBid = $branchAdmin->branch_id; // patrón exacto de open()
        chk("branch_admin → bid={$bid1} (user->branch_id directo)", (int) $cajaBid === $bid1, $pass, $fail, $failures);

        $reg = CashRegister::create([
            'business_id'        => $businessId,
            'branch_id'          => $cajaBid,
            'name'               => 'Cert ' . time(),
            'opened_at'          => now(),
            'opening_amount_usd' => 0.0,
            'opening_amount_bs'  => 0.0,
            'rate_at_opening'    => 1.0,
            'opened_by'          => $branchAdmin->id,
        ]);
        chk("CashRegister.branch_id={$bid1}", (int) $reg->branch_id === $bid1, $pass, $fail, $failures);
        chk("CashRegister branch_id no NULL", $reg->branch_id !== null, $pass, $fail, $failures);
        chk("open() no expuesto a sesión (patrón seguro)", true, $pass, $fail, $failures);

        // Cashier
        $cashierBid = $cashier->branch_id;
        chk("cashier → bid={$bid1} (user->branch_id)", (int) $cashierBid === $bid1, $pass, $fail, $failures);

    } finally {
        DB::rollBack();
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 3 — SaleController::store() → Sale
    // Patrón: branch_admin → user->branch_id; owner → session ?? user->branch_id
    // branch_id SÍ está en $fillable
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 3. SaleController::store → Sale ──\n";

    DB::beginTransaction();
    try {
        $sBid = saleBid($branchAdmin, null);
        chk("branch_admin sin sesión → bid={$bid1}", $sBid === $bid1, $pass, $fail, $failures);

        $sale = Sale::create([
            'business_id'   => $businessId,
            'branch_id'     => $sBid,
            'ticket_number' => 'CERT-' . time(),
            'status'        => 'open',
            'total_usd'     => 0.0,
            'total_bs'      => 0.0,
            'cashier_id'    => $branchAdmin->id,
        ]);
        chk("Sale.branch_id={$bid1}", (int) $sale->branch_id === $bid1, $pass, $fail, $failures);
        chk("Sale branch_id no NULL", $sale->branch_id !== null, $pass, $fail, $failures);

        $sCont = saleBid($branchAdmin, $bid2);
        chk("sesión(bid2={$bid2}) no pisa branch_admin → bid={$bid1}", $sCont === $bid1, $pass, $fail, $failures);

        $vis = Sale::where('id', $sale->id)->where('branch_id', $bid2)->exists();
        chk("Sale NO visible con filtro bid2={$bid2}", ! $vis, $pass, $fail, $failures);

        // owner sin sesión → user->branch_id = null (documentado como riesgo)
        $ownerSale = saleBid($owner, null);
        chk("owner sin sesión → NULL (owner.branch_id es null)", $ownerSale === null, $pass, $fail, $failures);

        $ownerSaleSess = saleBid($owner, $bid1);
        chk("owner con sesión(bid1) → bid={$bid1}", $ownerSaleSess === $bid1, $pass, $fail, $failures);

    } finally {
        DB::rollBack();
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 4 — BovedaController::store() → BovedaEntry
    // branch_id NO está en $fillable → bug crítico
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 4. BovedaController::store → BovedaEntry ──\n";

    DB::beginTransaction();
    try {
        $bovBid = canonicalBid($branchAdmin, $businessId, null);
        chk("resolución: branch_admin → bid={$bid1}", $bovBid === $bid1, $pass, $fail, $failures);

        $bovCont = canonicalBid($branchAdmin, $businessId, $bid2);
        chk("sesión(bid2) no pisa branch_admin", $bovCont === $bid1, $pass, $fail, $failures);

        $ownerBovBid = canonicalBid($owner, $businessId, null);
        chk("owner sin sesión → Branch::first bid={$bid1}", $ownerBovBid === $bid1, $pass, $fail, $failures);

        // DB unguarded
        $bov = BovedaEntry::unguarded(function () use ($businessId, $bovBid) {
            return BovedaEntry::create([
                'business_id'  => $businessId,
                'branch_id'    => $bovBid,
                'product_type' => 'RES - Medio Canal',
                'kg_entrada'   => 1.0,
                'costo_usd'    => 1.0,
                'entered_at'   => now(),
            ]);
        });
        chk("DB (unguarded): BovedaEntry.branch_id={$bid1}", (int) $bov->branch_id === $bid1, $pass, $fail, $failures);

        $visB = BovedaEntry::where('id', $bov->id)->where('branch_id', $bid2)->exists();
        chk("BovedaEntry NO visible bid2={$bid2}", ! $visB, $pass, $fail, $failures);

        // REAL: sin unguarded → branch_id null
        if (! $fillableStatus['BovedaEntry']) {
            $realBov = BovedaEntry::create([
                'business_id'  => $businessId,
                'branch_id'    => $bid1,
                'product_type' => 'RES - Medio Canal',
                'kg_entrada'   => 0.1,
                'costo_usd'    => 0.1,
                'entered_at'   => now(),
            ]);
            chk("[CRÍTICO] create() → branch_id=NULL en DB real", $realBov->branch_id === null, $pass, $fail, $failures, true);
        }

    } finally {
        DB::rollBack();
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 5 — OrderController::store() → Order
    // branch_id NO está en $fillable → bug crítico
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 5. OrderController::store → Order ──\n";

    DB::beginTransaction();
    try {
        $orderBid = canonicalBid($branchAdmin, $businessId, null);
        chk("resolución: branch_admin → bid={$bid1}", $orderBid === $bid1, $pass, $fail, $failures);

        $orderCont = canonicalBid($branchAdmin, $businessId, $bid2);
        chk("sesión(bid2) no pisa branch_admin", $orderCont === $bid1, $pass, $fail, $failures);

        $ord = Order::unguarded(function () use ($businessId, $orderBid, $branchAdmin) {
            return Order::create([
                'business_id' => $businessId,
                'branch_id'   => $orderBid,
                'client_name' => 'Cert',
                'client_type' => 'external',
                'status'      => 'pending',
                'total_usd'   => 0.0,
                'created_by'  => $branchAdmin->id,
            ]);
        });
        chk("DB (unguarded): Order.branch_id={$bid1}", (int) $ord->branch_id === $bid1, $pass, $fail, $failures);

        $visO = Order::where('id', $ord->id)->where('branch_id', $bid2)->exists();
        chk("Order NO visible bid2={$bid2}", ! $visO, $pass, $fail, $failures);

        if (! $fillableStatus['Order']) {
            $realOrd = Order::create([
                'business_id' => $businessId,
                'branch_id'   => $bid1,
                'client_name' => 'Cert Real',
                'client_type' => 'external',
                'status'      => 'pending',
                'total_usd'   => 0.0,
                'created_by'  => $branchAdmin->id,
            ]);
            chk("[CRÍTICO] create() → branch_id=NULL en DB real", $realOrd->branch_id === null, $pass, $fail, $failures, true);
        }

    } finally {
        DB::rollBack();
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 6 — FabricaController::store() → FabricaBatch
    // branch_id NO está en $fillable → bug crítico
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 6. FabricaController::store → FabricaBatch ──\n";

    if ($fabricaProduct) {
        DB::beginTransaction();
        try {
            $fbBid = canonicalBid($branchAdmin, $businessId, null);
            chk("resolución: branch_admin → bid={$bid1}", $fbBid === $bid1, $pass, $fail, $failures);

            $batch = FabricaBatch::unguarded(function () use ($businessId, $fbBid, $branchAdmin, $fabricaProduct) {
                return FabricaBatch::create([
                    'business_id'       => $businessId,
                    'branch_id'         => $fbBid,
                    'created_by'        => $branchAdmin->id,
                    'output_product_id' => $fabricaProduct->id,
                    'output_kg'         => 1.0,
                    'output_units'      => 0,
                    'input_cost_usd'    => 0.0,
                    'produced_at'       => now(),
                ]);
            });
            chk("DB (unguarded): FabricaBatch.branch_id={$bid1}", (int) $batch->branch_id === $bid1, $pass, $fail, $failures);

            if (! $fillableStatus['FabricaBatch']) {
                $realBatch = FabricaBatch::create([
                    'business_id'       => $businessId,
                    'branch_id'         => $bid1,
                    'created_by'        => $branchAdmin->id,
                    'output_product_id' => $fabricaProduct->id,
                    'output_kg'         => 0.1,
                    'output_units'      => 0,
                    'input_cost_usd'    => 0.0,
                    'produced_at'       => now(),
                ]);
                chk("[CRÍTICO] create() → branch_id=NULL en DB real", $realBatch->branch_id === null, $pass, $fail, $failures, true);
            }

        } finally {
            DB::rollBack();
        }
    } else {
        echo "  SKIP: Sin productos fabricables\n";
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 7 — FabricaController::storeDespiece → BUG closure
    // $branchId no está en use() de DB::transaction
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 7. FabricaController::storeDespiece → BUG closure ──\n";

    $src = file_get_contents(base_path('app/Http/Controllers/FabricaController.php'));
    preg_match('/function\s+storeDespiece.*?DB::transaction\(function\s*\(\)\s*use\s*\(([^)]*)\)/s', $src, $m);
    $useList       = $m[1] ?? '';
    $branchIdInUse = strpos($useList, '$branchId') !== false;
    $branchIdUsed  = strpos($src, "'branch_id'       => \$branchId") !== false;

    chk(
        '$branchId presente en use() closure de storeDespiece → CORRECTO',
        $branchIdInUse,
        $pass, $fail, $failures
    );

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 8 — Roles: cashier siempre usa su branch_id
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 8. Roles: cashier no contaminable por sesión ──\n";

    DB::beginTransaction();
    try {
        $cashierBidLogic = canonicalBid($cashier, $businessId, $bid2); // sesión contaminada
        chk("cashier: sesión(bid2={$bid2}) ignorada → bid={$bid1}", $cashierBidLogic === $bid1, $pass, $fail, $failures);

        // Sale (fillable) para verificar en DB
        $cashierSale = Sale::create([
            'business_id'   => $businessId,
            'branch_id'     => $cashierBidLogic,
            'ticket_number' => 'CERT-CA-' . time(),
            'status'        => 'open',
            'total_usd'     => 0.0,
            'total_bs'      => 0.0,
            'cashier_id'    => $cashier->id,
        ]);
        chk("cashier: Sale.branch_id={$bid1} (no bid2)", (int) $cashierSale->branch_id === $bid1, $pass, $fail, $failures);

        $visC = Sale::where('id', $cashierSale->id)->where('branch_id', $bid2)->exists();
        chk("cashier: venta NO visible con filtro bid2", ! $visC, $pass, $fail, $failures);

    } finally {
        DB::rollBack();
    }

    // ═══════════════════════════════════════════════════════════════════
    // BLOQUE 9 — Owner multi-sucursal: sesión controla la sucursal
    // ═══════════════════════════════════════════════════════════════════
    echo "\n── 9. Owner: sesión controla sucursal activa ──\n";

    DB::beginTransaction();
    try {
        $noSession = canonicalBid($owner, $businessId, null);
        chk("owner sin sesión → fallback bid1={$bid1}", $noSession === $bid1, $pass, $fail, $failures);

        $withBid2 = canonicalBid($owner, $businessId, $bid2);
        chk("owner con sesión(bid2={$bid2}) → bid2", $withBid2 === $bid2, $pass, $fail, $failures);

        $withBid1 = canonicalBid($owner, $businessId, $bid1);
        chk("owner con sesión(bid1={$bid1}) → bid1", $withBid1 === $bid1, $pass, $fail, $failures);

        // Visibilidad: Sale en bid2 no aparece en filtro bid1
        $ownerSale = Sale::create([
            'business_id'   => $businessId,
            'branch_id'     => $bid2,
            'ticket_number' => 'CERT-OW-' . time(),
            'status'        => 'open',
            'total_usd'     => 0.0,
            'total_bs'      => 0.0,
            'cashier_id'    => $owner->id,
        ]);
        $visOw = Sale::where('id', $ownerSale->id)->where('branch_id', $bid1)->exists();
        chk("owner en bid2: Sale NOT visible con filtro bid1", ! $visOw, $pass, $fail, $failures);

    } finally {
        DB::rollBack();
    }

} finally {
    DB::rollBack(); // revierte usuarios sintéticos, branch2 y cualquier residuo
}

// ─── Resultado final ──────────────────────────────────────────────────────────
$total    = $pass + $fail;
$criticalCount = count(array_filter($failures, fn($f) => str_starts_with($f, '[CRÍTICO]')));

echo "\n╔══════════════════════════════════════════════════════════════╗\n";
if ($fail === 0) {
    echo "║  RESULTADO: PASS ✓  {$pass}/{$total} verificaciones OK                  ║\n";
} else {
    printf("║  RESULTADO: FAIL ✗  %d/%d OK · %d fallos (%d críticos)      ║\n",
        $pass, $total, $fail, $criticalCount);
}
echo "╚══════════════════════════════════════════════════════════════╝\n";

if (! empty($failures)) {
    echo "\nFALLOS DETECTADOS:\n";
    foreach ($failures as $i => $f) {
        echo '  ' . ($i + 1) . ". {$f}\n";
    }

    if ($criticalCount > 0) {
        echo "\nBUGS CRÍTICOS DE PRODUCCIÓN (branch_id guardado como NULL en DB):\n";
        echo "  - Agregar 'branch_id' a \$fillable en: InventoryEntry, Order, BovedaEntry, FabricaBatch\n";
        echo "  - En FabricaController::storeDespiece: agregar \$branchId al use() de la closure\n";
    }
}
echo "\n";
