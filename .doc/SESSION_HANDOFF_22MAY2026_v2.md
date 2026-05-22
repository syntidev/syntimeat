# SESSION HANDOFF — SYNTImeat v2
**Fecha:** 22 Mayo 2026 — 1:30am
**Sesión:** Certificación Bóveda→Fábrica→Vitrina→POS + SYSTEM_MAP generado
**Repo:** https://github.com/syntidev/syntimeat.git
**Branch:** main | Commit HEAD: 0be682a
**Stack:** Laravel 13.8 + Inertia.js + Vue 3 + Tailwind 4 + MariaDB
**Local:** C:\laragon\www\syntimeat
**Readonly:** C:\laragon\www\synticorex (NUNCA tocar)

---

## CERTIFICACIÓN ACTUAL

| Test | Resultado |
|------|-----------|
| TestFlowSeeder | 22/22 ✅ |
| Stress Test (stress_test.php) | 46/46 ✅ |

---

## ARQUITECTURA REAL DEL SISTEMA

### Controllers (27 total)
| Controller | Módulo | Métodos clave |
|-----------|--------|---------------|
| AuthController | Auth | show, store, destroy |
| OnboardingController | Setup | show, store |
| DashboardController | Dashboard | index, data |
| CatalogController | Catálogo | index, store, update, destroy, storeCategory, updateCategory, storeSubcategory, toggleFavorite |
| InventoryController | Inventario | index, store |
| BovedaController | Bóveda | index, store, surte, close, registerMerma, plantillaDespiece, storeProduct, updateProduct, destroyProduct |
| FabricaController | Fábrica | index, store, storeDespiece |
| SaleController | POS/Ventas | index, store, pay, cancel, void, historial, generateTicketNumber |
| OrderController | Pedidos | index, store, collect, dispatch, cancel, deliveryIndex, confirmDelivery, collectPending |
| CashRegisterController | Caja | index, open, close, movement, dayClose, confirmClose |
| ReportController | Reportes | index, sales, inventory, closings, orders, dayReport, exportDayPdf, consolidated, export |
| ClientController | Clientes | index, store, update, show, search |
| ContingencyController | Contingencia | index, downloadForm, downloadTemplate, downloadInventoryTemplate, importSales, importInventory |
| SettingsController | Configuración | general, updateGeneral, cashRegisters, storeCashRegister, terminals, ticket, updateTicket, branches, users, storeUser, updateUser, destroyUser, setManualRate |
| PaymentMethodController | Métodos Pago | index, store, update, toggle, destroy, reorder |
| ProfileController | Perfil | edit, update, destroy |
| DollarRateController | Tasa | today |

### Middleware (4)
| Clase | Propósito |
|-------|-----------|
| EnsureRole | Verifica rol, abort 403 si no autorizado |
| CheckOnboarding | Redirige a /setup si wizard incompleto |
| EnforceUserSession | is_active, sesión única, días/horario habilitados |
| HandleInertiaRequests | Inyecta auth.user, flash, tasa en shared props |

### Services (2)
| Service | Propósito |
|---------|-----------|
| DollarRateService | getTodayRate() — API BCV + fallback manual |
| InventoryService | Cálculo de stock net_kg por producto |

### Models (25 total) — Relaciones clave
```
Business → hasMany: Users, Products, Categories, Sales, BovedaEntries
User → belongsTo: Business | hasMany: Sales, CashRegisters
Product → belongsTo: Category | hasMany: SaleItems, InventoryEntries
Sale → belongsTo: Business, User, CashRegister, Client
     → hasMany: items() [SaleItem], payments() [SalePayment]
BovedaEntry → belongsTo: Business
            → hasMany: InventoryEntries (via boveda_entry_id)
FabricaBatch → belongsTo: Business, outputProduct, creator
             → hasMany: inputs() [FabricaInput]
CashRegister → belongsTo: Business, User
             → hasMany: movements() [CashMovement], sales()
Order → belongsTo: Business, Client | hasMany: items() [OrderItem]
Client → belongsTo: Business | hasMany: sales(), orders()
```

---

## FLUJOS DE NEGOCIO CERTIFICADOS

### Flujo A — Canal/Res/Pollo (requires_despiece=1)
```
Bóveda.store() → boveda_entry creada
BovedaController.surte() → kg_surtido_vitrina++, despiece_pendiente
FabricaController.storeDespiece() → inventory_entries location=vitrina
SaleController.store() + pay() → stock descuenta, rate_used congelado
```

### Flujo B — Jamón/Queso (requires_despiece=0)
```
Bóveda.store() → boveda_entry creada
BovedaController.surte() → InventoryEntry location=vitrina directo
SaleController.store() + pay() → stock descuenta
```

### Flujo C — Fábrica (Chorizo/Combos)
```
FabricaController.store() → valida stock ingredientes (net_kg)
→ descuenta InventoryEntry de ingredientes (negativo)
→ crea InventoryEntry del producto fabricado (positivo)
→ FabricaBatch registrado con inputs y costo
```

### Ciclo de vida boveda_entry
```
ENTRADA → surte() N veces (waste_kg=0 durante surtidos)
→ auto-cierre cuando kg_disponible=0
→ O close() manual
→ Al cerrar: waste_kg = kg_entrada - kg_surtido_vitrina
```

---

## PRODUCTOS BÓVEDA (boveda_products)

| ID | Nombre | requires_despiece | Flujo |
|----|--------|-------------------|-------|
| 1 | Medio Canal Res | 1 | → Fábrica → Vitrina |
| 2 | Canal Cerdo | 1 | → Fábrica → Vitrina |
| 3 | Pollo Entero Congelado | 1 | → Fábrica → Vitrina |
| 4 | Jamón Pierna Sellado | 0 | → Vitrina directo |

---

## BUGS CERRADOS EN ESTA SESIÓN (19)

| # | Fix | Archivo |
|---|-----|---------|
| C-001 | router.reload() en lugar de location.reload() | Boveda/Index.vue |
| C-002 | surte() modo directo requires_despiece=0 | BovedaController.php |
| C-003 | v-model.number kg/costo + error campo correcto + guard deactivateProduct | Boveda/Index.vue |
| C-004 | despieceHistorial prop faltante en historial Fábrica | FabricaController.php + Fabrica/Index.vue |
| C-005 | router.reload incluye despieceHistorial | Fabrica/Index.vue |
| C-006 | CSS Merma/Proveedor pegados en tabla historial | Fabrica/Index.vue |
| C-007 | Limpieza registros seeder negativos ids 2,5,6,7,8 | DB boveda_entries |
| C-008 | waste_kg incorrecto Pollo id=10 | DB boveda_entries |
| C-009 | Merma solo al cerrar + auto-cierre kg_disponible=0 | BovedaController.php |
| C-010 | void() revierte InventoryEntries al anular venta paid | SaleController.php |
| C-011 | KPI Bóveda excluye kg_disponible negativo | BovedaController.php |
| C-012 | ActivityLog en open() y movement() de Caja | CashRegisterController.php |
| C-013 | DB::transaction en 5 métodos Bóveda (close,registerMerma,storeProduct,updateProduct,destroyProduct) | BovedaController.php |
| C-014 | FK business_id en storeDespiece() cortes.*.product_id | FabricaController.php |
| C-015 | generateTicketNumber() con lockForUpdate() — race condition cerrada | SaleController.php |
| C-016 | Fábrica→Vitrina descuento ingredientes verificado en caliente | — |
| C-017 | Bóveda directo→Vitrina convergencia verificada | — |
| C-018 | Validación stock net_kg en FabricaController.store() | FabricaController.php |
| C-019 | Auto-ajuste ±0.5kg + botón Máximo en modal Surtir | Boveda/Index.vue |

---

## DEUDA TÉCNICA PENDIENTE

### V1.0 — Antes de ir al cliente
| Prior | Tarea | Módulo |
|-------|-------|--------|
| 🔴 | Deploy VPS — Nginx + SSL + .env producción | Infraestructura |
| 🔴 | Certificar módulos restantes con stress test fases 6-10 | Todos |
| 🟡 | Ocultar botones admin en UI para rol cashier | Global Vue |

### V1.1 — Post-lanzamiento
| Prior | Tarea | Módulo |
|-------|-------|--------|
| 🟡 | Importador CSV productos | Catálogo |
| 🟡 | Módulo respaldo manual tickets post-apagón | Contingencia |
| 🟡 | Kits/Cestas en Fábrica (fabricable=true, product_id output) | Fábrica |
| 🟡 | CRUD Proveedores (hoy texto libre) | Nuevo módulo |
| 🟡 | Editar entrada activa Bóveda | Bóveda |
| 🟢 | Ticket como imagen WhatsApp (html2canvas) | POS |
| 🟢 | EnsureRole validar branch_id | Middleware |
| 🟢 | DayClose.vue cards duplicadas cuando boveda_entries vacía | Cash/DayClose.vue |

---

## HERRAMIENTAS DE CERTIFICACIÓN

```bash
# Datos — flujo feliz:
php artisan db:seed --class=TestFlowSeeder
# Esperado: 22/22 ✅

# Stress test — flujo completo + casos límite:
php stress_test.php
# Esperado: 46/46 ✅ (Fases 1-5 cubiertas)
# Pendiente: Fases 6-10 (Cowork generando)

# Push al repo:
git push origin main
```

---

## DEPLOY VPS — PRÓXIMO PASO

**Necesario:**
- IP del VPS
- OS Ubuntu 22/24
- Acceso SSH
- DNS chaguaramas.synti.cloud → IP del VPS

**Secuencia:**
```bash
# En VPS:
sudo apt install nginx php8.3-fpm mariadb-server
git clone https://github.com/syntidev/syntimeat.git
cp .env.example .env
# Editar .env con DB producción
php artisan migrate --force
php artisan storage:link
php artisan config:cache
# Configurar Nginx virtual host
# Certbot SSL
```

---

## REGLAS CRÍTICAS — NO VIOLAR

```
PHP:
- declare(strict_types=1) en TODO archivo PHP
- Early return — nunca nesting > 2 niveles
- Eager loading — cero N+1 toleradas
- DB::transaction en toda operación con múltiples writes

Vue:
- <script setup> siempre — Options API prohibida
- CSS vars: var(--brand), var(--bg-card), var(--text-primary)
- NUNCA colores hardcodeados

Negocio:
- Input POS: cajera ingresa MONTO Bs → sistema calcula kg inverso
- location=boveda NUNCA en POS ni inventario vitrina
- Stock descuenta SOLO en status=paid
- Moneda DB: price_usd + rate_used + total_bs siempre los tres
- Tasa no disponible: usar última tasa, NUNCA bloquear venta
- Anulaciones: admin+, motivo obligatorio ≥5 chars, ActivityLog
- NUNCA tocar synticorex
- 1 archivo por request salvo instrucción explícita
```

---

## ARCHIVOS CLAVE GENERADOS EN ESTA SESIÓN

| Archivo | Propósito |
|---------|-----------|
| SYSTEM_MAP.md | Mapa completo del sistema — fuente de verdad |
| stress_test.php | Stress test 46 casos — certificación completa |
| run_stress_test.bat | Ejecutor del stress test en Windows |
| SESSION_HANDOFF_22MAY2026.md | Este documento |
| database/seeders/TestFlowSeeder.php | Certificación flujo A→Z 22/22 |

---

*SYNTIdev — synti.dev — Mayo 2026 — Confidencial*
