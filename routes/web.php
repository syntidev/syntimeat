<?php

declare(strict_types=1);

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleController;
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
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ─── Catálogo ─────────────────────────────────────────────────────────────
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

    // ─── Configuración — Métodos de Pago ─────────────────────────────────────
    Route::get('/configuracion/metodos-pago', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('/configuracion/metodos-pago', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('/configuracion/metodos-pago/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::patch('/configuracion/metodos-pago/{paymentMethod}/toggle', [PaymentMethodController::class, 'toggle'])->name('payment-methods.toggle');
    Route::delete('/configuracion/metodos-pago/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');
    Route::post('/configuracion/metodos-pago/reorder', [PaymentMethodController::class, 'reorder'])->name('payment-methods.reorder');

    // ─── Inventario ───────────────────────────────────────────────────────────
    Route::get('/inventario', [InventoryController::class, 'index'])->name('inventory.index');
    Route::post('/inventario', [InventoryController::class, 'store'])->name('inventory.store');

    // ─── POS ──────────────────────────────────────────────────────────────────
    Route::get('/pos', [SaleController::class, 'index'])->name('pos.index');
    Route::post('/pos/ventas', [SaleController::class, 'store'])->name('sales.store');
    Route::patch('/pos/ventas/{sale}/pagar', [SaleController::class, 'pay'])->name('sales.pay');
    Route::patch('/pos/ventas/{sale}/cancelar', [SaleController::class, 'cancel'])->name('sales.cancel');
});

require __DIR__.'/auth.php';
