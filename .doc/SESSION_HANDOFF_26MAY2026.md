# SYNTImeat — Session Handoff
# Fecha: 26 Mayo 2026
# Estado: PRODUCCIÓN ACTIVA — Wave 1-5 completadas + Fase 19 stress_test

---

## CONTEXTO

Proyecto: SYNTImeat — POS Carnicería Chaguaramas
Repo: https://github.com/syntidev/syntimeat — Branch: main
Producción: https://meat.synti.cloud
VPS: 187.124.241.213 (Ubuntu 24.04 — Hostinger KVM1)
Local: C:\laragon\www\syntimeat
Super admin oculto: carbolivar@gmail.com (is_hidden=1, ID=20)

Certificación: stress_test.php 146/146 ✅ | AccesoRolesTest 28/28 ✅

---

## CREDENCIALES PRODUCCIÓN

```
carbolivar@gmail.com       → super_admin  | Chaguaramas2026! (is_hidden=1)
dueno@chaguaramas.com      → owner        | Chaguaramas2026!
admin@elbuencorte.com      → branch_admin | Chaguaramas2026!
contable1@chaguaramas.com  → analyst      | Chaguaramas2026!
contable2@elbuencorte.com  → analyst      | Chaguaramas2026!
cajera1@chaguaramas.com    → cashier      | Chaguaramas2026!
cajera2@elbuencorte.com    → cashier      | Chaguaramas2026!
cajera2@chaguaramas.com    → cashier      | Chaguaramas2026!
```

DB VPS: syntimeat_db / syntimeat / SyntiMeat2026!

---

## DEMO EN PRODUCCIÓN — DATOS DE CALIDAD

| Categoría    | Compra kg | Costo USD | Ventas USD | Utilidad | Margen |
|--------------|-----------|-----------|------------|----------|--------|
| Res          | 100 kg    | $800      | $1,015     | +$215    | 21%    |
| Cerdo        | 50 kg     | $200      | $522       | +$322    | 62%    |
| Pollo        | 30 kg     | $90       | $168       | +$78     | 46%    |
| Charcutería  | 20 kg     | $120      | $162       | +$42     | 26%    |
| **TOTAL**    |           | **$1,143**| **$1,867** | **+$724**| **38.8%** |

Tickets demo: DEMO-0001 / DEMO-0002 / DEMO-0003

---

## FIXES APLICADOS HOY — 26/05/2026

### Wave 1 — Aritmética contable
- **SaleController**: venta con `origin=credit` nace `status=pending`, no `paid`. Nunca afecta el reporte del día hasta que se cobre.
- **ReportController** (`buildDayData` + `buildConsolidatedData`): costo calculado desde `boveda_entries` reales — última entrada por producto, no promedio histórico. Filtro `payment_status != 'pendiente_cobro'` excluye créditos no cobrados.
- **Sale model**: `accounting_date` agregado a `$fillable`.
- **OrderController** (`collectPending`): al cobrar un crédito, fija `accounting_date` al día del cobro (no al de la venta). Preserva `accounting_date` si ya estaba seteado.

### Wave 2 — Seguridad roles
- **EnsureRole middleware**: sin logout automático. Responde con `409 + X-Inertia-Location` para requests Inertia y `abort(403)` para requests normales.
- **EnforceUserSession middleware**: `X-Inertia-Location` en los 4 bloques de redirect (sesión expirada, IP cambiada, etc.).
- **AppLayout.vue**: selector de sucursal visible solo para `super_admin`, `owner`, `branch_admin`. Cajeros y analistas no lo ven.
- **web.php**: `POST /set-branch` protegido con middleware de rol.

### Wave 3 — Cliente obligatorio en crédito/delivery
- **SaleController**: `client_name` es `required_if:origin,credit,delivery`. Validación en backend.
- **POS/Index.vue**: botón "Confirmar venta" bloqueado si `origin=credit` o `delivery` y no hay `client_name` ingresado.

### Wave 4 — Caja y tasa
- **CashRegisterController**: el monto de retiro no puede superar el saldo disponible en caja. Validación con mensaje de error claro.
- **AppLayout.vue**: `canEditRate` habilitado para `admin`, `owner`, `super_admin`, `branch_admin`. Cajeros no pueden modificar la tasa.

### Wave 5 — Bóveda dual (prorrateo de costo)
- **BovedaController**: entrada dual — una compra puede dividirse en dos porciones (ej: Res + huesos). El costo se prorratea por kg entre ambas porciones.
- **BovedaEntry model**: `pair_id` agregado a `$fillable`.
- **Boveda/Index.vue**: modal de entrada con toggle "Entrada dual", preview del prorrateo en tiempo real antes de confirmar.

### Fixes adicionales
- **BovedaController** (`plantillaDespiece`): `catMap` actualizado a los `product_type` reales del sistema (`RES - Medio Canal`, `CERDO - Canal`, etc.).
- **DashboardController**: usa `accounting_date` en lugar de `sold_at` para agrupar ventas del día. `bovedaCategoryMap` actualizado.
- **Dashboard.vue**: rediseño UI completo — barras proporcionales en Top Productos, Centro de Control por categoría con chips filtrables, badge pulsante en Stock Crítico, live dot en KPI principal.
- **ReportController**: drill-down de productos por categoría en reporte del día (`categories[].productos[]`).
- **Ticket.vue**: 4 campos nuevos con toggle (RIF del negocio, nombre del cajero, tasa BCV del día, método de pago). Todos desactivados por defecto.
- **Boveda/Index.vue**: tabla muestra kg con 2 decimales.
- **FetchDollarRate command** (`dollar:fetch`): comando Artisan creado para actualizar la tasa desde BCV.
- **routes/console.php**: scheduler ejecuta `dollar:fetch` cada 15 minutos.
- **Help actualizado**: Dashboard.vue (5 pasos + 6 FAQs), Settings/Ticket.vue (4 pasos), Boveda/Index.vue (nomenclatura `RES - Medio Canal` / `CERDO - Canal` / `POLLO - Entero Congelado`).

---

## FASE 19 — STRESS TEST (Wave 1 business rules)

Nueva fase agregada a `stress_test.php` — 7 assertions nuevas (total: 146/146).

### Caso A — Crédito no contamina reporte del día
- `19.A.1`: venta con `origin=credit` → `status≠paid` + `payment_status=pendiente_cobro`
- `19.A.2`: `buildDayData` del día no incrementa `vendido_usd` con el crédito sin cobrar

### Caso B — Crédito cobrado sí aparece en el día del cobro
- `19.B.1`: `collectPending()` → `status=paid` + `payment_status=paid`
- `19.B.2`: reporte del día (usando `accounting_date` real vía `Sale::find()`) sí incrementa `vendido_usd`

### Caso C — Costo usa inventario real, no promedio
- `19.C.1`: dos `InventoryEntry` para el mismo producto ($2.00/kg ayer, $3.00/kg hoy)
- `19.C.2`: venta pagada con ese producto
- `19.C.3`: reporte muestra `costo_usd > 0` y `utilidad_usd < vendido_usd`

### Fixes aplicados a Fase 19
1. `is_active` en lugar de `active` en `PaymentMethod` query
2. `$anyCategory19`: sin filtro `active` — usa `Category::where('business_id')->value('id')`
3. Assertion `19.A.1`: `status !== 'paid'` (ENUM no tiene `pending_dispatch`)
4. `$fechaCobroB19`: `Sale::find($id)->accounting_date` en lugar de `now()` (evita drift UTC/Caracas)
5. `$fechaReporteC19`: mismo patrón para `19.C.3`
6. Debug output antes de cada assertion (B.2 y C.3) para diagnóstico en próximas ejecuciones
7. `client_name` agregado al payload del crédito de `19.A.1`

---

## DECISIONES TÉCNICAS ESTA SESIÓN

### [DECISION] accounting_date como fecha canónica
`accounting_date` es la fecha que el sistema usa para imputar una venta al reporte del día. Para ventas normales se fija al crear la venta. Para créditos, se fija al cobrar (`collectPending`). El reporte usa `accounting_date` — no `sold_at` ni `created_at`. Regla: si se crean ventas después de las 19:00 hora local, `accounting_date` avanza al día siguiente.

### [DECISION] Costo por última entrada (no promedio)
`buildDayData` busca el costo via `boveda_entries` agrupadas por `product_id` + `cost_per_kg_usd`, ordenadas por `entered_at DESC`, tomando la primera por producto (`unique('product_id')`). Esto garantiza que el costo usado sea el de la última compra, no un promedio histórico.

### [DECISION] EnsureRole sin logout
El middleware de roles nunca hace logout. Si el rol no coincide: Inertia → 409 con `X-Inertia-Location` al dashboard. Request normal → `abort(403)`. Esto evita que un cajero que navega a una URL de admin sea deslogueado.

### [DECISION] Dashboard live con polling 30s
`fetchData()` con `setInterval(30_000)` en `onMounted`. El contador animado usa `requestAnimationFrame` con easing `cubic-bezier`. El live dot (punto verde parpadeante) es puramente CSS — sin JS adicional.

---

## PROTOCOLO IRROMPIBLE

- Auditar primero. CLI-A consulta, CLI-B ejecuta. Nunca CoWork sin prompt completo.
- NUNCA `php artisan test` sin `--filter` (borra DB con RefreshDatabase)
- NUNCA `php artisan test --testsuite=Feature` (ídem)
- NUNCA commit sin stress test 146/146 + roles test 28/28
- `npm run build` obligatorio en VPS después de cualquier cambio Vue
- `loginUsingId` dinámico: `User::where('role','super_admin')->where('is_hidden',0)->value('id')`
- `Sale::find($id)` para leer campos frescos de DB — nunca `->fresh()` sobre arrays JSON decodificados

---

## ESTADO DE TESTS

- `stress_test.php`: 146/146 PASS (19 fases — Fase 19 agrega 7 assertions Wave 1)
- `AccesoRolesTest.php`: 28/28 PASS
- `phpunit.xml`: `DB_DATABASE :memory:` ELIMINADO — usa MySQL real

---

## ARCHIVOS CLAVE

```
stress_test.php                                    — 146 tests, 19 fases
tests/Feature/AccesoRolesTest.php                  — 28 tests HTTP reales
app/Http/Controllers/SaleController.php            — origin=credit nace pending, client_name required
app/Http/Controllers/OrderController.php           — collectPending fija accounting_date
app/Http/Controllers/ReportController.php          — buildDayData: costo real boveda_entries
app/Http/Controllers/BovedaController.php          — entrada dual, prorrateo costo, catMap actualizado
app/Http/Controllers/DashboardController.php       — accounting_date, bovedaCategoryMap
app/Http/Controllers/CashRegisterController.php    — límite retiro vs saldo disponible
app/Http/Middleware/EnsureRole.php                 — 409+X-Inertia-Location, sin logout
app/Http/Middleware/EnforceUserSession.php         — X-Inertia-Location en 4 bloques
app/Models/Sale.php                                — accounting_date en $fillable
app/Models/BovedaEntry.php                         — pair_id en $fillable
app/Console/Commands/FetchDollarRate.php           — dollar:fetch command
routes/console.php                                 — scheduler dollar:fetch cada 15 min
routes/web.php                                     — POST /set-branch con middleware rol
resources/js/Pages/Dashboard.vue                   — rediseño completo, Centro de Control, live dot
resources/js/Pages/Boveda/Index.vue                — entrada dual, modal prorrateo, helpSteps actualizado
resources/js/Pages/POS/Index.vue                   — botón bloqueado sin client_name
resources/js/Pages/Settings/Ticket.vue             — 4 campos nuevos con toggle
resources/js/Layouts/AppLayout.vue                 — canEditRate, selector sucursal por rol
```

---

## COMANDOS VPS

```bash
ssh -i C:\Users\carbo\.ssh\id_ed25519 root@187.124.241.213
cd /var/www/syntimeat && git pull origin main && npm run build
php artisan route:clear && php artisan route:cache && php artisan config:cache && php artisan view:clear
# Verificar scheduler activo
crontab -l
# Correr tasa manualmente
php artisan dollar:fetch
```

---

## DEUDA TÉCNICA PENDIENTE

- [ ] `branch_id` no está en `$fillable` de `Sale` — bug latente si se asigna por masa
- [ ] `.env.testing` con DB separada para aislar Feature Tests de DB real
- [ ] Módulo merma compensada por chorizo (valor agregado — requiere diseño)
- [ ] Responsive tablet — certificación visual pendiente
- [ ] IVA en ticket (futuro — requiere configuración por negocio)
- [ ] QR en ticket (futuro)
- [ ] Tendencia vs ayer en Dashboard — requiere prop `ventas_ayer` en `DashboardController`
- [ ] Debug output en Fase 19.B.2 y 19.C.3 — remover antes de release final

---

## REGLAS CRÍTICAS

- `accounting_date` es la fecha canónica para reportes — nunca usar `sold_at` ni `created_at`
- Créditos (`origin=credit`): nacen `status=pending` + `payment_status=pendiente_cobro`. Aparecen en reporte solo al cobrar.
- Costo en reporte: última `boveda_entry` por producto — no promedio histórico
- `Sale::find($id)` para leer DB fresca — `->fresh()` solo funciona sobre modelos Eloquent, no sobre arrays JSON
- Carne del Canal: `active=false`, invisible en POS, pool para stock de res
- Fábrica Res: exactamente 4 cortes [Carne del Canal, Costilla, Hueso Redondo, Hueso Rojo]
- POLLO: `requires_despiece=0`, bifurca Tipo A/Tipo B al surtir
- `vitrina_product_id`: campo en `boveda_products`, lookup directo en `surte()`
- Productos "Otro libre": `requires_despiece=false`, match exacto por nombre en vitrina
- NUNCA correr suite completo de tests — solo `--filter` o archivo específico
- NUNCA modificar migraciones ya corridas — crear nuevas
