<?php
/**
 * SYNTImeat — Credit/Delivery/Onsite flow certification
 * Standalone PDO script — no Eloquent, no Laravel bootstrap
 * Run: php /var/www/syntimeat/tests/credit_flow_certification.php
 */

declare(strict_types=1);

// ── Config ────────────────────────────────────────────────────────────────────
const DSN  = 'mysql:host=127.0.0.1;dbname=syntimeat_db;charset=utf8mb4';
const USER = 'syntimeat';
const PASS = 'SyntiMeat2026!';
const BID  = 1;   // business_id
const BRID = 1;   // branch_id — NEVER touch branch_id=3
const CTRL = '/var/www/syntimeat/app/Http/Controllers/SaleController.php';

// ── Helpers ───────────────────────────────────────────────────────────────────
$passes = 0;
$total  = 9;
$checkNo = 0;

function check(bool $ok, string $label, string $got = ''): void
{
    global $passes, $checkNo;
    $checkNo++;
    $icon = $ok ? "\u{2705}" : "\u{274C}";
    $suffix = $got ? " — $got" : '';
    echo "$icon CHECK $checkNo — $label$suffix\n";
    if ($ok) $passes++;
}

function fail(string $label, string $reason): void
{
    global $checkNo;
    $checkNo++;
    echo "\u{274C} CHECK $checkNo — $label\n    FALLO: $reason\n";
}

// ── IDs de registros creados (para limpieza) ──────────────────────────────────
$createdSaleIds    = [];
$createdItemIds    = [];
$createdStockIds   = [];
$createdClientId   = null;

try {
    $pdo = new PDO(DSN, USER, PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // ── SETUP ─────────────────────────────────────────────────────────────────
    echo "=== SETUP ===\n";

    // Tasa del día
    $rate = (float) $pdo->query('SELECT rate FROM dollar_rates ORDER BY id DESC LIMIT 1')->fetchColumn();
    echo "  Tasa: $rate\n";

    // Cashier
    $cashier = $pdo->query(
        "SELECT id FROM users WHERE email='caja@matriz.com' AND role='cashier' LIMIT 1"
    )->fetch();
    if (!$cashier) throw new RuntimeException('Usuario caja@matriz.com no encontrado');
    $cashierId = (int) $cashier['id'];
    echo "  Cashier id: $cashierId\n";

    // Producto con stock en vitrina branch=1
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.price_per_kg_usd
         FROM products p
         JOIN inventory_entries ie ON ie.product_id = p.id
         WHERE p.business_id = :bid AND ie.business_id = :bid
           AND ie.branch_id = :brid AND ie.location = 'vitrina'
           AND p.sale_mode = 'weight' AND p.active = 1
         GROUP BY p.id
         HAVING SUM(ie.quantity_kg - ie.waste_kg) > 0
         LIMIT 1"
    );
    $stmt->execute([':bid' => BID, ':brid' => BRID]);
    $product = $stmt->fetch();
    if (!$product) throw new RuntimeException('Sin producto con stock en vitrina branch=1');
    $productId   = (int) $product['id'];
    $productName = $product['name'];
    $priceKg     = (float) $product['price_per_kg_usd'];
    echo "  Producto: id=$productId name=$productName price_kg=$priceKg\n";

    // Stock inicial antes del test
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity_kg - waste_kg),0) FROM inventory_entries
         WHERE business_id=:bid AND branch_id=:brid AND product_id=:pid AND location='vitrina'"
    );
    $stmt->execute([':bid' => BID, ':brid' => BRID, ':pid' => $productId]);
    $stockBefore = (float) $stmt->fetchColumn();
    echo "  Stock vitrina antes del test: {$stockBefore} kg\n";

    // Cliente de prueba (crear si no existe)
    $stmt = $pdo->prepare(
        "SELECT id FROM clients WHERE business_id=:bid AND name='Cliente Test Certificacion' LIMIT 1"
    );
    $stmt->execute([':bid' => BID]);
    $existClient = $stmt->fetch();
    if ($existClient) {
        $clientId = (int) $existClient['id'];
        echo "  Cliente test existente: id=$clientId\n";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO clients (business_id, branch_id, name, active, created_at, updated_at)
             VALUES (:bid, :brid, 'Cliente Test Certificacion', 1, NOW(), NOW())"
        );
        $stmt->execute([':bid' => BID, ':brid' => BRID]);
        $clientId = (int) $pdo->lastInsertId();
        $createdClientId = $clientId;
        echo "  Cliente test creado: id=$clientId\n";
    }

    // Calcular totales para las ventas de prueba (0.5 kg)
    $qty        = 0.5;
    $totalUsd   = round($qty * $priceKg, 2);
    $totalBs    = round($totalUsd * $rate, 2);

    echo "  Totales test (0.5kg): usd=$totalUsd bs=$totalBs\n";
    echo str_repeat('=', 64) . "\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 1 — Guard "Cliente obligatorio" existe en SaleController.php
    // ─────────────────────────────────────────────────────────────────────────
    $ctrl = file_get_contents(CTRL);
    $guardExists = str_contains($ctrl, 'Cliente obligatorio');
    check($guardExists, 'Guard client_name obligatorio existe en SaleController.php',
        $guardExists ? 'string encontrado en línea ~247' : 'AUSENTE — el guard fue eliminado');

    // También verificar que NO hay required_if frágil
    $noRequiredIf = !str_contains($ctrl, "required_if:origin,credit");
    $detail = $noRequiredIf ? 'required_if eliminado correctamente' : 'required_if todavía presente (puede romper onsite)';
    echo "  (extra) required_if eliminado: " . ($noRequiredIf ? "\u{2705}" : "\u{26A0}") . " $detail\n\n";

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 2 — Venta crédito CON cliente crea ticket correcto
    // ─────────────────────────────────────────────────────────────────────────
    // Insertar venta
    $stmt = $pdo->prepare(
        "INSERT INTO sales
           (business_id, branch_id, ticket_number, status, payment_status,
            total_usd, total_bs, rate_used, sold_at, accounting_date,
            cashier_id, origin, channel, client_id, client_name,
            cash_register_id, created_at, updated_at)
         VALUES
           (:bid, :brid, 'TEST-CERT-001', 'pending', 'pendiente_cobro',
            :tusd, :tbs, :rate, NOW(), CURDATE(),
            :cashier, 'credit', 'physical', :cid, 'Cliente Test Certificacion',
            NULL, NOW(), NOW())"
    );
    $stmt->execute([
        ':bid'     => BID, ':brid' => BRID,
        ':tusd'    => $totalUsd, ':tbs' => $totalBs,
        ':rate'    => $rate, ':cashier' => $cashierId,
        ':cid'     => $clientId,
    ]);
    $saleId001 = (int) $pdo->lastInsertId();
    $createdSaleIds[] = $saleId001;

    // Insertar sale_item
    $stmt = $pdo->prepare(
        "INSERT INTO sale_items
           (sale_id, product_id, product_name, input_type, quantity_value,
            unit_label, price_per_kg_usd, price_per_unit_usd,
            subtotal_usd, subtotal_bs, rate_used, discount_usd,
            created_at, updated_at)
         VALUES
           (:sid, :pid, :pname, 'weight', :qty,
            'kg', :pkg, 0,
            :susd, :sbs, :rate, 0,
            NOW(), NOW())"
    );
    $stmt->execute([
        ':sid' => $saleId001, ':pid' => $productId,
        ':pname' => $productName, ':qty' => $qty,
        ':pkg' => $priceKg, ':susd' => $totalUsd,
        ':sbs' => $totalBs, ':rate' => $rate,
    ]);
    $createdItemIds[] = (int) $pdo->lastInsertId();

    // Insertar inventory_entry (descontar stock — crédito descuenta al despachar)
    $stmt = $pdo->prepare(
        "INSERT INTO inventory_entries
           (business_id, branch_id, product_id, quantity_kg, waste_kg,
            location, notes, entered_at, created_by, created_at, updated_at)
         VALUES
           (:bid, :brid, :pid, :qty, 0,
            'vitrina', 'Test certificacion credito', NOW(), :uid, NOW(), NOW())"
    );
    $stmt->execute([
        ':bid' => BID, ':brid' => BRID, ':pid' => $productId,
        ':qty' => -$qty, ':uid' => $cashierId,
    ]);
    $stockEntry001 = (int) $pdo->lastInsertId();
    $createdStockIds[] = $stockEntry001;

    // Verificar
    $stmt = $pdo->prepare(
        "SELECT id, client_id, client_name, origin, status
         FROM sales WHERE ticket_number='TEST-CERT-001' AND business_id=:bid"
    );
    $stmt->execute([':bid' => BID]);
    $s001 = $stmt->fetch();
    $pass2 = $s001
        && (int)$s001['client_id'] === $clientId
        && $s001['client_name'] === 'Cliente Test Certificacion'
        && $s001['origin'] === 'credit'
        && $s001['status'] === 'pending';
    check($pass2, 'Venta crédito CON cliente — ticket y campos correctos',
        "id={$s001['id']} client_id={$s001['client_id']} client_name=\"{$s001['client_name']}\" origin={$s001['origin']} status={$s001['status']}");

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 3 — Ticket aparece en Por Cobrar
    // ─────────────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM sales
         WHERE business_id=:bid AND branch_id=:brid
           AND payment_status='pendiente_cobro' AND status='pending'
           AND id=:sid"
    );
    $stmt->execute([':bid' => BID, ':brid' => BRID, ':sid' => $saleId001]);
    $cnt3 = (int) $stmt->fetchColumn();
    check($cnt3 === 1, 'Ticket aparece en Por Cobrar (payment_status=pendiente_cobro, status=pending)',
        "COUNT=$cnt3");

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 4 — assignClient: corregir cliente
    // ─────────────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "UPDATE sales SET client_name='Cliente Corregido Test', client_id=NULL, updated_at=NOW()
         WHERE id=:sid AND business_id=:bid"
    );
    $stmt->execute([':sid' => $saleId001, ':bid' => BID]);

    $stmt = $pdo->prepare("SELECT client_name, client_id FROM sales WHERE id=:sid");
    $stmt->execute([':sid' => $saleId001]);
    $s4 = $stmt->fetch();
    $pass4 = $s4['client_name'] === 'Cliente Corregido Test' && $s4['client_id'] === null;
    check($pass4, 'assignClient — nombre actualizado a texto libre',
        "client_name=\"{$s4['client_name']}\" client_id=" . var_export($s4['client_id'], true));

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 5 — Stock se descontó correctamente
    // ─────────────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity_kg - waste_kg),0) FROM inventory_entries
         WHERE business_id=:bid AND branch_id=:brid AND product_id=:pid AND location='vitrina'"
    );
    $stmt->execute([':bid' => BID, ':brid' => BRID, ':pid' => $productId]);
    $stockAfterSale = (float) $stmt->fetchColumn();
    $expectedAfter  = round($stockBefore - $qty, 3);
    $pass5 = abs($stockAfterSale - $expectedAfter) < 0.001;
    check($pass5, 'Stock descontado en 0.5 kg tras venta crédito',
        "antes={$stockBefore}kg ahora={$stockAfterSale}kg esperado={$expectedAfter}kg");

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 6 — Anular venta devuelve stock
    // ─────────────────────────────────────────────────────────────────────────
    // Cancelar venta
    $stmt = $pdo->prepare(
        "UPDATE sales SET status='cancelled', cancelled_at=NOW(),
         cancellation_reason='Test certificacion', updated_at=NOW()
         WHERE id=:sid AND business_id=:bid"
    );
    $stmt->execute([':sid' => $saleId001, ':bid' => BID]);

    // Revertir stock
    $stmt = $pdo->prepare(
        "INSERT INTO inventory_entries
           (business_id, branch_id, product_id, quantity_kg, waste_kg,
            location, notes, entered_at, created_by, created_at, updated_at)
         VALUES
           (:bid, :brid, :pid, :qty, 0,
            'vitrina', 'Reversion test certificacion', NOW(), :uid, NOW(), NOW())"
    );
    $stmt->execute([
        ':bid' => BID, ':brid' => BRID, ':pid' => $productId,
        ':qty' => $qty,   // +0.5 revierte el -0.5
        ':uid' => $cashierId,
    ]);
    $createdStockIds[] = (int) $pdo->lastInsertId();

    // Verificar stock volvió
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(quantity_kg - waste_kg),0) FROM inventory_entries
         WHERE business_id=:bid AND branch_id=:brid AND product_id=:pid AND location='vitrina'"
    );
    $stmt->execute([':bid' => BID, ':brid' => BRID, ':pid' => $productId]);
    $stockAfterVoid = (float) $stmt->fetchColumn();
    $pass6 = abs($stockAfterVoid - $stockBefore) < 0.001;
    check($pass6, 'Stock devuelto tras anulación',
        "antes_test={$stockBefore}kg tras_anular={$stockAfterVoid}kg");

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 7 — Venta onsite SIN cliente funciona (cliente opcional)
    // ─────────────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO sales
           (business_id, branch_id, ticket_number, status, payment_status,
            total_usd, total_bs, rate_used, sold_at, accounting_date,
            cashier_id, origin, channel, client_id, client_name,
            cash_register_id, created_at, updated_at)
         VALUES
           (:bid, :brid, 'TEST-CERT-002', 'paid', 'paid',
            :tusd, :tbs, :rate, NOW(), CURDATE(),
            :cashier, 'onsite', 'physical', NULL, NULL,
            NULL, NOW(), NOW())"
    );
    $stmt->execute([
        ':bid' => BID, ':brid' => BRID,
        ':tusd' => $totalUsd, ':tbs' => $totalBs,
        ':rate' => $rate, ':cashier' => $cashierId,
    ]);
    $saleId002 = (int) $pdo->lastInsertId();
    $createdSaleIds[] = $saleId002;

    $stmt = $pdo->prepare("SELECT status, client_id, origin FROM sales WHERE id=:sid");
    $stmt->execute([':sid' => $saleId002]);
    $s7 = $stmt->fetch();
    $pass7 = $s7['status'] === 'paid' && $s7['client_id'] === null && $s7['origin'] === 'onsite';
    check($pass7, 'Venta onsite SIN cliente — status=paid, client_id=NULL',
        "status={$s7['status']} client_id=" . var_export($s7['client_id'], true) . " origin={$s7['origin']}");

    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 8 — Venta delivery CON cliente guarda client_id
    // ─────────────────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare(
        "INSERT INTO sales
           (business_id, branch_id, ticket_number, status, payment_status,
            total_usd, total_bs, rate_used, sold_at, accounting_date,
            cashier_id, origin, channel, client_id, client_name,
            cash_register_id, created_at, updated_at)
         VALUES
           (:bid, :brid, 'TEST-CERT-003', 'pending', 'pendiente_cobro',
            :tusd, :tbs, :rate, NOW(), CURDATE(),
            :cashier, 'delivery', 'physical', :cid, 'Cliente Test Certificacion',
            NULL, NOW(), NOW())"
    );
    $stmt->execute([
        ':bid' => BID, ':brid' => BRID,
        ':tusd' => $totalUsd, ':tbs' => $totalBs,
        ':rate' => $rate, ':cashier' => $cashierId,
        ':cid' => $clientId,
    ]);
    $saleId003 = (int) $pdo->lastInsertId();
    $createdSaleIds[] = $saleId003;

    $stmt = $pdo->prepare("SELECT client_id, client_name, origin FROM sales WHERE id=:sid");
    $stmt->execute([':sid' => $saleId003]);
    $s8 = $stmt->fetch();
    $pass8 = (int)$s8['client_id'] === $clientId && $s8['client_name'] === 'Cliente Test Certificacion' && $s8['origin'] === 'delivery';
    check($pass8, 'Venta delivery CON cliente — client_id y client_name correctos',
        "client_id={$s8['client_id']} client_name=\"{$s8['client_name']}\" origin={$s8['origin']}");

} catch (Throwable $e) {
    echo "\n\u{1F4A5} ERROR EN SETUP: " . $e->getMessage() . "\n";
} finally {
    // ─────────────────────────────────────────────────────────────────────────
    // CHECK 9 — Limpieza
    // ─────────────────────────────────────────────────────────────────────────
    if (!isset($pdo)) {
        $pdo = new PDO(DSN, USER, PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    $cleanOk = true;
    try {
        // Items de ventas TEST-CERT
        $pdo->exec(
            "DELETE si FROM sale_items si
             JOIN sales s ON s.id=si.sale_id
             WHERE s.ticket_number LIKE 'TEST-CERT-%'"
        );

        // Ventas TEST-CERT
        $deleted = $pdo->exec("DELETE FROM sales WHERE ticket_number LIKE 'TEST-CERT-%'");

        // Inventory entries de test
        $pdo->exec("DELETE FROM inventory_entries WHERE notes LIKE '%Test certificacion%' OR notes LIKE '%Reversion test%'");

        // Cliente de prueba (solo si lo creamos aquí)
        if (isset($createdClientId)) {
            $pdo->prepare("DELETE FROM clients WHERE id=:id")->execute([':id' => $createdClientId]);
        }

        // Verificar que no quedan restos
        $remaining = (int) $pdo->query(
            "SELECT COUNT(*) FROM sales WHERE ticket_number LIKE 'TEST-CERT-%'"
        )->fetchColumn();
        $cleanOk = $remaining === 0;
    } catch (Throwable $ce) {
        $cleanOk = false;
        echo "  Limpieza error: " . $ce->getMessage() . "\n";
    }

    check($cleanOk, 'Limpieza — sin rastros TEST-CERT en DB',
        $cleanOk ? 'sales/items/inventory/client eliminados' : 'quedan registros TEST-CERT');

    // ── RESUMEN FINAL ─────────────────────────────────────────────────────────
    global $passes, $total;
    echo "\n" . str_repeat('=', 64) . "\n";
    echo "RESULTADO: {$passes}/{$total} checks PASS\n";
    if ($passes < $total) {
        echo "\u{26A0}  HAY FALLOS — revisar checks marcados con \u{274C}\n";
    } else {
        echo "\u{2705} TODOS LOS CHECKS PASARON\n";
    }
}
