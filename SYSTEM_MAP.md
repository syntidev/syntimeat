# SYSTEM MAP — SYNTImeat v3.4
Actualizado: 2026-06-05
Versión anterior: 2026-06-02 (v3.3)
▲ Cambios de la sesión 03–05/06/2026 marcados con ▲
(cambios sesión 31/05–02/06 marcados con ▲▲)

---

## PRODUCCIÓN

| Campo        | Valor |
|--------------|-------|
| URL          | https://meat.synti.cloud |
| VPS          | 187.124.241.213 (Ubuntu 24.04 — Hostinger KVM1) |
| DB           | syntimeat_db / syntimeat / SyntiMeat2026! |
| Branch git   | main |
| ▲ Commit     | 4d2f599 |
| ▲ Versión    | v3.4 |
| ▲ Tag        | v3.4-roccia-certificado |
| ▲ Fecha      | 2026-06-05 |
| ▲ Sucursales | branch_id=1 Chaguaramas · branch_id=3 El Buen Corte |

### ▲ Usuarios en producción

| Email                  | Rol          | Branch |
|------------------------|--------------|--------|
| master@matriz.com      | owner        | —      |
| conta@matriz.com       | analyst      | 1      |
| caja@matriz.com        | cashier      | 1      |
| admin@sucursal.com     | branch_admin | 3      |
| caja@sucursal.com      | cashier      | 3      |

---

## 1. Controllers

### BovedaController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 23 | `index()` | GET | /boveda |
| 98 | `store()` | POST | /boveda |
| 214 | `update()` | PUT | /boveda/{entry} |
| 259 | `destroy()` | DELETE | /boveda/{entry} |
| 282 | `surte()` | PATCH | /boveda/{entry}/surtir |
| 428 | `close()` | PATCH | /boveda/{entry}/cerrar |
| 456 | `registerMerma()` | PATCH | /boveda/{entry}/merma |
| 501 | `storeProduct()` | POST | /boveda/productos |
| 538 | `updateProduct()` | PUT | /boveda/productos/{product} |
| 577 | `plantillaDespiece()` | GET | /boveda/{entry}/plantilla |
| 633 | `destroyProduct()` | DELETE | /boveda/productos/{product} |

**Request validated fields:**
- `store()`: product_type, description, kg_entrada, costo_usd, supplier, entered_at, kg_par (nullable, numeric, min:0.001)
- `surte()`: peso_real
- `registerMerma()`: peso_actual
- `storeProduct()`: name, unit, requires_despiece, vitrina_product_id
- `updateProduct()`: name, unit, requires_despiece, vitrina_product_id

**Canal 1 / Canal 2 (pair_id):** Cuando product_type = 'RES - Medio Canal' y se envía kg_par, `store()` crea DOS BovedaEntries con `pair_id` cruzado. La segunda hereda `costo_usd` prorrateado por peso.

**catMap en plantillaDespiece():**
```php
$catMap = [
    'RES - Medio Canal'        => 'Res',
    'CERDO - Canal'            => 'Cerdo',
    'POLLO - Entero Congelado' => 'Pollo',
];
$resOrder   = ['Carne del Canal', 'Costilla', 'Hueso Redondo', 'Hueso Rojo'];
// ▲ polloOrder presente en plantillaDespiece() — commit c6b2dd5
// ▲ Detección catMap por nombre (str_contains) como fallback — commit 38eaae8
```

**Inertia props `index()`:** activas, historial, bovedaProducts, productosVitrina, kpis{entradasActivas, kgDisponible, costoActivo, surtidoHoy}

---

### FabricaController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 24 | `index()` | GET | /fabrica |
| 186 | `store()` | POST | /fabrica |
| 329 | `storeDespiece()` | POST | /fabrica/despiece |

**Request validated fields:**
- `store()`: output_product_id, output_kg, output_units, inputs[].product_id, inputs[].quantity_kg, inputs[].cost_usd, notes, produced_at
- `storeDespiece()`: boveda_entry_id, cortes[].product_id (Rule::exists scoped a business_id), cortes[].kg, notes

▲ **branch_id en storeDespiece():** `InventoryEntry::create()` incluye ahora `branch_id` calculado al inicio del método (fallback a `Branch::where('business_id',...)->orderBy('id')->value('id')`).

**catMap y resOrder (4 cortes Res — filtro UI):**
```php
$catMap = ['RES - Medio Canal' => 'Res', 'CERDO - Canal' => 'Cerdo', 'POLLO - Entero Congelado' => 'Pollo'];
$resOrder = ['Carne del Canal', 'Costilla', 'Hueso Redondo', 'Hueso Rojo'];
// ->when($catName === 'Res', fn($q) => $q->whereIn('name', $resOrder))
```

▲ **catMap Pollo + polloOrder (commits f656a1a · c6b2dd5):** Fábrica y Bóveda detectan POLLO por nombre (`str_contains($bovedaProduct->name, 'Pollo')` como fallback) además del prefijo. `$polloOrder` ahora también presente en `plantillaDespiece()` de BovedaController para planilla Bóveda.

**Inertia props `index()`:** fabricables, ingredientes, stockMap, historial, despiecePendiente, despieceHistorial

---

### SaleController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 30 | `index()` | GET | /pos |
| 121 | `store()` | POST | /pos/ventas |
| 333 | `pay()` | PATCH | /pos/ventas/{sale}/pagar |
| 480 | `cancel()` | PATCH | /pos/ventas/{sale}/cancelar |
| 548 | `historial()` | GET | /ventas |
| 654 | `void()` | PATCH | /ventas/{sale}/anular |

▲▲ **$branchId capturado en closures de DB::transaction** en `pay()` y `cancel()` — se declara antes de la transacción y se pasa vía `use ($branchId, ...)` para evitar undefined variable en closures.

▲ **Guard stock negativo (commit 8e97b06):** `store()` valida que `net_kg >= items[].quantity_value` antes de crear la venta. Ventas que superan stock ahora retornan error 422 con mensaje descriptivo — ya no se permiten stocks negativos silenciosos.

**Patrón pool stock_product_id:**
```php
$stockProductId = $item->product?->stock_product_id ?? $item->product_id;
InventoryEntry::create(['product_id' => $stockProductId, ...]);
```
Premium, Primera y Segunda descuentan stock de 'Carne del Canal'.

**Request validated fields:**
- `store()`: items[].product_id, items[].input_type, items[].amount_bs, items[].quantity_value, origin, channel, status, client_name, client_phone, client_id
- `cancel()`: cancellation_reason (min:5)
- `void()`: motivo (min:5)

**Inertia props `index()`:** products, categories, cashRegister, todayRate, paymentMethods, ticketPrefix, stockMap, posShowKg, businessInfo, ticketPrefs

---

### CashRegisterController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 26 | `index()` | GET | /caja |
| 105 | `open()` | POST | /caja/abrir |
| 174 | `close()` | POST | /caja/{register}/cerrar |
| 222 | `dayClose()` | GET | /caja/cierre |
| 404 | `confirmClose()` | POST | /caja/cierre/{register} |
| 470 | `movement()` | POST | /caja/{register}/movimiento |

**Request validated fields:**
- `open()`: opening_amount_bs
- `close()`: counted_cash_bs, notes
- `movement()`: type (in/out/corte), amount_bs, concept
- `confirmClose()`: counted_cash_bs, notes

**Inertia props `index()`:** cashRegister, allOpenRegisters, history, kpis{expected_bs, sales_total_bs, movements_count, rate}, todayRate, isAdmin

---

### CatalogController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 28 | `index()` | GET | /catalogo |
| 76 | `store()` | POST | /catalogo/productos |
| 148 | `update()` | PUT | /catalogo/productos/{product} |
| 206 | `destroy()` | DELETE | /catalogo/productos/{product} |
| 222 | `toggleFavorite()` | PATCH | /catalogo/productos/{product}/favorito |
| 233 | `downloadProductTemplate()` | GET | /catalogo/plantilla-productos |
| 251 | `importProducts()` | POST | /catalogo/importar |
| 422 | `storeCategory()` | POST | /catalogo/categorias |
| 461 | `storeSubcategory()` | POST | /catalogo/subcategorias |
| 480 | `updateCategory()` | PUT | /catalogo/categorias/{category} |
| 494 | `destroyCategory()` | DELETE | /catalogo/categorias/{category} |
| 510 | `updateSubcategory()` | PUT | /catalogo/subcategorias/{subcategory} |
| 521 | `destroySubcategory()` | DELETE | /catalogo/subcategorias/{subcategory} |

▲ **Request validated fields (store/update producto):**
- name, `barcode` (nullable, string, max:50), category_id, subcategory_id, sale_mode, price_per_kg_usd, price_per_unit_usd, min_stock, fabricable, is_favorite, image (file)

▲ **min_stock cast seguro (store() L107–109 · update() L170–172):**
```php
if ($validated['sale_mode'] === 'unit') {
    $validated['min_stock'] = (float)($validated['min_stock'] ?? 0);
    $validated['min_stock'] = (int) round($validated['min_stock']);
}
```
Previene TypeError de PHP strict_types cuando min_stock llega como null desde el form.

▲ **branchId fallback en store():**
```php
$branchId = $user->branch_id
    ?? session('current_branch_id')
    ?? Branch::where('business_id', $businessId)->orderBy('id')->value('id');
```

**importProducts():** Importa CSV/Excel. Devuelve JSON `{imported, updated, total, errors[]}`. Upsert por nombre dentro del business.

**Inertia props `index()`:** categories (con subcategorías), products (con current_stock calculado)

---

### InventoryController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 20 | `index()` | GET | /inventario |
| 102 | `store()` | POST | /inventario |

**Request validated fields (`store()`):** product_id, quantity_kg, waste_kg, cost_per_kg_usd, supplier, notes, location, entered_at

**Inertia props `index()`:** products, categories, todayEntries, stockMap, lastEntryMap, kpis

---

### DashboardController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 30 | `index()` | GET | /dashboard |
| 40 | `data()` | GET | /dashboard/data (JSON) |

**Filtro branch_id por rol:** `data()` filtra ventas por branch_id según rol. Owner/super_admin ven todas las sucursales.

▲ **Inertia props `index()`:** ventas_hoy, top_productos, stock_critico, ultimas_ventas, caja_activa, tasa_hoy, pedidos_pendientes, `categorias_hoy` (reemplaza kilos_por_categoria), utilidad_boveda

---

### OrderController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 31 | `index()` | GET | /pedidos |
| 117 | `store()` | POST | /pedidos |
| 196 | `collect()` | PATCH | /pedidos/{order}/cobrar |
| 336 | `dispatch()` | PATCH | /pedidos/{order}/despachar |
| 413 | `collectPending()` | PATCH | /ventas/{sale}/cobrar-pendiente |
| 512 | `cancel()` | PATCH | /pedidos/{order}/cancelar |
| 547 | `deliveryIndex()` | GET | /pedidos/delivery |
| 576 | `confirmDelivery()` | PATCH | /pedidos/{sale}/delivery-cobrado |

**Request validated fields:**
- `store()`: client_name, client_type, items[].product_id, items[].quantity_value, notes
- `collect()`: payment_method_id, amount_bs, reference, rate
- `cancel()`: motivo
- `collectPending()`: payments[], rate

**Inertia props `index()`:** pedidosActivos, historial, cobrosPendientes, products, paymentMethods, paymentTerminals, todayRate, kpis

---

### ReportController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 30 | `index()` | GET | /reportes |
| 52 | `sales()` | GET | /reportes/ventas (JSON) |
| 110 | `inventory()` | GET | /reportes/inventario (JSON) |
| 158 | `closings()` | GET | /reportes/cierres (JSON) |
| 197 | `orders()` | GET | /reportes/pedidos (JSON) |
| 243 | `dayReport()` | GET | /reportes/dia (JSON) |
| 270 | `exportDayPdf()` | GET | /reportes/pdf-dia (PDF) |
| 300 | `consolidated()` | GET | /reportes/consolidado |
| 325 | `consolidatedData()` | GET | /reportes/consolidado/data (JSON) |
| 363 | ▲ `canalRendimiento()` | GET | /reportes/canal-rendimiento (JSON) |
| 898 | `export()` | GET | /reportes/exportar (XLSX) |

▲ **canalRendimiento():** Filtra DespieceLog por branch_id del usuario. Calcula rendimiento por canal (kg_entrada → kg_vitrina → merma). `margen_pct` corregido: usa costo real de boveda_entry vs ingresos vitrina.

**buildDayData() — costo con pool stock_product_id:**
```php
$costProductId = $item->product?->stock_product_id ?? $item->product_id;
$costPerKg     = (float) ($avgCosts[$costProductId] ?? 0);
```

**Filtros comunes:** date_from, date_to, cashier_id, payment_method, status, branch_id

**Inertia props `index()`:** categories, products

---

### ClientController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 19 | `index()` | GET | /clientes |
| 59 | `store()` | POST | /clientes |
| 99 | `update()` | PUT | /clientes/{client} |
| 143 | `show()` | GET | /clientes/{client} |
| 165 | `search()` | GET | /clientes/buscar (JSON) |

**Request validated fields:** cedula, name, phone, email, address, notes

---

### SettingsController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 26 | `setManualRate()` | POST | /tasa/manual |
| 39 | `general()` | GET | /configuracion/general |
| 48 | `updateGeneral()` | POST | /configuracion/general |
| 85 | `users()` | GET | /configuracion/usuarios |
| 108 | `storeUser()` | POST | /configuracion/usuarios |
| 134 | `updateUser()` | PUT | /configuracion/usuarios/{user} |
| 159 | `destroyUser()` | DELETE | /configuracion/usuarios/{user} |
| 176 | `cashRegisters()` | GET | /configuracion/cajas |
| 200 | `storeCashRegister()` | POST | /configuracion/cajas |
| 220 | `updateCashRegister()` | PUT | /configuracion/cajas/{cashRegister} |
| 233 | `destroyCashRegister()` | DELETE | /configuracion/cajas/{cashRegister} |
| 243 | `terminals()` | GET | /configuracion/terminales |
| 257 | `storeTerminal()` | POST | /configuracion/terminales |
| 280 | `updateTerminal()` | PUT | /configuracion/terminales/{terminal} |
| 297 | `destroyTerminal()` | DELETE | /configuracion/terminales/{terminal} |
| 319 | `ticket()` | GET | /configuracion/ticket |
| 354 | `updateTicket()` | POST | /configuracion/ticket |
| 402 | `branches()` | GET | /configuracion/sucursales |
| 415 | `storeBranch()` | POST | /configuracion/sucursales |
| 433 | `updateBranch()` | PUT | /configuracion/sucursales/{branch} |
| — | `hardware()` (closure) | GET | /configuracion/hardware |

---

### PaymentMethodController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 17 | `index()` | GET | /configuracion/metodos-pago |
| 39 | `store()` | POST | /configuracion/metodos-pago |
| 63 | `update()` | PUT | /configuracion/metodos-pago/{paymentMethod} |
| 78 | `toggle()` | PATCH | /configuracion/metodos-pago/{paymentMethod}/toggle |
| 87 | `destroy()` | DELETE | /configuracion/metodos-pago/{paymentMethod} |
| 96 | `reorder()` | POST | /configuracion/metodos-pago/reorder |

---

### TeamController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 26 | `index()` | GET | /configuracion/equipo |
| 63 | `store()` | POST | /configuracion/equipo |
| 99 | `update()` | PUT | /configuracion/equipo/{user} |
| 141 | `toggleActive()` | PATCH | — |
| 159 | `killSession()` | PATCH | — |
| 171 | `destroy()` | DELETE | /configuracion/equipo/{user} |

Gestión de usuarios del equipo (toggle activo, matar sesión, CRUD).

---

### ContingencyController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 38 | `index()` | GET | /contingencia |
| 45 | `downloadForm()` | GET | /contingencia/formato-papel |
| 65 | `downloadTemplate()` | GET | /contingencia/plantilla-ventas |
| 81 | `downloadInventoryTemplate()` | GET | /contingencia/plantilla-inventario |
| 97 | `importSales()` | POST | /contingencia/importar-ventas |
| 269 | `importInventory()` | POST | /contingencia/importar-inventario |

---

### OnboardingController
| Método | Verb | Ruta |
|--------|------|------|
| `show()` | GET | /setup |
| `store()` | POST | /setup/{step} |

---

## 2. Modelos (solo los que cambiaron en sesión 29/05/2026)

### Order ▲
**fillable:** business_id, `branch_id`, client_name, client_type, status, total_usd, notes, created_by

**with:** items (eager)

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| belongsTo | business() | Business |
| belongsTo | branch() | Branch |
| belongsTo | creator() | User (FK: created_by) |
| hasMany | items() | OrderItem |
| hasOne | sale() | Sale |

---

### Client ▲
**fillable:** business_id, `branch_id`, cedula, name, phone, email, address, notes, active

**relaciones:** belongsTo business(), belongsTo branch(), hasMany sales()

---

### FabricaBatch ▲
**fillable:** business_id, `branch_id`, created_by, output_product_id, output_kg, output_units, input_cost_usd, notes, produced_at

**relaciones:** belongsTo business(), belongsTo branch(), belongsTo creator() (User), belongsTo outputProduct() (Product), hasMany inputs() (FabricaInput)

---

### PaymentTerminal ▲
**fillable:** business_id, `branch_id`, name, type, bank_name, is_active

**relaciones:** belongsTo business(), belongsTo branch()

---

### CashMovement ▲
**fillable:** cash_register_id, `branch_id`, type (in/out/corte), amount_usd, amount_bs, concept, created_by

**relaciones:** belongsTo cashRegister(), belongsTo branch(), belongsTo creator() (User)

---

### Product ▲
**fillable:** business_id, branch_id, category_id, subcategory_id, name, sku, `barcode`, sale_mode, base_unit_label, fraction_allowed, price_per_kg_usd, price_per_unit_usd, `price_per_kg_bs`, `price_per_unit_bs`, min_stock, location, image_path, sort_order, active, fabricable, is_favorite, stock_product_id

**▲ price_per_kg_bs / price_per_unit_bs:** `decimal(12,2) nullable` — precio de vitrina en Bs calculado al momento de actualización (price_usd × tasa). Almacenado como referencia rápida en ticket / catálogo.

**stock_product_id:** nullable FK. Descuento de inventario y cálculo de costo se hacen contra este product_id. Premium/Primera/Segunda → 'Carne del Canal'.

**▲ SKUs báscula (branch=3 y branch=1):**
| SKU | Producto | Barcode Roccia |
|-----|----------|----------------|
| 00001 | Primera | EAN-13 con PLU 00001 |
| 00002 | Segunda | EAN-13 con PLU 00002 |
| 00003 | Premium | EAN-13 con PLU 00003 |

---

## 3. Modelos sin cambios (referencia rápida)

| Modelo | Notas clave |
|--------|-------------|
| Business | subscription_active (kill switch) · legal_name/rif nullable desde 2026-05-23 |
| User | is_hidden · roles: super_admin,owner,branch_admin,supervisor,analyst,cashier,admin |
| Sale | accounting_date nullable · stock descuenta solo cuando status='paid' |
| SaleItem | snapshots product_name, price_per_kg_usd al momento de venta |
| CashRegister | opened_at/opening_amount_usd/opened_by ahora nullable |
| InventoryEntry | net_kg = quantity_kg - waste_kg (GENERATED VIRTUAL DB) |
| BovedaEntry | pair_id (Canal 1/2) · kg_disponible GENERATED VIRTUAL |
| BovedaProduct | Catálogo Chaguaramas: RES, POLLO, CERDO, Jamón Pierna · ▲ Pollo el Corral + Pollo Entero Q Pollo → requires_despiece=1 |
| FabricaInput | FK: fabrica_batch_id, product_id, despiece_item_id, inventory_entry_id |
| Category | macro_category: BOVEDA,RES,POLLO,CERDO,CHARCUTERIA,TRASTES,DESPENSA |
| Branch | business_id, name, address, city, phone, is_active, access_start, access_end |
| DollarRate | Conexión readonly `synticorex` DB · UPDATED_AT=null |
| PaymentMethod | branch_id desde 2026-05-28 · ▲ branch=3 datos: POS BDV, POS Provincial, POS Activo agregados |
| ActivityLog | old_values/new_values:array |

---

## 4. Services

### DollarRateService
| Método | Firma | Propósito |
|--------|-------|-----------|
| `getTodayRate()` | `(string $source = 'bcv'): float` | Tasa del día, fallback a última disponible |
| `getLatestRate()` | `(string $source = 'bcv'): float` | Última tasa activa sin importar fecha |
| `fetchAndStore()` | `(): array{success, rate?, source?, message}` | Consulta APIs y persiste nueva tasa USD |
| `storeManualRate()` | `(float $rate): bool` | Graba tasa manual del admin |
| `formatBs()` | `(float $usd, float $rate): float` | usd × rate, redondeado a 2 decimales |

**Constantes:** FALLBACK = 40.00 | MAX_CHANGE_PCT = 60% | CACHE_TTL = 3600s

### CurrencyFetcherService
| Método | Propósito |
|--------|-----------|
| `fetchUSD()` | BCV oficial USD — dolarapi.com → brecha-cambiaria.com |
| `fetchEUR()` | BCV oficial EUR — mismas fuentes |

---

## 5. Middleware

| Clase | Alias | Propósito |
|-------|-------|-----------|
| `EnsureRole` | `role` | ▲ Verifica rol en lista; roles producción: owner,analyst,cashier,branch_admin |
| `CheckOnboarding` | `check.onboarding` | Redirige a /setup si business no tiene onboarding_completed |
| `EnforceUserSession` | (global) | Verifica is_active, sesión única, días habilitados, ventana horaria. Exento: owner, super_admin |
| `HandleInertiaRequests` | (global) | Inyecta auth.user, flash, tasa, banking_alert en shared props Inertia |
| `CheckSubscription` | `subscription` | Kill switch: si business.subscription_active=false → logout + mantenimiento |

**Stack middleware autenticado:** `['auth', 'verified', 'check.onboarding', 'subscription']`

---

## 6. Artisan Commands

| Comando | Clase | Propósito |
|---------|-------|-----------|
| `dollar:fetch` | UpdateDollarRate | Consulta BCV y persiste tasa USD/EUR |
| `cash:banking-alert` | BankingAlertCommand | Guarda alerta corte bancario en caché (--minutes=30\|20\|10\|0) |
| `demo:reset` | ResetDemoData | Resetea datos demo (desarrollo) |

**▲ Alerta bancaria — schedule actualizado (console.php):**
```php
Schedule::command('cash:banking-alert --minutes=30')->dailyAt('19:00')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=20')->dailyAt('19:10')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=10')->dailyAt('19:20')->timezone('America/Caracas');
Schedule::command('cash:banking-alert --minutes=0')->dailyAt('19:30')->timezone('America/Caracas');
```
Ventana corrida de 18:40–19:00 → 19:00–19:30. Corte real a las 7:30 PM Venezuela.

**▲ Mensaje --minutes=0 actualizado:**
`"¡CORTE BANCARIO! Procesa los pagos pendientes en los terminales correspondientes (ej: Banco de Venezuela)."`

**Alerta bancaria:** Escribe en cache `banking_alert` (TTL 2h) → HandleInertiaRequests lo inyecta → AppLayout lo muestra como banner global en POS.

---

## 7. Rutas

### Públicas (sin auth)
| URI | Verb | Controller@método |
|-----|------|-------------------|
| / | GET | redirect → login |
| /ayuda | GET | closure → Ayuda |
| /setup | GET | OnboardingController@show |
| /setup/{step} | POST | OnboardingController@store |

### Autenticadas — `auth, verified, check.onboarding, subscription`

#### Todos los roles (`super_admin,admin,owner,branch_admin,supervisor,analyst,cashier`)
| URI | Verb | Nombre |
|-----|------|--------|
| /dashboard | GET | dashboard |
| /dashboard/data | GET | dashboard.data |
| /pos | GET | pos.index |
| /pos/ventas | POST | sales.store |
| /pos/ventas/{sale}/pagar | PATCH | sales.pay |
| /pos/ventas/{sale}/cancelar | PATCH | sales.cancel |
| /ventas | GET | sales.index |
| /caja | GET | cash.index |
| /caja/abrir | POST | cash.open |
| /caja/{register}/cerrar | POST | cash.close |
| /caja/{register}/movimiento | POST | cash.movement |
| /caja/cierre | GET | cash.day-close |
| /clientes/buscar | GET | clients.search |
| /clientes | GET/POST | clients.index / clients.store |
| /clientes/{client} | GET/PUT | clients.show / clients.update |
| /pedidos | GET/POST | orders.index / orders.store |
| /pedidos/delivery | GET | orders.delivery |
| /pedidos/{order}/cobrar | PATCH | orders.collect |
| /pedidos/{order}/despachar | PATCH | orders.dispatch |
| /pedidos/{order}/cancelar | PATCH | orders.cancel |
| /pedidos/{sale}/delivery-cobrado | PATCH | sales.delivery-confirm |
| /ventas/{sale}/cobrar-pendiente | PATCH | sales.collect-pending |
| /inventario | GET/POST | inventory.index / inventory.store |
| /contingencia | GET + POST | contingency.* |
| /profile | GET/PATCH/DELETE | profile.* |

#### `super_admin, owner` únicamente
| URI | Verb | Nombre |
|-----|------|--------|
| /reportes/consolidado | GET | reports.consolidated |
| /reportes/consolidado/data | GET | reports.consolidated-data |

#### ▲ `super_admin, owner, branch_admin, analyst`
| URI | Verb | Nombre |
|-----|------|--------|
| /reportes/canal-rendimiento | GET | reports.canal |

#### ▲ Selector sucursal — `super_admin, owner` (branch_admin eliminado en sesión 02/06)
| URI | Verb | Nombre |
|-----|------|--------|
| /set-branch | POST | branch.set |

#### ▲ QZ Tray — sin middleware auth, sin CSRF
| URI | Verb | Controller@método |
|-----|------|-------------------|
| /qz/certificate | GET | QzController@certificate |
| /qz/sign | POST | QzController@sign |

`bootstrap/app.php`: `/qz/sign` exento de verificación CSRF via `$middleware->validateCsrfTokens(except: ['qz/sign'])`.

#### `super_admin,admin,owner,branch_admin,supervisor,analyst`
| URI | Verb | Nombre |
|-----|------|--------|
| /reportes | GET + JSON endpoints | reports.* |
| /tasa/manual | POST | rate.manual |
| /caja/cierre/{register} | POST | cash.confirm-close |
| /ventas/{sale}/anular | PATCH | sales.void |
| /catalogo | GET + CRUD | catalog.* |
| /catalogo/importar | POST | catalog.import |
| /fabrica | GET/POST | fabrica.index / fabrica.store |
| /fabrica/despiece | POST | fabrica.despiece |
| /boveda | GET/POST + CRUD | boveda.* |
| /configuracion/metodos-pago | GET + CRUD | payment-methods.* |
| /configuracion/general | GET/POST | settings.general |
| /configuracion/cajas | GET + CRUD | settings.cash-registers.* |
| /configuracion/terminales | GET + CRUD | settings.terminals.* |
| /configuracion/ticket | GET/POST | settings.ticket |
| /configuracion/hardware | GET | settings.hardware |
| /configuracion/sucursales | GET | settings.branches |

#### `super_admin, owner, analyst`
| URI | Verb | Nombre |
|-----|------|--------|
| /configuracion/usuarios | GET + CRUD | settings.users.* |
| /configuracion/equipo | GET + CRUD | settings.team.* |

#### `super_admin,admin,owner,branch_admin,supervisor` (crear/editar sucursales)
| URI | Verb | Nombre |
|-----|------|--------|
| /configuracion/sucursales | POST/PUT | settings.branches.store / .update |

---

## 8. Tablas DB — branch_id certificadas ▲

| # | Tabla | Migración |
|---|-------|-----------|
| 1 | users | 0001_01_01_000000 |
| 2 | cash_registers | 2026_05_13_220003 |
| 3 | sales | 2026_05_13_220003 |
| 4 | inventory_entries | 2026_05_13_220003 |
| 5 | products | 2026_05_14_065526 |
| 6 | boveda_entries | 2026_05_28_173645 |
| 7 | payment_methods | 2026_05_28_180001 |
| 8 | orders | ▲ 2026_05_29_000001 |
| 9 | despiece_logs | ▲ 2026_05_29_000001 |
| 10 | fabrica_batches | ▲ 2026_05_29_000001 |
| 11 | clients | ▲ 2026_05_29_000001 |
| 12 | payment_terminals | ▲ 2026_05_29_000001 |
| 13 | cash_movements | ▲ 2026_05_29_000002 |

**Total: 13 tablas con branch_id certificadas al 29/05/2026.**

---

## 9. Migraciones — tabla completa cronológica

| Archivo | Tabla | Operación |
|---------|-------|-----------|
| 0001_01_01_000000_create_users_table | users | CREATE |
| 0001_01_01_000001_create_cache_table | cache, cache_locks | CREATE |
| 0001_01_01_000002_create_jobs_table | jobs, job_batches, failed_jobs | CREATE |
| 2026_05_09_000001 | businesses | CREATE |
| 2026_05_09_000002 | users | ADD business_id |
| 2026_05_09_000003 | categories | CREATE |
| 2026_05_09_000004 | subcategories | CREATE |
| 2026_05_09_000005 | products | CREATE |
| 2026_05_09_000006 | inventory_entries | CREATE (net_kg GENERATED VIRTUAL) |
| 2026_05_09_000007 | cash_registers | CREATE |
| 2026_05_09_000008 | sales | CREATE |
| 2026_05_09_000009 | sale_items | CREATE (snapshots product_name, prices) |
| 2026_05_09_000010 | orders | CREATE |
| 2026_05_09_000011 | order_items | CREATE |
| 2026_05_09_000012 | cash_movements | CREATE |
| 2026_05_09_000013 | activity_logs | CREATE |
| 2026_05_09_000014 | businesses | ADD settings JSON, rate_margin |
| 2026_05_10_000001 | dollar_rates | CREATE (rate, source, currency_type, is_active) |
| 2026_05_10_000010 | payment_methods | CREATE |
| 2026_05_10_000013 | sale_payments | CREATE |
| 2026_05_10_000014 | sales | ADD client_name, client_phone, client_id |
| 2026_05_10_000015 | clients | CREATE |
| 2026_05_11_000001 | businesses | ADD theme_color |
| 2026_05_11_000002 | payment_terminals | CREATE |
| 2026_05_12_000001 | users | ADD role, theme |
| 2026_05_12_000002 | users | CHANGE role → ENUM |
| 2026_05_12_000010 | inventory_entries | ADD location |
| 2026_05_12_000011 | despiece_logs | CREATE |
| 2026_05_12_000012 | despiece_items | CREATE |
| 2026_05_12_000013 | sales | ADD delivery_status, delivery fields |
| 2026_05_12_000014 | products | ADD location |
| 2026_05_13_000015 | boveda_entries | CREATE |
| 2026_05_13_000016 | boveda_entries | NORMALIZE (reestructura columnas) |
| 2026_05_13_000017 | boveda_products | CREATE |
| 2026_05_13_000018 | despiece_items | ADD tipo |
| 2026_05_13_165047 | boveda_entries | ADD waste_kg |
| 2026_05_13_190001 | inventory_entries | ADD boveda_entry_id |
| 2026_05_13_190002 | boveda_entries | FIX kg_disponible GENERATED VIRTUAL |
| 2026_05_13_190003 | sale_items | ADD subtotal_bs |
| 2026_05_13_200001 | categories | ADD macro_category |
| 2026_05_13_200002 | fabrica_batches | CREATE |
| 2026_05_13_200003 | fabrica_inputs | CREATE |
| 2026_05_13_210001 | products | ADD fabricable |
| 2026_05_13_210002 | fabrica_inputs | ADD product_id |
| 2026_05_13_220001 | branches | CREATE |
| 2026_05_13_220002 | users | EXPAND roles ENUM |
| 2026_05_13_220003 | sales, inventory_entries, cash_registers | ADD branch_id |
| 2026_05_13_230001 | products | ADD cost_per_unit_usd |
| 2026_05_14_000001 | sales | ADD payment_status, order_id |
| 2026_05_14_010001 | businesses | ADD max_branches |
| 2026_05_14_050743 | sales | ADD 'credit' a origin ENUM |
| 2026_05_14_065526 | products | ADD branch_id |
| 2026_05_14_071527 | users | ADD team fields |
| 2026_05_14_074358 | users | ADD access_days |
| 2026_05_14_080924 | clients | RENAME client_code → cedula |
| 2026_05_14_100001 | cash_registers | ADD opening_amount_bs |
| 2026_05_14_180427 | boveda_products | ADD despiece fields |
| 2026_05_14_194421 | boveda_entries | ADD despiece_completado_at |
| 2026_05_15_000001 | products | DROP cost fields |
| 2026_05_16_000001 | cash_movements | ADD 'corte' a type ENUM |
| 2026_05_16_000002 | cash_movements | ADD amount_bs |
| 2026_05_16_000003 | products | ADD is_favorite |
| 2026_05_22_000001 | cash_registers | MAKE opened_at, opening_amount_usd, opened_by nullable |
| 2026_05_22_214705 | users | ADD is_hidden |
| 2026_05_23_000001 | businesses | ADD subscription_active |
| 2026_05_23_000002 | sales | ADD accounting_date |
| 2026_05_23_123937 | businesses | FIX rif nullable |
| 2026_05_24_000001 | boveda_entries | ADD pair_id |
| 2026_05_24_000002 | products | ADD stock_product_id |
| 2026_05_25_000001 | users | ADD permissions |
| 2026_05_28_173645 | boveda_entries | ADD branch_id |
| 2026_05_28_180001 | payment_methods | ADD branch_id |
| ▲▲ 2026_05_29_000001 | orders, despiece_logs, fabrica_batches, clients, payment_terminals | ADD branch_id (5 tablas) |
| ▲▲ 2026_05_29_000002 | cash_movements | ADD branch_id |
| ▲ 2026_06_03_000001 | products | ADD price_per_kg_bs decimal(12,2) nullable, price_per_unit_bs decimal(12,2) nullable |

**Total: 75 migraciones corridas.**

---

## 10. Vistas Vue — árbol Pages/

```
resources/js/Pages/
├── Welcome.vue
├── Ayuda.vue
├── Error.vue
├── Dashboard.vue              ▲ prop categorias_hoy (ex kilos_por_categoria) · empty state canal
├── Auth/
│   ├── Login.vue
│   ├── Register.vue
│   ├── ForgotPassword.vue
│   ├── ResetPassword.vue
│   ├── ConfirmPassword.vue
│   └── VerifyEmail.vue
├── Onboarding/
│   └── Index.vue
├── Profile/
│   ├── Edit.vue
│   └── Partials/
│       ├── UpdateProfileInformationForm.vue
│       ├── UpdatePasswordForm.vue
│       └── DeleteUserForm.vue
├── POS/
│   └── Index.vue              ▲ Scanner EAN-13/Code128: parseBarcodeScale(), onKeyDown global 300ms, handleScan(), showScanFeedback()
├── Boveda/
│   └── Index.vue              entradaForm: conCanal2 + kg_par para RES - Medio Canal
├── Fabrica/
│   └── Index.vue              helpSteps: 4 cortes Res (Carne del Canal, Costilla, Hueso Redondo, Hueso Rojo)
├── Inventory/
│   └── Index.vue              ▲ Campo pivote costo USD↔Bs reactivo (bsCostPrice ↔ form.cost_per_kg_usd · tasa: page.props.tasa.rate)
├── Catalog/
│   └── Index.vue              ▲ Campo pivote precio USD↔Bs reactivo (bsKgPrice/bsUnitPrice · tasa: page.props.tasa.rate) · Botón importar CSV/Excel → POST /catalogo/importar
├── Cash/
│   ├── Index.vue
│   └── DayClose.vue
├── Sales/
│   └── Index.vue
├── Orders/
│   ├── Index.vue
│   └── Delivery.vue
├── Clients/
│   └── Index.vue
├── Reports/
│   ├── Index.vue
│   └── Consolidado.vue
├── Contingency/
│   └── Index.vue
└── Settings/
    ├── General.vue
    ├── Users.vue
    ├── Team.vue
    ├── CashRegisters.vue
    ├── PaymentMethods.vue
    ├── Terminals.vue
    ├── Ticket.vue
    ├── Hardware.vue            ▲▲ Guía de hardware: scanner EAN-13, balanza, impresora térmica · ▲ Campo de prueba Roccia ROP-30 operativo (decodifica EAN-13 en tiempo real) · Pendiente: simulador completo (producto + kg + Bs al escanear)
    └── Branches.vue
```

### ▲ Responsive — Breakpoints normalizados (sesión 29/05/2026)
- **33 archivos Vue actualizados** con `@media (max-width: 640px)` y `@media (max-width: 1023px)`
- **app.css:** clase global `.mobile-cards` para colapsar tablas en mobile
- Regla: `640px` = límite mobile/tablet · `1023px` = límite tablet/desktop

### ▲ POS/Index.vue — Scanner de báscula multi-estándar (Roccia ROP-30)
```javascript
// Báscula Roccia ROP-30 — formato EAN-13 (13 dígitos):
// pos[0]   = prefijo '0'     (siempre 0)
// pos[1]   = fijo '9'        (identificador báscula)
// pos[2..6] = PLU / SKU      (5 dígitos, ej: '00001' = Primera)
// pos[7..11] = precio Bs     (5 dígitos)
// pos[12]  = check digit EAN-13
// Divisor precio: endsWith('00') ? ÷100 : ÷10
// Code128 o cualquier otro — buscar directo por barcode/sku en productos

function parseBarcodeScale(code) { ... }   // retorna { product, weightKg, priceOverride }
function processBarcode(code) { ... }      // path 1: entrada principal desde scanner
function onKeyDown(e) { ... }              // listener global, buffer con timeout 300ms
function handleScan(code) { ... }          // path 3: agrega al ticket activo
function showScanFeedback(name, qty) { ... } // feedback visual 2s
```
Tres paths unificados: `processBarcode()` → `parseBarcodeScale()` → `handleScan()`.
Listener registrado en `onMounted` / removido en `onUnmounted`.
▲ **Carrito (commit 00b1c38):** muestra kg · Bs · USD por ítem — columna triple visible en ticket.
▲ **Certificado (commit 4d2f599):** Scanner Roccia ROP-30 certificado en DESA. Pendiente prueba en producción.

---

## 11. Roles y Permisos

### Roles del sistema
| Rol | Alcance | Restricciones |
|-----|---------|---------------|
| super_admin | Todo, todas las sucursales | — |
| owner | Todo en su business | No crea super_admins |
| branch_admin | Todo en su sucursal | No crea owners ni otras sucursales |
| supervisor | Operativo + reportes | No confirma cierres ni anula ventas |
| analyst | Solo lectura: reportes, caja, inventario, catálogo | No puede operar caja ni hacer ventas |
| cashier | POS, caja, pedidos, clientes | Sin acceso a costos ni bóveda |
| admin | (heredado — no en producción activa) | — |

### ▲ Roles en producción (Chaguaramas + El Buen Corte)
| Rol | Usado en producción |
|-----|---------------------|
| owner | master@matriz.com |
| branch_admin | admin@sucursal.com |
| analyst | conta@matriz.com |
| cashier | caja@matriz.com, caja@sucursal.com |

### Permisos por módulo
```javascript
const rolePermissions = {
    super_admin:  ['*'],
    owner:        ['dashboard','pos','inventory','boveda','fabrica','orders','sales','dayclose','catalog','clients','contingency','users','settings','cash','reports'],
    branch_admin: ['dashboard','pos','inventory','boveda','fabrica','orders','sales','dayclose','catalog','clients','contingency','users','settings','cash'],
    supervisor:   ['dashboard','pos','cash','sales','dayclose','inventory','catalog','boveda','fabrica','orders','clients','reports','contingency'],
    analyst:      ['dashboard','sales','dayclose','cash','reports','inventory','catalog','clients','orders','contingency'],
    cashier:      ['pos','caja','pedidos','clientes'],
}
```

**▲ Branch picker (AppLayout.vue L348/L380):** visible solo para `['super_admin','owner']`. `branch_admin` eliminado en sesión 02/06/2026 — ve su sucursal fija.

**navOwner:** Navegación alternativa para owner y branch_admin — Panel Empresarial arriba.

---

## 12. Tags de restauración

| Tag | Descripción |
|-----|-------------|
| v1.0-certificado | POS base + caja + ventas certificadas |
| v1.1-caja-certificada | Caja completa con corte bancario |
| v1.2-ciclo-caja | Ciclo caja completo (open→close) |
| v1.2-pair-id | pair_id Canal 1/Canal 2 |
| v1.3-canal-par | Lógica par canal completa |
| v1.4-carne-canal | Pool stock_product_id — Premium/Primera/Segunda |
| v1.5-fabrica-4cortes | Fábrica con 4 cortes Res normalizados |
| v1.5-widget-canal | Widget canal en Dashboard |
| v1.6-reportes-costo | Reportes con costo real de bóveda |
| v1.6-stress-109 | 109 acciones de stress test sin errores |
| v1.7-pos-stock-check | POS con validación de stock en tiempo real |
| v1.8-entrega-final | Entrega cliente — flujo completo |
| v1.8-reportes-drilldown | Reportes con drill-down por categoría |
| v1.9-sale-catalog-fix | Fix ventas + catálogo multi-sucursal |
| v1.9.1-favoritos-carnetotal | Favoritos + carnet total en POS |
| v1.9.2-payment-methods | Métodos de pago configurables |
| v1.9.3-responsive-team | Equipo responsive |
| v2.0-certificacion | Certificación 45/45 PASS |
| v2.0.2-paymentmethods-backfill | Backfill payment_methods |
| v2.1-certificado-45-45 | Re-certificación post-refactor |
| v2.1-normalizado | Normalización catMap/resOrder |
| v2.2-responsive-completo | Responsive completo pre-branches |
| v2.3-branch-certificado-final | Multi-sucursal branch_id certificado |
| v2.3-certificado-final | Certificación final flujo + audit |
| v2.3-flujo-certificado | 15/16 flujo POS→pago→stock |
| v2.4-canal-despiece-fix | Fix canalRendimiento + margen_pct |
| v2.5-dashboard-reportes-fix | Dashboard categorias_hoy + reportes fix |
| v2.7-responsive-ecosistema-completo | Responsive en 33+ vistas Vue |
| v2.8-mobile-cards-global | .mobile-cards global en app.css |
| v2.9-scanner-barcode | Scanner EAN-13/Code128 en POS · branch_id en 6 tablas nuevas |
| ▲▲ v3.3-pivot-usd-bs | Pivote USD↔Bs en Catálogo e Inventario · branch picker owner-only · schedule bancario 19:00-19:30 |
| ▲ v3.4-roccia-certificado | Báscula Roccia ROP-30 certificada · guard stock negativo · carrito kg/Bs/USD · catMap Pollo · price_per_kg_bs · Pedrero creado · Hígado restaurado |

---

## 13. Reglas críticas — NO VIOLAR

### PHP
- `declare(strict_types=1)` en TODO archivo PHP sin excepción
- Early return obligatorio — nunca nesting > 2 niveles
- Eager loading obligatorio — cero N+1 toleradas
- NUNCA `asset()` → siempre `@vite()`
- NUNCA `exec()`, `shell_exec()`, `eval()`

### Vue
- SIEMPRE `<script setup>` — Options API prohibida
- CSS vars para colores: `var(--brand)`, `var(--bg-card)`, `var(--text-primary)`
- NUNCA colores hardcodeados en componentes
- Iconos: SIEMPRE Lucide Vue Next — NUNCA emojis, NUNCA FontAwesome

### Moneda — CRÍTICO
- Negocio opera en Bolívares (Bs.) al cobrar al cliente
- Precios en USD solo referencia interna
- Al vender: `price_usd × tasa_del_día = total_bs`
- DB guarda: price_usd + rate_used + total_bs
- NUNCA bloquear venta por falta de tasa

### Flujo — IRROMPIBLE
- Bóveda → Fábrica/Despiece → Vitrina → POS → Cierre
- location=boveda: SOLO visible en /boveda y /despiece
- POS filtra: `->where('location', '!=', 'boveda')`
- Stock descuenta SOLO cuando `sale.status = 'paid'`

### Git y código
- NUNCA crear worktrees — todo directo en main
- NUNCA tocar `C:\laragon\www\synticorex`
- Máximo 1 archivo modificado por request salvo instrucción explícita
- NUNCA correr suite completo de tests (Breeze + RefreshDatabase borra DB)
- ▲ NUNCA usar `sed` para editar código — usar Edit tool o edición directa

### DB
- NUNCA modificar migraciones ya corridas — crear nuevas
- Campos monetarios: `decimal(10,2)` con sufijo `_usd`
- Campos de peso: `decimal(8,3)`

---

## 14. Certificación

| Área | Estado |
|------|--------|
| Stress test | ▲ 145/145 PASS |
| Branch ISO | ▲ 11/11 — Bóveda→Fábrica→Vitrina certificado (ver detalle §14.1) |
| Flujo POS→pago→stock | 15/16 (1 edge case delivery pendiente) |
| Audit branch_id | 13/13 tablas certificadas |
| Responsive | 33 vistas normalizadas — 640px/1023px |
| Scanner Roccia ROP-30 | ▲ Certificado en DESA · pendiente prueba producción |
| Guard stock negativo | ▲ Activo en SaleController |
| Roles producción | owner/analyst/cashier/branch_admin activos |

### 14.1 — Branch ISO 11/11 — Detalle flujo completo

**Condición inicial:** 20 productos vitrina (Res/Pollo/Cerdo) con inventory_entries eliminadas.

**Bóveda (3 entradas surtidas a Fábrica):**
| Entry | ID | kg_entrada | Status |
|-------|----|------------|--------|
| RES - Medio Canal | 917 | 50 kg | 200 OK |
| POLLO - Entero Congelado | 918 | 20 kg | 200 OK |
| CERDO - Canal | 919 | 30 kg | 200 OK |

> Nota: BovedaProduct POLLO tenía `requires_despiece=false` → corregido a `true` para habilitar despiece.

**Despiece (FabricaController::storeDespiece):**
| Animal | Cortes | Total doc. | Merma |
|--------|--------|-----------|-------|
| RES | Carne Total 20 · Costilla 10 · H.Redondo 10 · H.Rojo 8 | 48 kg | 2 kg |
| POLLO | Picado 5 · Muslo 5 · Pechuga 5 · Alas 3 · Molleja 2 | 20 kg | 0 kg |
| CERDO | Chuleta 15 · Costilla 14 | 29 kg | 1 kg |

**Stock vitrina resultante (11 productos):**
| Producto | Stock |
|----------|-------|
| Carne Total | 20.000 kg |
| Costilla (Res) | 10.000 kg |
| Hueso Redondo | 10.000 kg |
| Hueso Rojo | 8.000 kg |
| Ala | 3.000 kg |
| Molleja | 2.000 kg |
| Muslo | 5.000 kg |
| Pechuga | 5.000 kg |
| Pollo Picado | 5.000 kg |
| Chuleta de Cerdo | 15.000 kg |
| Costilla de Cerdo | 14.000 kg |

**Commit v2.9:** `2d42bf2` — `feat(pos): scanner de báscula multi-estándar EAN-13/Code128`
**▲▲ Commit v3.3:** `6751b4c` — `feat(catalog+inventory): campo pivote USD↔Bs con tasa BCV en tiempo real`
**▲ Commit v3.4:** `4d2f599` — `feat(pos): scanner Roccia ROP-30 certificado + carrito Bs grande`

### ▲ Bugs cerrados sesión 03–05/06/2026
| Commit | Fix |
|--------|-----|
| e5226a3 | Selector bóveda >20 productos |
| 0d84445 | productosVitrina global + unique |
| bc7b285 | Guard pivote Carne Total |
| 8e97b06 | Ventas stock negativo bloqueadas |
| 00b1c38 | Carrito muestra kg · Bs · USD |
| f656a1a | catMap Pollo + polloOrder Fábrica |
| c6b2dd5 | polloOrder planilla Bóveda |
| 3534561 | Surtir destino Fábrica/Vitrina |
| f77c864 | Test despiece 34/34 certificado |
| 38eaae8 | Planilla catMap detección por nombre |
| 4d2f599 | Scanner Roccia ROP-30 certificado |

### ▲ Pendientes tras sesión 05/06/2026
- Simulador POS en Hardware.vue (mostrar producto + kg + Bs al escanear código EAN-13 manual)
- Pull en VPS — stash pendiente de resolver
- Catálogo busca por sku y barcode
- 4 decimales en price_per_kg_usd input catálogo
- Prueba báscula Roccia ROP-30 en producción

---
*Generado: 2026-06-05 | VPS HEAD: 4d2f599 | Próximo punto de restauración recomendado: antes de simulador báscula Hardware.vue*
