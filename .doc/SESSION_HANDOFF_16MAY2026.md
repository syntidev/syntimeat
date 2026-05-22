# SYNTImeat — Session Handoff
# Fecha: 16 Mayo 2026 — Para nueva sesión de Claude Code
# Estado al momento del corte de sesión

---

## CONTEXTO RÁPIDO

Proyecto: SYNTImeat — POS para Carnicería Chaguaramas
Repo: C:\laragon\www\syntimeat
Stack: Laravel 13.8 + Vue 3 + Inertia + Tailwind 4 + MySQL
DB: syntimeat_db
Usuario: carbolivar@gmail.com / Admin001 / role: super_admin
Lee CLAUDE.md y SYNTImeat_Schema_Reference.md antes de tocar nada.

---

## ESTADO DE CERTIFICACIÓN

✅ Bóveda — certificada
✅ Fábrica — certificada (despiece + producción por lotes)
✅ Inventario — certificado
✅ POS — certificado con:
   - Toggle "Solo con stock" funcional
   - Favoritos (is_favorite) — migración corrida, pendiente verificar en UI
   - Paginación 20 en 20 con "Cargar más"
   - Scroll horizontal en tabs de categorías (prompt en cola)
   - Bóveda eliminada de tabs
   - Precios reales en seeder
✅ Ventas del Día — certificado
   - Filtro por método de pago corregido
✅ Cierre del Día — certificado
   - Categorías muestran RES/POLLO/CERDO (no MISC)
   - Apertura y movimientos en Bs. exactos sin drift
   - Corte de turno sin cerrar caja
   - Badge "Corte" en azul
✅ Pedidos — certificado
   - Crédito y Delivery cobran correctamente
   - Montos Bs. exactos en pedidos
✅ Caja — certificada
   - Apertura con monto exacto
   - Movimientos en Bs. exactos
   - Corte de turno funcional

---

## PROMPTS PENDIENTES DE EJECUTAR (en orden)

### PROMPT 1 — Ícono moto en ticket
```
[EJECUTA] Un archivo — resources/js/Pages/POS/Index.vue

En el ticket imprimible, agregar indicador del 
tipo de venta debajo del número de ticket:

- Venta normal en sitio: no mostrar nada
- Delivery: ícono Lucide <Bike> + texto "DELIVERY"
- Crédito: ícono Lucide <Clock> + texto "CRÉDITO"

Confirmar el campo exacto que identifica el tipo 
(sale.origin o sale.channel) antes de tocar.
Un archivo. Sin worktrees.
```

### PROMPT 2 — Terminales en cobro de pedidos (Opción C)
```
[EJECUTA] Un archivo — resources/js/Pages/Orders/Index.vue

En el modal "Registrar Cobro" de pedidos, el dropdown 
de métodos de pago debe incluir también los terminales.

Opción C: al seleccionar un terminal, usa el 
payment_method_id de tipo card/débito y pre-llena 
el campo reference con el nombre del terminal.

El controller ya envía paymentMethods y terminals.
Verificar qué props llegan y combinarlos en el dropdown.

Un archivo. Sin worktrees.
```

### PROMPT 3 — Scroll horizontal tabs POS
```
[EJECUTA] Un archivo — resources/js/Pages/POS/Index.vue

Los tabs de categorías deben tener scroll horizontal 
con flechas < > en desktop cuando hay overflow.
Fades en los bordes. Touch scroll en mobile.
Íconos Lucide <ChevronLeft> <ChevronRight>.
Un archivo. Sin worktrees.
```

### PROMPT 4 — Badge Pedidos en menú
```
[EJECUTA] Un archivo — resources/js/Layouts/AppLayout.vue

Agregar badge numérico en el ítem "Pedidos" del menú
igual al badge que tiene "Inventario" (stock crítico).

El badge debe mostrar: créditos pendientes + deliveries por cobrar.
El dato viene del mismo endpoint que ya carga los cobrosPendientes.

Un archivo. Sin worktrees.
```

### PROMPT 5 — Badge Fábrica en menú
```
[EJECUTA] Un archivo — resources/js/Layouts/AppLayout.vue

Agregar badge en "Fábrica" cuando hay despiece pendiente
(boveda_entries con despiece_completado_at = null y kg_surtido > 0).

Mismo estilo que badge de Inventario.
Un archivo. Sin worktrees.
```

---

## MÓDULOS SIN CERTIFICAR (en orden de prioridad)

1. **Clientes** — CRUD básico, historial de tickets
2. **Dashboard** — KPIs, ventas por categoría (Panel muestra $0.00 — bug)
3. **Configuración completa** — General, Usuarios, Sucursales, Cajas, Ticket
4. **Reportes** — 4 tabs, export PDF/Excel
5. **Contingencia** — offline mode
6. **Panel Empresarial** — multi-sucursal, $0.00 bug pendiente

---

## DEUDA TÉCNICA DOCUMENTADA

| Bug | Módulo | Prioridad |
|-----|--------|-----------|
| Panel Empresarial muestra $0.00 | Analytics | 🔴 Alta |
| EnsureRole no valida branch_id | Middleware | 🔴 Alta |
| Importador batch de precios (Excel) | Catálogo | 🟡 Media |
| Ticket como imagen para WhatsApp (html2canvas) | POS | 🟡 Media |
| Bóveda para productos no-carne (quesos, embutidos) | Bóveda | 🟡 Media |
| Corte de turno muestra Bs. 0 en registros legacy | Caja | 🟢 Baja |
| TST-0005 del seeder contamina historial real | Seeder | 🟢 Baja |

---

## FEATURES PENDIENTES V1.1

- **Favoritos en POS** — migración corrida (is_favorite en products), 
  estrella en Catálogo, tab "Favoritos" en POS — VERIFICAR si quedó funcional
- **Bóveda productos no-carne** — quesos/embutidos entran en cantidad,
  salen directo a vitrina sin despiece en Fábrica
- **Turnos de caja** — transferencia formal de responsabilidad entre cajeros

---

## REGLAS CRÍTICAS (no violar)

- declare(strict_types=1) en TODO PHP
- <script setup> en TODO Vue
- NUNCA emojis — SIEMPRE Lucide Vue
- NUNCA worktrees — todo directo en main
- Moneda: Bs. al cliente. price_usd × tasa = total_bs en DB
- El monto Bs. que ingresa el cajero es SAGRADO — no se recalcula
- location=boveda NUNCA en POS ni inventario vitrina
- Stock descuenta SOLO en status=paid
- Módulo de ayuda (?) en cada módulo nuevo — mismo formato que Bóveda/Fábrica
- TestFlowSeeder debe pasar 22/22 antes de cualquier commit mayor

---

## COMANDO DE VERIFICACIÓN RÁPIDA

```bash
php artisan db:seed --class=TestFlowSeeder
# Debe mostrar: Resultados: 22/22 checks OK ✅ PASS
```

---

## GIT STATUS

Último commit: fix(ventas): filtros método de pago, Bs exactos pedidos, ticket delivery/crédito
Branch: main
Remote: github.com/syntidev/syntimeat

---

*SYNTIdev — synti.dev — Handoff generado: 16 Mayo 2026*
