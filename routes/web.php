<?php

declare(strict_types=1);

use App\Http\Controllers\BovedaController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ContingencyController;
use App\Http\Controllers\DespieceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\FabricaController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ─── Onboarding (no auth required) ───────────────────────────────────────────
Route::get('/setup', [OnboardingController::class, 'show'])->name('onboarding');
Route::post('/setup/{step}', [OnboardingController::class, 'store'])
    ->whereNumber('step')
    ->name('onboarding.step');

// ─── Public ───────────────────────────────────────────────────────────────────
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

// ─── Authenticated + onboarding complete ─────────────────────────────────────
Route::middleware(['auth', 'verified', 'check.onboarding'])->group(function () {

    // ── Perfil (todos los roles) ──────────────────────────────────────────────
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Todos los roles (super_admin, admin, cashier) ─────────────────────────
    Route::middleware('role:super_admin,admin,cashier')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');

        // Cierre del Día — vista accesible también por cashier
        Route::get('/caja/cierre', [CashRegisterController::class, 'dayClose'])->name('cash.day-close');

        // POS
        Route::get('/pos', [SaleController::class, 'index'])->name('pos.index');
        Route::post('/pos/ventas', [SaleController::class, 'store'])->name('sales.store');
        Route::patch('/pos/ventas/{sale}/pagar', [SaleController::class, 'pay'])->name('sales.pay');
        Route::patch('/pos/ventas/{sale}/cancelar', [SaleController::class, 'cancel'])->name('sales.cancel');

        // Ventas del Día (solo lectura para cashier)
        Route::get('/ventas', [SaleController::class, 'historial'])->name('sales.index');

        // Caja
        Route::get('/caja', [CashRegisterController::class, 'index'])->name('cash.index');
        Route::post('/caja/abrir', [CashRegisterController::class, 'open'])->name('cash.open');
        Route::post('/caja/{register}/cerrar', [CashRegisterController::class, 'close'])->name('cash.close');
        Route::post('/caja/{register}/movimiento', [CashRegisterController::class, 'movement'])->name('cash.movement');

        // Clientes
        Route::get('/clientes/buscar', [ClientController::class, 'search'])->name('clients.search');
        Route::get('/clientes', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clientes', [ClientController::class, 'store'])->name('clients.store');
        Route::get('/clientes/{client}', [ClientController::class, 'show'])->name('clients.show');
        Route::put('/clientes/{client}', [ClientController::class, 'update'])->name('clients.update');

        // Pedidos
        Route::get('/pedidos', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/pedidos/delivery', [OrderController::class, 'deliveryIndex'])->name('orders.delivery');
        Route::post('/pedidos', [OrderController::class, 'store'])->name('orders.store');
        Route::patch('/pedidos/{order}/cobrar', [OrderController::class, 'collect'])->name('orders.collect');
        Route::patch('/pedidos/{order}/despachar', [OrderController::class, 'dispatch'])->name('orders.dispatch');
        Route::patch('/pedidos/{order}/cancelar', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::patch('/pedidos/{sale}/delivery-cobrado', [OrderController::class, 'confirmDelivery'])->name('sales.delivery-confirm');
        Route::patch('/ventas/{sale}/cobrar-pendiente', [OrderController::class, 'collectPending'])->name('sales.collect-pending');
    });

    // ── Panel Empresarial — solo super_admin / owner (vista multi-sucursal) ───
    Route::middleware('role:super_admin,owner')->group(function () {
        Route::get('/reportes/consolidado', [ReportController::class, 'consolidated'])->name('reports.consolidated');
        Route::get('/reportes/consolidado/data', [ReportController::class, 'consolidatedData'])->name('reports.consolidated-data');
    });

    // ── super_admin y admin ───────────────────────────────────────────────────
    Route::middleware('role:super_admin,admin')->group(function () {
        // Tasa manual
        Route::post('/tasa/manual', [SettingsController::class, 'setManualRate'])->name('rate.manual');

        // Cierre del Día — confirmar solo admin/super_admin
        Route::post('/caja/cierre/{register}', [CashRegisterController::class, 'confirmClose'])->name('cash.confirm-close');

        // Anular venta
        Route::patch('/ventas/{sale}/anular', [SaleController::class, 'void'])->name('sales.void');

        // Catálogo
        Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('/catalogo/productos', [CatalogController::class, 'store'])->name('catalog.store');
        Route::put('/catalogo/productos/{product}', [CatalogController::class, 'update'])->name('catalog.update');
        Route::delete('/catalogo/productos/{product}', [CatalogController::class, 'destroy'])->name('catalog.destroy');
        Route::post('/catalogo/categorias', [CatalogController::class, 'storeCategory'])->name('catalog.category.store');
        Route::put('/catalogo/categorias/{category}', [CatalogController::class, 'updateCategory'])->name('catalog.category.update');
        Route::delete('/catalogo/categorias/{category}', [CatalogController::class, 'destroyCategory'])->name('catalog.category.destroy');
        Route::post('/catalogo/subcategorias', [CatalogController::class, 'storeSubcategory'])->name('catalog.subcategory.store');
        Route::put('/catalogo/subcategorias/{subcategory}', [CatalogController::class, 'updateSubcategory'])->name('catalog.subcategory.update');
        Route::delete('/catalogo/subcategorias/{subcategory}', [CatalogController::class, 'destroySubcategory'])->name('catalog.subcategory.destroy');

        // Fábrica
        Route::get('/fabrica',  [FabricaController::class, 'index'])->name('fabrica.index');
        Route::post('/fabrica', [FabricaController::class, 'store'])->name('fabrica.store');

        // Inventario
        Route::get('/inventario', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventario', [InventoryController::class, 'store'])->name('inventory.store');

        // Despiece
        Route::get('/despiece', [DespieceController::class, 'index'])->name('despiece.index');
        Route::post('/despiece', [DespieceController::class, 'store'])->name('despiece.store');

        // Bóveda
        Route::get('/boveda', [BovedaController::class, 'index'])->name('boveda.index');
        Route::post('/boveda', [BovedaController::class, 'store'])->name('boveda.store');
        Route::patch('/boveda/{entry}/surtir', [BovedaController::class, 'surte'])->name('boveda.surte');
        Route::patch('/boveda/{entry}/cerrar', [BovedaController::class, 'close'])->name('boveda.close');
        Route::patch('/boveda/{entry}/merma', [BovedaController::class, 'registerMerma'])->name('boveda.merma');
        Route::get('/boveda/{entry}/plantilla', [BovedaController::class, 'plantillaDespiece'])->name('boveda.plantilla');
        Route::post('/boveda/productos', [BovedaController::class, 'storeProduct'])->name('boveda.product.store');
        Route::put('/boveda/productos/{product}', [BovedaController::class, 'updateProduct'])->name('boveda.product.update');
        Route::delete('/boveda/productos/{product}', [BovedaController::class, 'destroyProduct'])->name('boveda.product.destroy');

        // Reportes
        Route::get('/reportes', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reportes/ventas', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('/reportes/inventario', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reportes/cierres', [ReportController::class, 'closings'])->name('reports.closings');
        Route::get('/reportes/pedidos', [ReportController::class, 'orders'])->name('reports.orders');
        Route::get('/reportes/dia', [ReportController::class, 'dayReport'])->name('reports.day');
        Route::get('/reportes/pdf-dia', [ReportController::class, 'exportDayPdf'])->name('reports.day-pdf');
        Route::get('/reportes/exportar', [ReportController::class, 'export'])->name('reports.export');

        // Configuración — Métodos de Pago
        Route::get('/configuracion/metodos-pago', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
        Route::post('/configuracion/metodos-pago', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
        Route::put('/configuracion/metodos-pago/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
        Route::patch('/configuracion/metodos-pago/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');
        Route::delete('/configuracion/metodos-pago/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
        Route::post('/configuracion/metodos-pago/reorder', [PaymentMethodController::class, 'reorder'])->name('payment-methods.reorder');

        // Configuración — General, Cajas, Terminales
        Route::get('/configuracion', fn () => redirect()->route('settings.general'))->name('settings.index');
        Route::prefix('configuracion')->name('settings.')->group(function () {
            Route::get('/general',    [SettingsController::class, 'general'])->name('general');
            Route::post('/general',   [SettingsController::class, 'updateGeneral'])->name('general.update');
            Route::get('/cajas',                   [SettingsController::class, 'cashRegisters'])->name('cash-registers');
            Route::post('/cajas',                  [SettingsController::class, 'storeCashRegister'])->name('cash-registers.store');
            Route::put('/cajas/{cashRegister}',    [SettingsController::class, 'updateCashRegister'])->name('cash-registers.update');
            Route::delete('/cajas/{cashRegister}', [SettingsController::class, 'destroyCashRegister'])->name('cash-registers.destroy');
            Route::get('/terminales',              [SettingsController::class, 'terminals'])->name('terminals');
            Route::post('/terminales',             [SettingsController::class, 'storeTerminal'])->name('terminals.store');
            Route::put('/terminales/{terminal}',   [SettingsController::class, 'updateTerminal'])->name('terminals.update');
            Route::delete('/terminales/{terminal}',[SettingsController::class, 'destroyTerminal'])->name('terminals.destroy');
            Route::get('/ticket',  [SettingsController::class, 'ticket'])->name('ticket');
            Route::post('/ticket', [SettingsController::class, 'updateTicket'])->name('ticket.update');
            Route::get('/sucursales',             [SettingsController::class, 'branches'])->name('branches');
            Route::post('/sucursales',            [SettingsController::class, 'storeBranch'])->name('branches.store');
            Route::put('/sucursales/{branch}',    [SettingsController::class, 'updateBranch'])->name('branches.update');
        });

        // Contingencia
        Route::get('/contingencia', [ContingencyController::class, 'index'])->name('contingency.index');
        Route::get('/contingencia/formato-papel', [ContingencyController::class, 'downloadForm'])->name('contingency.form');
        Route::get('/contingencia/plantilla-ventas', [ContingencyController::class, 'downloadTemplate'])->name('contingency.sales-template');
        Route::get('/contingencia/plantilla-inventario', [ContingencyController::class, 'downloadInventoryTemplate'])->name('contingency.inventory-template');
        Route::post('/contingencia/importar-ventas', [ContingencyController::class, 'importSales'])->name('contingency.import-sales');
        Route::post('/contingencia/importar-inventario', [ContingencyController::class, 'importInventory'])->name('contingency.import-inventory');
    });

    // ── Solo super_admin ──────────────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        Route::prefix('configuracion')->name('settings.')->group(function () {
            Route::get('/usuarios',           [SettingsController::class, 'users'])->name('users');
            Route::post('/usuarios',          [SettingsController::class, 'storeUser'])->name('users.store');
            Route::put('/usuarios/{user}',    [SettingsController::class, 'updateUser'])->name('users.update');
            Route::delete('/usuarios/{user}', [SettingsController::class, 'destroyUser'])->name('users.destroy');
        });
    });

});


require __DIR__.'/auth.php';
