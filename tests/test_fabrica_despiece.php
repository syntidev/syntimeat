<?php
// Test: Flujo completo Bóveda → Fábrica para RES, CERDO y POLLO
require '/var/www/syntimeat/vendor/autoload.php';
$app = require '/var/www/syntimeat/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\BovedaProduct;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

$businessId = 1;
$branchId   = 3;
$pass = 0; $fail = 0;

function check($label, $condition) {
    global $pass, $fail;
    if ($condition) { echo "  ✅ $label\n"; $pass++; }
    else            { echo "  ❌ $label\n"; $fail++; }
}

// ─── CATMAP ───────────────────────────────────────────────────────────────────
$catMap = [
    'RES - Medio Canal' => 'Res',
    'CERDO - Canal'     => 'Cerdo',
];

foreach (['RES - Medio Canal', 'CERDO - Canal'] as $bp) {
    $prod = BovedaProduct::where('business_id', $businessId)->where('name', $bp)->first();
    check("BovedaProduct '{$bp}' existe", $prod !== null);
    check("BovedaProduct '{$bp}' requires_despiece=1", (bool)($prod?->requires_despiece));
}

// ─── POLLO: detectar por nombre ───────────────────────────────────────────────
echo "\n--- POLLO boveda_products ---\n";
$pollos = BovedaProduct::where('business_id', $businessId)
    ->get()->filter(fn($p) => str_contains(strtolower($p->name), 'pollo'));
check("Al menos 1 boveda_product con 'pollo' en nombre", $pollos->count() > 0);
foreach ($pollos as $p) {
    $catName = str_contains(strtolower($p->name), 'pollo') ? 'Pollo' : null;
    check("  '{$p->name}' → catName=Pollo detectado", $catName === 'Pollo');
}

// ─── PRODUCTOS VITRINA POR CATEGORÍA ─────────────────────────────────────────
$resOrder   = ['Carne Total', 'Costilla', 'Hueso Redondo', 'Hueso Rojo'];
$polloOrder = ['Pollo Picado', 'Muslo', 'Pechuga', 'Alas de Pollo', 'Molleja', 'Pedrero'];

echo "\n--- RES vitrina branch=$branchId ---\n";
foreach ($resOrder as $name) {
    $p = Product::where('business_id', $businessId)->where('branch_id', $branchId)
        ->where('location','vitrina')->where('name', $name)->where('active',1)->first();
    check("  '$name' existe en vitrina", $p !== null);
    check("  '$name' fabricable=0", ($p?->fabricable == 0));
}

echo "\n--- POLLO vitrina branch=$branchId ---\n";
foreach ($polloOrder as $name) {
    $p = Product::where('business_id', $businessId)->where('branch_id', $branchId)
        ->where('location','vitrina')->where('name', $name)->where('active',1)->first();
    check("  '$name' existe en vitrina", $p !== null);
    check("  '$name' fabricable=0", ($p?->fabricable == 0));
}

echo "\n--- CERDO vitrina branch=$branchId ---\n";
$cerdo = Product::where('business_id', $businessId)->where('branch_id', $branchId)
    ->where('location','vitrina')->where('active',1)
    ->whereHas('category', fn($q) => $q->where('name','Cerdo'))
    ->where('fabricable',0)->get();
check("Al menos 1 producto Cerdo en vitrina", $cerdo->count() > 0);
foreach ($cerdo as $p) {
    echo "    → id={$p->id} {$p->name}\n";
}

// ─── PIVOTE CARNE TOTAL ───────────────────────────────────────────────────────
echo "\n--- Pivote Carne Total ---\n";
$pivote = Product::where('business_id',$businessId)->where('branch_id',$branchId)
    ->where('name','Carne Total')->where('active',1)->first();
check("Carne Total branch=3 activo", $pivote !== null);

$pivotados = Product::where('stock_product_id', $pivote?->id)->where('branch_id',$branchId)->get();
check("Premium/Primera/Segunda apuntan a pivote branch=3", $pivotados->count() >= 3);
foreach ($pivotados as $p) echo "    → {$p->name} stock_product_id={$p->stock_product_id}\n";

// ─── RESUMEN ─────────────────────────────────────────────────────────────────
echo "\n=============================\n";
echo "PASS: $pass | FAIL: $fail\n";
echo ($fail === 0 ? "✅ TODO OK\n" : "⚠️  HAY FALLOS\n");
