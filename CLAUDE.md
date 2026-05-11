# SYNTImeat — CLAUDE.md
# Instrucciones maestras para Claude Code
# Leer completo antes de cualquier acción

## GOBERNANZA — LEER PRIMERO

### Modos de operación

| MODO      | Palabra clave | Qué hacer                          | PROHIBIDO                              |
|-----------|---------------|------------------------------------|----------------------------------------|
| CONSULTA  | [CONSULTA]    | Responder en ≤5 líneas, sin código | Abrir archivos, escribir código        |
| DISEÑO    | [DISEÑO]      | Proponer arquitectura              | Implementar, tocar archivos            |
| EJECUCIÓN | [EJECUTA]     | Implementar lo acordado            | Inferir cambios fuera del scope        |
| REVISIÓN  | [REVISA]      | Auditar código existente           | Proponer refactors no solicitados      |
| DEBUG     | [DEBUG]       | Diagnosticar SOLO el error         | Tocar código fuera del scope           |

Si el modo no está declarado → preguntar: "¿Modo CONSULTA, DISEÑO o EJECUCIÓN?" y PARAR.
NUNCA asumir modo EJECUCIÓN por defecto.

### Protocolo anti-deriva (irrompible)

Antes de cada respuesta verificar:
1. ¿Me pidieron código? → Solo entonces escribo código
2. ¿El scope es claro? → Si no, preguntar en UNA línea y parar
3. ¿Voy a modificar algo fuera de lo pedido? → PARAR
4. ¿Encontré un bug fuera del scope? → Reportar en 1 línea, NO corregir

Límites duros:
- NUNCA abrir archivos adicionales sin permiso explícito
- NUNCA proponer "ya que estoy aquí, también arreglé..."
- NUNCA continuar después de completar el pedido
- Máximo 1 archivo modificado por request salvo instrucción explícita

## PROYECTO

SYNTImeat — Sistema POS para PYMES venezolanas.
Cliente piloto: Carnicería Chaguaramas, Valle de la Pascua, Guárico.
Ruta local: C:\laragon\www\syntimeat\
Repo referencia (SOLO LECTURA, NUNCA modificar): C:\laragon\www\synticorex\

## STACK

Laravel 13.8, PHP 8.3, MySQL
Inertia.js, Vue 3 (Composition API), Tailwind 4
Vite 8, Motion One

## REGLAS CRÍTICAS — NUNCA VIOLAR

### PHP
- declare(strict_types=1) en TODO archivo PHP sin excepción
- Early return obligatorio — nunca nesting mayor a 2 niveles
- Eager loading obligatorio — cero N+1 toleradas
- NUNCA asset() → siempre @vite()
- NUNCA exec(), shell_exec(), eval()

### Vue
- SIEMPRE <script setup> — Options API prohibida
- Componentes 100% propios — sin Bootstrap, sin templates comprados
- CSS vars para colores: var(--brand), var(--bg-card), var(--text-primary)
- NUNCA colores hardcodeados en componentes

### MONEDA — CRÍTICO
- El negocio opera en BOLÍVARES (Bs.) — moneda real de cobro al cliente
- Precios definidos en USD solo como referencia interna
- Al vender: price_usd × tasa_del_día = total_bs que paga el cliente
- El ticket al cliente muestra BOLÍVARES — nunca dólares al cliente
- En DB guardar siempre: price_usd + rate_used + total_bs
- La tasa viene de dollar_rates (synticorex DB readonly), fallback a última disponible
- NUNCA bloquear una venta por falta de tasa
- Leer ANTES de desarrollar DollarRateService:
  C:\laragon\www\synticorex\app\Models\DollarRate.php
  C:\laragon\www\synticorex\app\Console\Commands\ (buscar dollar o rate)
  C:\laragon\www\synticorex\app\Services\ (buscar Dollar o Rate)

### INVENTARIO
- Descuenta stock SOLO cuando sale.status cambia a 'paid'
- Tickets open NO afectan el stock
- waste_kg en inventory_entries — stock usa net_kg (quantity_kg - waste_kg)

### PRODUCTOS
- sale_mode = weight → input decimal en kg → precio = qty × price_per_kg_usd
- sale_mode = unit  → input entero → precio = qty × price_per_unit_usd
- El operador NUNCA hace aritmética — el sistema calcula solo

### AUDITORÍA
- Toda anulación registra en activity_logs: user_id, motivo, timestamp
- Solo rol admin puede anular — motivo obligatorio

### DISEÑO
- Dark UI por defecto, clase .light en html root para modo claro
- Toggle luna/sol persiste en users.theme (dark|light)
- Fuente: Plus Jakarta Sans — NUNCA Figtree (viene con Breeze, reemplazar)
- Sin colores hardcodeados — usar CSS vars

### BASE DE DATOS
- syntimeat_db — 13 tablas ya migradas
- Conexión readonly a synticorex DB para dollar_rates únicamente
- Campos monetarios: decimal(10,2) con sufijo _usd para referencia
- Campos de peso: decimal(8,3)
- Snapshots en sale_items: guardar product_name y prices al momento de venta

### SCOPE
- 1 archivo modificado por request salvo instrucción explícita
- NUNCA tocar C:\laragon\www\synticorex
- NUNCA modificar migraciones ya corridas — crear nuevas si hace falta

## CHECKLIST PRE-ENTREGA

- [ ] declare(strict_types=1) en archivos PHP
- [ ] <script setup> en componentes Vue
- [ ] @vite() en lugar de asset()
- [ ] Sin colores hardcodeados — usando CSS vars
- [ ] Eager loading en queries con relaciones
- [ ] Sin archivos fuera del scope tocados
- [ ] Moneda: price_usd × rate = total_bs al cobrar

## AGENTES DISPONIBLES

| Agente   | Cuándo usarlo                              |
|----------|-----------------------------------------|
| @consultant | Antes de ejecutar — análisis y viabilidad |
| @executor   | Implementar algo ya definido              |
| @reviewer   | Auditar código entregado                  |
| @debugger   | Diagnosticar un error específico          |

Flujo:
- Tarea ambigua → @consultant primero
- Tarea clara   → @executor directamente
- Post-impl     → @reviewer
- Bug           → @debugger
