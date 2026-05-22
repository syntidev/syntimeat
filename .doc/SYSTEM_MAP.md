# SYSTEM MAP — SYNTImeat
Generado: 2026-05-22

---

## 1. Controllers

### BovedaController
| Línea | Método | Verb | Ruta |
|-------|--------|------|------|
| 22 | `index()` | GET | /boveda |
| 85 | `store()` | POST | /boveda |
| 144 | `surte()` | PATCH | /boveda/{entry}/surtir |
| 258 | `close()` | PATCH | /boveda/{entry}/cerrar |
| 284 | `registerMerma()` | PATCH | /boveda/{entry}/merma |
| 327 | `storeProduct()` | POST | /boveda/productos |
| 362 | `updateProduct()` | PUT | /boveda/productos/{product} |
| 399 | `plantillaDespiece()` | GET | /boveda/{entry}/plantilla |
| 450 | `destroyProduct()` | DELETE | /boveda/productos/{product} |

**Request validated fields:**
- `store()`: product_type, description, kg_entrada, costo_usd, supplier, entered_at
- `surte()`: peso_real
- `registerMerma()`: peso_actual
- `storeProduct()`: name, unit, requires_despiece, vitrina_product_id
- `updateProduct()`: name, unit, requires_despiece, vitrina_product_id

**Inertia::render props (`index()`):**
- activas, historial, bovedaProducts, productosVitrina, kpis{entradasActivas, kgDisponible, costoActivo, surtidoHoy}

---

### FabricaController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /fabrica |
| `store()` | POST | /fabrica |
| `storeDespiece()` | POST | /fabrica/despiece |

**Request validated fields:**
- `store()`: output_product_id, output_kg, output_units, inputs[].product_id, inputs[].quantity_kg, inputs[].cost_usd, notes, produced_at
- `storeDespiece()`: boveda_entry_id, cortes[].product_id (Rule::exists scoped a business_id), cortes[].kg, notes

**Inertia::render props (`index()`):**
- fabricables, ingredientes, stockMap, historial, despiecePendiente, despieceHistorial

---

### SaleController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /pos |
| `store()` | POST | /pos/ventas |
| `pay()` | PATCH | /pos/ventas/{sale}/pagar |
| `cancel()` | PATCH | /pos/ventas/{sale}/cancelar |
| `void()` | PATCH | /ventas/{sale}/anular |
| `historial()` | GET | /ventas |

**Request validated fields:**
- `store()`: items[].product_id, items[].input_type, items[].amount_bs, items[].quantity_value, origin, channel, status, client_name, client_phone, client_id
- `cancel()`: cancellation_reason (min:5)
- `void()`: motivo (min:5)

**Inertia::render props (`index()`):**
- products, categories, cashRegister, todayRate, paymentMethods, ticketPrefix, stockMap, posShowKg, businessInfo, ticketPrefs

---

### CashRegisterController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /caja |
| `open()` | POST | /caja/abrir |
| `close()` | POST | /caja/{register}/cerrar |
| `movement()` | POST | /caja/{register}/movimiento |
| `dayClose()` | GET | /caja/cierre |
| `confirmClose()` | POST | /caja/cierre/{register} |

**Request validated fields:**
- `open()`: opening_amount_bs
- `close()`: counted_cash_bs, notes
- `movement()`: type (in/out), amount_bs, concept
- `confirmClose()`: counted_cash_bs, notes

**Inertia::render props (`index()`):**
- cashRegister, allOpenRegisters, history, kpis{expected_bs, sales_total_bs, movements_count, rate}, todayRate, isAdmin

---

### CatalogController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /catalogo |
| `store()` | POST | /catalogo/productos |
| `update()` | PUT | /catalogo/productos/{product} |
| `destroy()` | DELETE | /catalogo/productos/{product} |
| `toggleFavorite()` | PATCH | /catalogo/productos/{product}/favorito |
| `storeCategory()` | POST | /catalogo/categorias |
| `updateCategory()` | PUT | /catalogo/categorias/{category} |
| `destroyCategory()` | DELETE | /catalogo/categorias/{category} |
| `storeSubcategory()` | POST | /catalogo/subcategorias |
| `updateSubcategory()` | PUT | /catalogo/subcategorias/{subcategory} |
| `destroySubcategory()` | DELETE | /catalogo/subcategorias/{subcategory} |

**Request validated fields (store/update producto):**
- name, sku, category_id, subcategory_id, sale_mode, price_per_kg_usd, price_per_unit_usd, location, active, fabricable, image (file)

**Inertia::render props (`index()`):**
- categories (con subcategorías), products

---

### InventoryController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /inventario |
| `store()` | POST | /inventario |

**Request validated fields (`store()`):**
- product_id, quantity_kg, waste_kg, cost_per_kg_usd, supplier, notes, location, entered_at

**Inertia::render props (`index()`):**
- products, categories, todayEntries, stockMap, lastEntryMap, kpis

---

### DashboardController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /dashboard |
| `data()` | GET | /dashboard/data (JSON) |

**Inertia::render props (`index()`):**
- ventas_hoy, top_productos, stock_critico, ultimas_ventas, caja_activa, tasa_hoy, pedidos_pendientes, categorias_hoy, utilidad_boveda

---

### OrderController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /pedidos |
| `store()` | POST | /pedidos |
| `collect()` | PATCH | /pedidos/{order}/cobrar |
| `dispatch()` | PATCH | /pedidos/{order}/despachar |
| `cancel()` | PATCH | /pedidos/{order}/cancelar |
| `deliveryIndex()` | GET | /pedidos/delivery |
| `confirmDelivery()` | PATCH | /ventas/{sale}/delivery-cobrado |
| `collectPending()` | PATCH | /ventas/{sale}/cobrar-pendiente |

**Request validated fields:**
- `store()`: client_name, client_type, items[].product_id, items[].quantity_value, notes
- `collect()`: payment_method_id, amount_bs, reference, rate
- `cancel()`: motivo
- `collectPending()`: payments[], rate

**Inertia::render props (`index()`):**
- pedidosActivos, historial, cobrosPendientes, products, paymentMethods, paymentTerminals, todayRate, kpis

---

### ReportController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /reportes |
| `sales()` | GET | /reportes/ventas (JSON) |
| `inventory()` | GET | /reportes/inventario (JSON) |
| `closings()` | GET | /reportes/cierres (JSON) |
| `orders()` | GET | /reportes/pedidos (JSON) |
| `dayReport()` | GET | /reportes/dia (JSON) |
| `exportDayPdf()` | GET | /reportes/pdf-dia (PDF) |
| `consolidated()` | GET | /reportes/consolidado |
| `consolidatedData()` | GET | /reportes/consolidado/data (JSON) |
| `export()` | GET | /reportes/exportar (XLSX) |

**Filtros comunes:** date_from, date_to, cashier_id, payment_method, status

---

### ClientController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /clientes |
| `store()` | POST | /clientes |
| `update()` | PUT | /clientes/{client} |
| `show()` | GET | /clientes/{client} |
| `search()` | GET | /clientes/buscar (JSON) |

**Request validated fields:**
- `store()/update()`: cedula, name, phone, email, address, notes

---

### SettingsController
| Método | Verb | Ruta |
|--------|------|------|
| `general()` | GET | /configuracion/general |
| `updateGeneral()` | POST | /configuracion/general |
| `cashRegisters()` | GET | /configuracion/cajas |
| `storeCashRegister()` | POST | /configuracion/cajas |
| `updateCashRegister()` | PUT | /configuracion/cajas/{cashRegister} |
| `destroyCashRegister()` | DELETE | /configuracion/cajas/{cashRegister} |
| `terminals()` | GET | /configuracion/terminales |
| `storeTerminal()` | POST | /configuracion/terminales |
| `updateTerminal()` | PUT | /configuracion/terminales/{terminal} |
| `destroyTerminal()` | DELETE | /configuracion/terminales/{terminal} |
| `ticket()` | GET | /configuracion/ticket |
| `updateTicket()` | POST | /configuracion/ticket |
| `branches()` | GET | /configuracion/sucursales |
| `storeBranch()` | POST | /configuracion/sucursales |
| `updateBranch()` | PUT | /configuracion/sucursales/{branch} |
| `users()` | GET | /configuracion/usuarios |
| `storeUser()` | POST | /configuracion/usuarios |
| `updateUser()` | PUT | /configuracion/usuarios/{user} |
| `destroyUser()` | DELETE | /configuracion/usuarios/{user} |
| `setManualRate()` | POST | /tasa/manual |

---

### PaymentMethodController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /configuracion/metodos-pago |
| `store()` | POST | /configuracion/metodos-pago |
| `update()` | PUT | /configuracion/metodos-pago/{paymentMethod} |
| `toggle()` | PATCH | /configuracion/metodos-pago/{paymentMethod}/toggle |
| `destroy()` | DELETE | /configuracion/metodos-pago/{paymentMethod} |
| `reorder()` | POST | /configuracion/metodos-pago/reorder |

---

### ContingencyController
| Método | Verb | Ruta |
|--------|------|------|
| `index()` | GET | /contingencia |
| `downloadForm()` | GET | /contingencia/formato-papel |
| `downloadTemplate()` | GET | /contingencia/plantilla-ventas |
| `downloadInventoryTemplate()` | GET | /contingencia/plantilla-inventario |
| `importSales()` | POST | /contingencia/importar-ventas |
| `importInventory()` | POST | /contingencia/importar-inventario |

---

### OnboardingController
| Método | Verb | Ruta |
|--------|------|------|
| `show()` | GET | /setup |
| `store()` | POST | /setup/{step} |

---

## 2. Models

### Business
**fillable:** name, legal_name, rif, logo_path, address, city, state, phone, currency_default, rate_source, rate_margin, weight_unit, ticket_prefix, ticket_footer, sale_capture_mode, line_input_mode, preticket_enabled, preticket_expiry_minutes, price_lock_policy, onboarding_completed, active, max_branches, settings, theme_color

**casts:** rate_margin:decimal:2, preticket_enabled:bool, onboarding_completed:bool, active:bool, max_branches:int, settings:array

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| hasMany | users() | User |
| hasMany | branches() | Branch |
| hasMany | categories() | Category |
| hasMany | products() | Product |
| hasMany | inventoryEntries() | InventoryEntry |
| hasMany | cashRegisters() | CashRegister |
| hasMany | sales() | Sale |
| hasMany | orders() | Order |
| hasMany | activityLogs() | ActivityLog |
| hasMany | paymentMethods() | PaymentMethod |
| hasMany | paymentTerminals() | PaymentTerminal |

---

### User
**fillable:** name, email, password, business_id, branch_id, role, theme, is_active, session_token, access_start, access_end, access_days

**roles:** super_admin, owner, branch_admin, analyst, supervisor, cashier, admin

**casts:** email_verified_at:datetime, password:hashed, access_days:array

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| belongsTo | business() | Business |
| belongsTo | branch() | Branch |
| hasMany | sales() | Sale (FK: cashier_id) |
| hasMany | orders() | Order (FK: created_by) |

**helpers de rol:** isSuperAdmin(), isOwner(), isAdmin(), isCashier(), hasRole(), canManageBusiness(), canVoidSales(), seesTodasLasSucursales()

---

### Product
**fillable:** business_id, branch_id, category_id, subcategory_id, name, sku, barcode, sale_mode, base_unit_label, fraction_allowed, price_per_kg_usd, price_per_unit_usd, min_stock, location, image_path, sort_order, active, fabricable, is_favorite

**casts:** fraction_allowed:bool, fabricable:bool, price_per_kg_usd:decimal:2, price_per_unit_usd:decimal:2, active:bool, is_favorite:bool

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| belongsTo | business() | Business |
| belongsTo | branch() | Branch |
| belongsTo | category() | Category |
| belongsTo | subcategory() | Subcategory |
| hasMany | inventoryEntries() | InventoryEntry |
| hasMany | saleItems() | SaleItem |
| hasMany | orderItems() | OrderItem |

---

### Sale
**fillable:** business_id, ticket_number, status, total_usd, payment_method, amount_received_usd, change_usd, rate_used, total_bs, notes, sold_at, cashier_id, cash_register_id, cancelled_at, cancelled_by, cancellation_reason, client_name, client_phone, client_id, origin, channel, delivery_status, delivery_confirmed_at, payment_status, order_id

**with:** items (eager)

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| belongsTo | business() | Business |
| belongsTo | cashier() | User (FK: cashier_id) |
| belongsTo | cashRegister() | CashRegister |
| belongsTo | canceller() | User (FK: cancelled_by) |
| belongsTo | order() | Order |
| hasMany | items() | SaleItem |
| hasMany | salePayments() | SalePayment |

---

### SaleItem
**fillable:** sale_id, product_id, product_name, input_type, quantity_value, unit_label, price_per_kg_usd, price_per_unit_usd, subtotal_usd, subtotal_bs, rate_used, discount_usd

**relaciones:** belongsTo sale(), belongsTo product()

---

### Order
**fillable:** business_id, client_name, client_type, status, total_usd, notes, created_by

**with:** items (eager)

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| belongsTo | business() | Business |
| belongsTo | creator() | User (FK: created_by) |
| hasMany | items() | OrderItem |
| hasOne | sale() | Sale |

---

### CashRegister
**fillable:** business_id, branch_id, name, opened_at, closed_at, opening_amount_usd, opening_amount_bs, expected_cash_usd, counted_cash_usd, difference_usd, rate_at_opening, notes, opened_by, closed_by

**relaciones:**
| Tipo | Método | Modelo |
|------|--------|--------|
| belongsTo | business() | Business |
| belongsTo | branch() | Branch |
| belongsTo | opener() | User (FK: opened_by) |
| belongsTo | closer() | User (FK: closed_by) |
| hasMany | sales() | Sale |
| hasMany | movements() | CashMovement |

---

### CashMovement
**fillable:** cash_register_id, type (in/out/corte), amount_usd, amount_bs, concept, created_by

**relaciones:** belongsTo cashRegister(), belongsTo creator() (User)

---

### InventoryEntry
**fillable:** business_id, product_id, boveda_entry_id, quantity_kg, waste_kg, cost_per_kg_usd, supplier, notes, location, entered_at, created_by

**casts:** quantity_kg:decimal:3, waste_kg:decimal:3, net_kg:decimal:3 (columna virtual DB)

**relaciones:** belongsTo business(), belongsTo product(), belongsTo bovedaEntry(), belongsTo creator() (User)

---

### BovedaEntry
**fillable:** business_id, product_type, description, kg_entrada, costo_usd, waste_kg, kg_surtido_vitrina, supplier, entered_at, closed_at, despiece_completado_at

**casts:** kg_disponible:decimal:3 (columna generada DB = kg_entrada - kg_surtido_vitrina - waste_kg)

**relaciones:** belongsTo business(), hasMany inventoryEntries(), hasOne bovedaProduct() (FK: name↔product_type)

**scope:** scopeActive() → whereNull('closed_at')

---

### BovedaProduct
**fillable:** business_id, name, unit, active, sort_order, requires_despiece, vitrina_product_id

**relaciones:** belongsTo business()

---

### FabricaBatch
**fillable:** business_id, created_by, output_product_id, output_kg, output_units, input_cost_usd, notes, produced_at

**relaciones:** belongsTo business(), belongsTo creator() (User), belongsTo outputProduct() (Product), hasMany inputs() (FabricaInput)

---

### FabricaInput
**fillable:** fabrica_batch_id, product_id, despiece_item_id, inventory_entry_id, label, quantity_kg, cost_usd

**relaciones:** belongsTo batch() (FabricaBatch), belongsTo product(), belongsTo despieceItem(), belongsTo inventoryEntry()

---

### Category
**fillable:** business_id, name, icon, color, macro_category, sort_order, active

**relaciones:** belongsTo business(), hasMany subcategories() (ordered by sort_order), hasMany products()

---

### Client
**fillable:** business_id, cedula, name, phone, email, address, notes, active

**relaciones:** belongsTo business(), hasMany sales()

---

### Branch
**fillable:** business_id, name, address, city, phone, is_active, access_start, access_end

**relaciones:** belongsTo business(), hasMany users(), hasMany sales(), hasMany cashRegisters()

---

### ActivityLog
**fillable:** business_id, user_id, action, model_type, model_id, old_values, new_values, ip_address

**casts:** old_values:array, new_values:array

**relaciones:** belongsTo business(), belongsTo user()

---

### DollarRate
**fillable:** rate, source (bcv/parallel/negotiated/manual), currency_type (USD/EUR), effective_from, effective_until, is_active

**scopes:** scopeUsd(), scopeEur()

**nota:** UPDATED_AT = null

---

### PaymentMethod
**fillable:** business_id, name, type, bank_name, is_active, sort_order

**relaciones:** belongsTo business()

---

## 3. Services

### DollarRateService
| Método | Firma | Propósito |
|--------|-------|-----------|
| `getTodayRate()` | `(string $source = 'bcv'): float` | Tasa del día, fallback a última disponible |
| `getLatestRate()` | `(string $source = 'bcv'): float` | Última tasa activa sin importar fecha |
| `fetchAndStore()` | `(): array{success, rate?, source?, message}` | Consulta APIs y persiste nueva tasa USD |
| `storeManualRate()` | `(float $rate): bool` | Graba tasa manual del admin |
| `formatBs()` | `(float $usd, float $rate): float` | usd × rate, redondeado a 2 decimales |
| `getSources()` | `(): string[]` | ['bcv', 'parallel', 'negotiated', 'manual'] |

**Constantes:** FALLBACK = 40.00 | MAX_CHANGE_PCT = 60% | CACHE_TTL = 3600s

---

### CurrencyFetcherService
| Método | Firma | Propósito |
|--------|-------|-----------|
| `fetchUSD()` | `(): array{success, rate, source}` | Obtiene BCV oficial USD — fuentes: dolarapi.com → brecha-cambiaria.com |
| `fetchEUR()` | `(): array{success, rate, source}` | Obtiene BCV oficial EUR — mismas fuentes |

---

## 4. Middleware

| Clase | Alias | Propósito |
|-------|-------|-----------|
| `EnsureRole` | `role` | Verifica que `user->role` esté en los roles permitidos, abort 403 si no |
| `CheckOnboarding` | `check.onboarding` | Redirige a /setup si business no tiene onboarding_completed |
| `EnforceUserSession` | (global) | Verifica is_active, sesión única por token, días habilitados, ventana horaria |
| `HandleInertiaRequests` | (global) | Inyecta auth.user, flash, tasa en shared props de Inertia |

---

## 5. Rutas

### Públicas (sin auth)
| URI | Verb | Controller@método | Nombre |
|-----|------|-------------------|--------|
| / | GET | closure (Welcome) | — |
| /setup | GET | OnboardingController@show | onboarding |
| /setup/{step} | POST | OnboardingController@store | onboarding.step |

### Autenticadas — Todos los roles (super_admin, admin, cashier)
| URI | Verb | Controller@método | Nombre |
|-----|------|-------------------|--------|
| /dashboard | GET | DashboardController@index | dashboard |
| /dashboard/data | GET | DashboardController@data | dashboard.data |
| /pos | GET | SaleController@index | pos.index |
| /pos/ventas | POST | SaleController@store | sales.store |
| /pos/ventas/{sale}/pagar | PATCH | SaleController@pay | sales.pay |
| /pos/ventas/{sale}/cancelar | PATCH | SaleController@cancel | sales.cancel |
| /ventas | GET | SaleController@historial | sales.index |
| /caja | GET | CashRegisterController@index | cash.index |
| /caja/abrir | POST | CashRegisterController@open | cash.open |
| /caja/{register}/cerrar | POST | CashRegisterController@close | cash.close |
| /caja/{register}/movimiento | POST | CashRegisterController@movement | cash.movement |
| /caja/cierre | GET | CashRegisterController@dayClose | cash.day-close |
| /clientes | GET | ClientController@index | clients.index |
| /clientes | POST | ClientController@store | clients.store |
| /clientes/buscar | GET | ClientController@search | clients.search |
| /clientes/{client} | GET | ClientController@show | clients.show |
| /clientes/{client} | PUT | ClientController@update | clients.update |
| /pedidos | GET | OrderController@index | orders.index |
| /pedidos | POST | OrderController@store | orders.store |
| /pedidos/delivery | GET | OrderController@deliveryIndex | orders.delivery |
| /pedidos/{order}/cobrar | PATCH | OrderController@collect | orders.collect |
| /pedidos/{order}/despachar | PATCH | OrderController@dispatch | orders.dispatch |
| /pedidos/{order}/cancelar | PATCH | OrderController@cancel | orders.cancel |
| /pedidos/{sale}/delivery-cobrado | PATCH | OrderController@confirmDelivery | sales.delivery-confirm |
| /ventas/{sale}/cobrar-pendiente | PATCH | OrderController@collectPending | sales.collect-pending |
| /profile | GET | ProfileController@edit | profile.edit |
| /profile | PATCH | ProfileController@update | profile.update |
| /profile | DELETE | ProfileController@destroy | profile.destroy |

### super_admin + admin
| URI | Verb | Controller@método | Nombre |
|-----|------|-------------------|--------|
| /tasa/manual | POST | SettingsController@setManualRate | rate.manual |
| /caja/cierre/{register} | POST | CashRegisterController@confirmClose | cash.confirm-close |
| /ventas/{sale}/anular | PATCH | SaleController@void | sales.void |
| /catalogo | GET | CatalogController@index | catalog.index |
| /catalogo/productos | POST | CatalogController@store | catalog.store |
| /catalogo/productos/{product} | PUT | CatalogController@update | catalog.update |
| /catalogo/productos/{product} | DELETE | CatalogController@destroy | catalog.destroy |
| /catalogo/productos/{product}/favorito | PATCH | CatalogController@toggleFavorite | catalog.product.favorite |
| /catalogo/categorias | POST | CatalogController@storeCategory | catalog.category.store |
| /catalogo/categorias/{category} | PUT | CatalogController@updateCategory | catalog.category.update |
| /catalogo/categorias/{category} | DELETE | CatalogController@destroyCategory | catalog.category.destroy |
| /catalogo/subcategorias | POST | CatalogController@storeSubcategory | catalog.subcategory.store |
| /catalogo/subcategorias/{subcategory} | PUT | CatalogController@updateSubcategory | catalog.subcategory.update |
| /catalogo/subcategorias/{subcategory} | DELETE | CatalogController@destroySubcategory | catalog.subcategory.destroy |
| /fabrica | GET | FabricaController@index | fabrica.index |
| /fabrica | POST | FabricaController@store | fabrica.store |
| /fabrica/despiece | POST | FabricaController@storeDespiece | fabrica.despiece |
| /inventario | GET | InventoryController@index | inventory.index |
| /inventario | POST | InventoryController@store | inventory.store |
| /boveda | GET | BovedaController@index | boveda.index |
| /boveda | POST | BovedaController@store | boveda.store |
| /boveda/{entry}/surtir | PATCH | BovedaController@surte | boveda.surte |
| /boveda/{entry}/cerrar | PATCH | BovedaController@close | boveda.close |
| /boveda/{entry}/merma | PATCH | BovedaController@registerMerma | boveda.merma |
| /boveda/{entry}/plantilla | GET | BovedaController@plantillaDespiece | boveda.plantilla |
| /boveda/productos | POST | BovedaController@storeProduct | boveda.product.store |
| /boveda/productos/{product} | PUT | BovedaController@updateProduct | boveda.product.update |
| /boveda/productos/{product} | DELETE | BovedaController@destroyProduct | boveda.product.destroy |
| /reportes | GET | ReportController@index | reports.index |
| /reportes/ventas | GET | ReportController@sales | reports.sales |
| /reportes/inventario | GET | ReportController@inventory | reports.inventory |
| /reportes/cierres | GET | ReportController@closings | reports.closings |
| /reportes/pedidos | GET | ReportController@orders | reports.orders |
| /reportes/dia | GET | ReportController@dayReport | reports.day |
| /reportes/pdf-dia | GET | ReportController@exportDayPdf | reports.day-pdf |
| /reportes/exportar | GET | ReportController@export | reports.export |
| /configuracion/metodos-pago | GET | PaymentMethodController@index | payment-methods.index |
| /configuracion/metodos-pago | POST | PaymentMethodController@store | payment-methods.store |
| /configuracion/metodos-pago/{pm} | PUT | PaymentMethodController@update | payment-methods.update |
| /configuracion/metodos-pago/{pm}/toggle | PATCH | PaymentMethodController@toggle | payment-methods.toggle |
| /configuracion/metodos-pago/{pm} | DELETE | PaymentMethodController@destroy | payment-methods.destroy |
| /configuracion/metodos-pago/reorder | POST | PaymentMethodController@reorder | payment-methods.reorder |
| /configuracion/general | GET | SettingsController@general | settings.general |
| /configuracion/general | POST | SettingsController@updateGeneral | settings.general.update |
| /configuracion/cajas | GET | SettingsController@cashRegisters | settings.cash-registers |
| /configuracion/cajas | POST | SettingsController@storeCashRegister | settings.cash-registers.store |
| /configuracion/terminales | GET | SettingsController@terminals | settings.terminals |
| /configuracion/ticket | GET | SettingsController@ticket | settings.ticket |
| /configuracion/ticket | POST | SettingsController@updateTicket | settings.ticket.update |
| /configuracion/sucursales | GET | SettingsController@branches | settings.branches |
| /configuracion/sucursales | POST | SettingsController@storeBranch | settings.branches.store |
| /contingencia | GET | ContingencyController@index | contingency.index |
| /contingencia/importar-ventas | POST | ContingencyController@importSales | contingency.import-sales |
| /contingencia/importar-inventario | POST | ContingencyController@importInventory | contingency.import-inventory |

### Solo super_admin + owner
| URI | Verb | Controller@método | Nombre |
|-----|------|-------------------|--------|
| /reportes/consolidado | GET | ReportController@consolidated | reports.consolidated |
| /reportes/consolidado/data | GET | ReportController@consolidatedData | reports.consolidated-data |

### Solo super_admin
| URI | Verb | Controller@método | Nombre |
|-----|------|-------------------|--------|
| /configuracion/usuarios | GET | SettingsController@users | settings.users |
| /configuracion/usuarios | POST | SettingsController@storeUser | settings.users.store |
| /configuracion/usuarios/{user} | PUT | SettingsController@updateUser | settings.users.update |
| /configuracion/usuarios/{user} | DELETE | SettingsController@destroyUser | settings.users.destroy |

---

## 6. Vue Pages

### POS/Index.vue
**props:** products, categories, cashRegister, todayRate, paymentMethods, ticketPrefix, stockMap, posShowKg, businessInfo, ticketPrefs

**refs principales:** tickets (multi-ticket), activeTicket, selectedCat, search, soloConStock, qtyModal, qtyProduct, payModal, payments, saleOrigin, showClientFields, clientId, clientName, clientPhone, successModal, successItems, showMobileCart

---

### Boveda/Index.vue
**props:** activas, historial, bovedaProducts, productosVitrina, kpis

**refs principales:** tab, flash, showEntradaModal, entradaForm, showSurtirModal, surtirEntry, surtirForm, surtirErrors, despiecePendiente, closing, showProductModal, editingProduct, productForm, localBovedaProducts, showHelp

---

### Fabrica/Index.vue
**props:** fabricables, ingredientes, stockMap, historial, despiecePendiente, despieceHistorial

**refs principales:** tab, showModal, modalProduct, ingredSearch, despieceExpanded, despieceForms, despieceErrors, despieceSaving, despieceFlash, despiecePdfEntry, showHelp

---

### Cash/Index.vue
**props:** cashRegister, allOpenRegisters, history, kpis, todayRate, isAdmin

**refs principales:** activeTab, openModal, movModal, corteModal, showHelp

---

### Cash/DayClose.vue
**props:** (leída desde CashRegisterController@dayClose)

---

### Catalog/Index.vue
**props:** categories, products

**refs principales:** activeTab, searchQuery, showModal, editProduct, submitting, selectedImagePreview, mainTab, showCatModal, editCategory, showSubModal, editSubcat, subParentId, showHelp

---

### Inventory/Index.vue
**props:** products, categories, todayEntries, stockMap, lastEntryMap, kpis

**refs principales:** search, filterCat, filterStatus, sortKey, sortDir, currentPage, drawerProduct, showModal, selectedCategory, showHelp

---

### Dashboard.vue
**props:** ventas_hoy, top_productos, stock_critico, ultimas_ventas, caja_activa, tasa_hoy, pedidos_pendientes, categorias_hoy, utilidad_boveda

**refs principales:** d (reactive snapshot de props), barsVisible, selectedCats, horaActual

---

### Sales/Index.vue
**props:** sales, totals, cashiers, paymentMethods, filters

**refs principales:** filterDate, filterCashier, filterPayment

---

### Orders/Index.vue
**props:** pedidosActivos, historial, cobrosPendientes, products, paymentMethods, paymentTerminals, todayRate, kpis

**refs principales:** pedidos (reactive copy), showModal, cobroModal

---

### Clients/Index.vue
**props:** clients, selectedClient (show mode), salesHistory

---

### Reports/Index.vue
**props:** paymentMethods, cashiers

---

### Reports/Consolidado.vue
**props:** branches, initialData

---

### Contingency/Index.vue
**props:** (sin props externas — descarga archivos)

---

### Settings/* (General, Team, Users, PaymentMethods, CashRegisters, Terminals, Ticket, Branches)
**patrón común:** props de configuración del módulo correspondiente, sin refs de estado complejo

---

### Auth/* (Login, Register, ForgotPassword, ResetPassword, ConfirmPassword, VerifyEmail)
**props:** status, errors — componentes Breeze estándar

---

## 7. Migraciones

| Archivo | Tabla | Operación |
|---------|-------|-----------|
| 0001_01_01_000000_create_users_table | users | CREATE |
| 0001_01_01_000001_create_cache_table | cache, cache_locks | CREATE |
| 0001_01_01_000002_create_jobs_table | jobs, job_batches, failed_jobs | CREATE |
| 2026_05_09_000001 | businesses | CREATE |
| 2026_05_09_000002 | users | ADD business_id |
| 2026_05_09_000003 | categories | CREATE |
| 2026_05_09_000004 | subcategories | CREATE |
| 2026_05_09_000005 | products | CREATE (name, sku, sale_mode, price_per_kg_usd, price_per_unit_usd, location, active…) |
| 2026_05_09_000006 | inventory_entries | CREATE (product_id, quantity_kg, waste_kg, net_kg virtual, cost_per_kg_usd…) |
| 2026_05_09_000007 | cash_registers | CREATE |
| 2026_05_09_000008 | sales | CREATE (ticket_number, status, total_usd, total_bs, rate_used…) |
| 2026_05_09_000009 | sale_items | CREATE (snapshots: product_name, price_per_kg_usd…) |
| 2026_05_09_000010 | orders | CREATE |
| 2026_05_09_000011 | order_items | CREATE |
| 2026_05_09_000012 | cash_movements | CREATE |
| 2026_05_09_000013 | activity_logs | CREATE |
| 2026_05_09_000014 | businesses | ADD settings JSON, rate_margin… |
| 2026_05_10_000001 | dollar_rates | CREATE (rate, source, currency_type, effective_from, is_active) |
| 2026_05_10_000010 | payment_methods | CREATE |
| 2026_05_10_000013 | sale_payments | CREATE |
| 2026_05_10_000014 | sales | ADD client_name, client_phone, client_id |
| 2026_05_10_000015 | clients | CREATE |
| 2026_05_11_000001 | businesses | ADD theme_color |
| 2026_05_11_000002 | payment_terminals | CREATE |
| 2026_05_12_000001 | users | ADD role, theme |
| 2026_05_12_000002 | users | CHANGE role a ENUM |
| 2026_05_12_000010 | inventory_entries | ADD location |
| 2026_05_12_000011 | despiece_logs | CREATE |
| 2026_05_12_000012 | despiece_items | CREATE |
| 2026_05_12_000013 | sales | ADD delivery_status, delivery fields |
| 2026_05_12_000014 | products | ADD location |
| 2026_05_13_000015 | boveda_entries | CREATE |
| 2026_05_13_000016 | boveda_entries | NORMALIZE (agregar kg_surtido_vitrina, refactor) |
| 2026_05_13_000017 | boveda_products | CREATE |
| 2026_05_13_000018 | despiece_items | ADD tipo |
| 2026_05_13_165047 | boveda_entries | ADD waste_kg |
| 2026_05_13_190001 | inventory_entries | ADD boveda_entry_id |
| 2026_05_13_190002 | boveda_entries | ADD kg_disponible columna GENERATED VIRTUAL |
| 2026_05_13_190003 | sale_items | ADD subtotal_bs |
| 2026_05_13_200001 | categories | ADD macro_category |
| 2026_05_13_200002 | fabrica_batches | CREATE |
| 2026_05_13_200003 | fabrica_inputs | CREATE |
| 2026_05_13_210001 | products | ADD fabricable |
| 2026_05_13_210002 | fabrica_inputs | ADD product_id |
| 2026_05_13_220001 | branches | CREATE |
| 2026_05_13_220002 | users | EXPAND roles ENUM |
| 2026_05_13_220003 | sales, inventory_entries, etc. | ADD branch_id |
| 2026_05_13_230001 | products | ADD cost_per_unit_usd |
| 2026_05_14_000001 | sales | ADD payment_status, order_id |
| 2026_05_14_010001 | businesses | ADD max_branches |
| 2026_05_14_050743 | sales | ADD 'credit' al ENUM origin |
| 2026_05_14_065526 | products | ADD branch_id |
| 2026_05_14_071527 | users | ADD team fields (position, avatar…) |
| 2026_05_14_074358 | users | ADD access_days JSON |
| 2026_05_14_080924 | clients | RENAME client_code → cedula |
| 2026_05_14_100001 | cash_registers | ADD opening_amount_bs |
| 2026_05_14_180427 | boveda_products | ADD requires_despiece, vitrina_product_id |
| 2026_05_14_194421 | boveda_entries | ADD despiece_completado_at |
| 2026_05_15_000001 | products | DROP cost fields legacy |
| 2026_05_16_000001 | cash_movements | ADD 'corte' al ENUM type |
| 2026_05_16_000002 | cash_movements | ADD amount_bs |
| 2026_05_16_000003 | products | ADD is_favorite |

**Total: 65 migraciones**

---

## 8. Seeders

| Seeder | Propósito |
|--------|-----------|
| `DatabaseSeeder` | Orquestador principal — llama a los demás en orden |
| `PaymentMethodSeeder` | Métodos de pago base: efectivo Bs, efectivo USD, transferencia, pago móvil, punto de venta |
| `CatalogSeeder` | Categorías y productos genéricos de demostración |
| `CatalogSeederChaguaramas` | Catálogo real de Chaguaramas: Res/Pollo/Cerdo/Charcutería/Trastes con precios USD |
| `ChaguaramasBaseSeeder` | Datos base del negocio piloto: business, admin user, configuración |
| `InventorySeeder` | Entradas de inventario de prueba para vitrina |
| `TestFlowSeeder` | Flujo completo de prueba (22 checks): boveda → despiece → vitrina → POS → cierre — fixture de certificación |

---

## 9. Variables de entorno (.env keys)

```
APP_NAME
APP_ENV
APP_KEY
APP_DEBUG
APP_URL
APP_LOCALE
APP_FALLBACK_LOCALE
APP_FAKER_LOCALE
APP_MAINTENANCE_DRIVER
BCRYPT_ROUNDS
LOG_CHANNEL
LOG_STACK
LOG_DEPRECATIONS_CHANNEL
LOG_LEVEL
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
SYNTIWEB_DB_HOST
SYNTIWEB_DB_PORT
SYNTIWEB_DB_DATABASE
SYNTIWEB_DB_USERNAME
SYNTIWEB_DB_PASSWORD
DOLLAR_FALLBACK_RATE
SESSION_DRIVER
SESSION_LIFETIME
SESSION_ENCRYPT
SESSION_PATH
SESSION_DOMAIN
BROADCAST_CONNECTION
FILESYSTEM_DISK
QUEUE_CONNECTION
CACHE_STORE
MEMCACHED_HOST
REDIS_CLIENT
REDIS_HOST
REDIS_PASSWORD
REDIS_PORT
MAIL_MAILER
MAIL_SCHEME
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
AWS_ACCESS_KEY_ID
AWS_SECRET_ACCESS_KEY
AWS_DEFAULT_REGION
AWS_BUCKET
AWS_USE_PATH_STYLE_ENDPOINT
VITE_APP_NAME
```

**Notable:** `SYNTIWEB_DB_*` = conexión readonly a synticorex (dollar_rates). `DOLLAR_FALLBACK_RATE` = tasa de último recurso (default 40.00).
