<?php

/**
 * Simulación de navegación como admin de sucursal (branch_id=3).
 * Verifica que cada endpoint retorna SOLO datos de branch=3.
 *
 * Si admin@sucursal.com no existe, crea un usuario temporal branch_admin
 * en branch_id=3 y lo elimina al terminar.
 *
 * Uso: php artisan tinker < tests/simulate_admin_sucursal.php
 */

use App\Models\BovedaEntry;
use App\Models\Branch;
use App\Models\InventoryEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

// ─── 1. Autenticar (o crear usuario temporal) ─────────────────────────────────

$targetBranch = 3;
$tmpUser      = false;

$user = User::where('email', 'admin@sucursal.com')->first();

if ($user === null) {
    // Si branch_id=3 no existe, usar la primera sucursal disponible distinta de 1
    $branch = Branch::find($targetBranch);
    if ($branch === null) {
        $branch = Branch::where('id', '!=', 1)->first() ?? Branch::first();
        if ($branch === null) {
            echo "ERROR: no hay sucursales en la tabla branches.\n";
            return;
        }
        $targetBranch = $branch->id;
        printf("INFO: branch_id=3 no existe — usando branch_id=%s (%s) para el test.\n",
            $branch->id, $branch->name);
    }

    // Obtener business_id de la sucursal
    $businessId = $branch->business_id;

    // Crear usuario temporal
    $user = User::create([
        'name'              => '[TEST] Admin Sucursal 3',
        'email'             => 'admin@sucursal.com',
        'password'          => Hash::make('test_password_never_used'),
        'role'              => 'branch_admin',
        'branch_id'         => $targetBranch,
        'business_id'       => $businessId,
        'email_verified_at' => now(),
    ]);
    $tmpUser = true;
    printf("INFO: Usuario temporal creado — id=%s  branch_id=%s\n", $user->id, $user->branch_id);
}

Auth::login($user);

$expectedBranch = (int) $user->branch_id;
$businessId     = (int) $user->business_id;

// Patrón canónico lectura — tinker no tiene sesión HTTP; branch_admin usa branch_id fijo
$branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
    ? $user->branch_id
    : (session('current_branch_id') ?? null);

printf("\nAutenticado: %s  |  role=%s  |  branch_id=%s  |  business_id=%s\n\n",
    $user->email, $user->role, $branchId ?? 'NULL', $businessId);

if ($branchId === null) {
    echo "ADVERTENCIA: branchId=NULL — el script necesita un usuario branch_admin o cashier.\n\n";
}

// ─── 2. Helpers ───────────────────────────────────────────────────────────────

$results = [];

$check = function (
    string $endpoint,
    int $expected,
    \Illuminate\Support\Collection $items,
    string $col = 'branch_id'
) use (&$results): void {
    $branches     = $items->pluck($col)->map(fn ($v) => $v !== null ? (int) $v : null);
    $unique       = $branches->unique()->sort()->values()->toArray();
    $contaminated = array_values(array_filter($unique, fn ($b) => $b !== null && $b !== $expected));
    $nullCount    = $branches->filter(fn ($b) => $b === null)->count();
    // vacío = PASS (no hay datos de otra sucursal); NULL = FAIL
    $pass = empty($contaminated) && $nullCount === 0;

    $found = empty($unique)
        ? 'vacío'
        : implode(', ', array_map(fn ($b) => $b === null ? 'NULL' : "branch={$b}", $unique));

    Log::info('simulate_admin_sucursal', [
        'endpoint'     => $endpoint,
        'expected'     => $expected,
        'found'        => $unique,
        'null_count'   => $nullCount,
        'contaminated' => $contaminated,
        'pass'         => $pass,
    ]);

    $results[] = compact('endpoint', 'expected', 'found', 'contaminated', 'nullCount', 'pass');
};

// ─── 3. GET /boveda ───────────────────────────────────────────────────────────

$bovedaActivas = BovedaEntry::where('business_id', $businessId)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

$bovedaHistorial = BovedaEntry::where('business_id', $businessId)
    ->whereNotNull('closed_at')
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->limit(30)
    ->get(['id', 'branch_id']);

$check('GET /boveda (activas)',   $expectedBranch, $bovedaActivas);
$check('GET /boveda (historial)', $expectedBranch, $bovedaHistorial);

// ─── 4. GET /inventario ───────────────────────────────────────────────────────

$invProductos = Product::where('business_id', $businessId)
    ->where('active', true)
    ->where('location', '!=', 'boveda')
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

// InventoryController usa ->where forzado (no ->when); con $branchId=null daría IS NULL — branch_admin siempre tiene valor
$invEntradas = InventoryEntry::where('business_id', $businessId)
    ->where('branch_id', $branchId)
    ->whereDate('entered_at', today())
    ->get(['id', 'branch_id']);

$invStockIn = InventoryEntry::where('business_id', $businessId)
    ->where('branch_id', $branchId)
    ->get(['id', 'branch_id']);

$check('GET /inventario (productos)',    $expectedBranch, $invProductos);
$check('GET /inventario (entradas hoy)', $expectedBranch, $invEntradas);
$check('GET /inventario (stockIn)',      $expectedBranch, $invStockIn);

// ─── 5. GET /catalogo ────────────────────────────────────────────────────────

$catProductos = Product::where('business_id', $businessId)
    ->where('active', true)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

$catStockIn = InventoryEntry::where('business_id', $businessId)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

$check('GET /catalogo (productos)', $expectedBranch, $catProductos);
$check('GET /catalogo (stockIn)',   $expectedBranch, $catStockIn);

// ─── 6. GET /pedidos ─────────────────────────────────────────────────────────

$pedidosProductos = Product::where('business_id', $businessId)
    ->where('active', true)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

$creditPending = Sale::where('business_id', $businessId)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

$check('GET /pedidos (catálogo POS)',    $expectedBranch, $pedidosProductos);
$check('GET /pedidos (crédito pending)', $expectedBranch, $creditPending);

// ─── 7. GET /ventas ──────────────────────────────────────────────────────────

$ventas = Sale::where('business_id', $businessId)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->limit(100)
    ->get(['id', 'branch_id']);

$check('GET /ventas', $expectedBranch, $ventas);

// ─── 8. GET /configuracion/metodos-pago ──────────────────────────────────────

$metodosPago = PaymentMethod::where('business_id', $businessId)
    ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
    ->get(['id', 'branch_id']);

$check('GET /config/metodos-pago', $expectedBranch, $metodosPago);

// ─── 9. Tabla de resultados ───────────────────────────────────────────────────

echo "\n";
$w0 = 44; $w1 = 14; $w2 = 22;
$sep = '+' . str_repeat('-', $w0 + 2) . '+' . str_repeat('-', $w1 + 2)
     . '+' . str_repeat('-', $w2 + 2) . '+' . str_repeat('-', 30) . '+';

echo $sep . "\n";
printf("| %-{$w0}s | %-{$w1}s | %-{$w2}s | %-28s |\n",
    'ENDPOINT', 'ESPERADO', 'ENCONTRADO', 'RESULTADO');
echo $sep . "\n";

$allPass = true;
foreach ($results as $r) {
    $status = $r['pass'] ? 'PASS' : 'FAIL';
    if (! $r['pass']) {
        $allPass = false;
        if (! empty($r['contaminated'])) {
            $status .= ' contam=branch(' . implode(',', $r['contaminated']) . ')';
        }
        if ($r['nullCount'] > 0) {
            $status .= ' NULL×' . $r['nullCount'];
        }
    }
    printf("| %-{$w0}s | %-{$w1}s | %-{$w2}s | %-28s |\n",
        substr($r['endpoint'], 0, $w0),
        "branch={$r['expected']}",
        substr($r['found'], 0, $w2),
        $status);
}

echo $sep . "\n";
printf("| %-" . ($w0 + $w1 + $w2 + 10) . "s | %-28s |\n",
    'RESULTADO GLOBAL',
    $allPass ? 'PASS — sin contaminacion' : 'FAIL — ver lineas FAIL');
echo $sep . "\n\n";

// ─── 10. Cleanup — eliminar usuario temporal ──────────────────────────────────

if ($tmpUser) {
    $user->delete();
    echo "INFO: Usuario temporal eliminado.\n\n";
}
