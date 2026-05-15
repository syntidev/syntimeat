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
- NUNCA crear worktrees — todo desarrollo directo en main
- git worktree list debe mostrar solo C:/laragon/www/syntimeat [main]
- NUNCA abrir archivos adicionales sin permiso explícito
- NUNCA proponer "ya que estoy aquí, también arreglé..."
- NUNCA continuar después de completar el pedido
- Máximo 1 archivo modificado por request salvo instrucción explícita

---

## 12 REGLAS DE COMPORTAMIENTO

### Regla 1 — Piensa antes de codear
Declara suposiciones explícitamente antes de tocar cualquier archivo.
Si no estás seguro, pregunta en UNA línea y PARA.
Presenta múltiples interpretaciones cuando haya ambigüedad.
Detente cuando estés confundido. Nombra qué no está claro.

### Regla 2 — Simplicidad primero
Código mínimo que resuelve el problema. Nada especulativo.
Sin features que no se pidieron. Sin abstracciones para código de uso único.
Test: ¿un senior diría que esto está sobrecomplicado? Si sí, simplifica.

### Regla 3 — Cambios quirúrgicos
Toca solo lo que debas. No "mejores" código adyacente.
No refactorices lo que no está roto. Sigue el estilo existente del proyecto.
Si encuentras un bug fuera del scope → reportar en 1 línea, NO corregir.

### Regla 4 — Ejecución por objetivos
Define criterios de éxito antes de implementar.
Itera hasta verificar — no sigas pasos ciegos.
Criterios fuertes permiten iterar de forma independiente.

### Regla 5 — El código responde cuando puede
Usa el modelo solo para decisiones de juicio.
Ruteo, reintentos, transformaciones determinísticas → código, no LLM.
Si el código puede responder, el código responde.

### Regla 6 — Los presupuestos de tokens no son sugerencias
Por tarea: 4,000 tokens. Por sesión: 30,000 tokens.
Si te acercas al presupuesto, resume y avisa antes de continuar.
No dejes el exceso pasar en silencio.

### Regla 7 — Surfacea conflictos, no los promedies
Si dos patrones existentes contradicen, no los mezcles.
Elige el más reciente/probado, explica por qué, marca el otro para limpieza.
Código "promedio" que satisface dos reglas contradictorias es el peor código.
Ejemplo real: SaleController vs SalesController → elige uno, mata el otro.

### Regla 8 — Lee antes de escribir
Antes de agregar código en un archivo:
1. Lee los exports del archivo
2. Lee el caller inmediato
3. Lee utilidades compartidas obvias
Si no entiendes por qué el código existente está estructurado así, PREGUNTA.
"Me parece ortogonal" es la frase más peligrosa en este codebase.

### Regla 9 — Los tests verifican intención, no solo comportamiento
Cada test debe codificar POR QUÉ el comportamiento importa.
Para SYNTImeat: el TestFlowSeeder es el test de intención del flujo completo.
Corre php artisan db:seed --class=TestFlowSeeder antes de cualquier commit mayor.
Si un check falla, no hagas commit.

### Regla 10 — Checkpoint después de cada paso significativo
Tras completar cada paso en una tarea multi-paso:
- Resume qué se hizo
- Qué está verificado
- Qué falta
No continúes desde un estado que no puedas describir de vuelta.
Si pierdes el hilo, PARA y replantea.

### Regla 11 — Respeta las convenciones del codebase
snake_case en PHP/DB. camelCase en Vue/JS. Sin excepciones.
CSS vars siempre — nunca colores hardcodeados.
Plus Jakarta Sans — nunca Figtree.
El desacuerdo con una convención es una conversación separada.
Dentro del codebase: conformidad > gusto personal.

### Regla 12 — Falla en voz alta
"Migración completada" está mal si registros se saltaron en silencio.
"Tests pasaron" está mal si saltaste alguno.
"Feature funciona" está mal si no verificaste el caso límite pedido.
Por default: surfacea incertidumbre, no la escondas.
Si no puedes estar seguro de que algo funcionó, dilo explícitamente.

---

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
- La tasa viene de dollar_rates, fallback a última disponible
- NUNCA bloquear una venta por falta de tasa

### FLUJO DE NEGOCIO — IRROMPIBLE
- Bóveda → Fábrica/Despiece → Vitrina → POS → Cierre
- location=boveda: SOLO visible en /boveda y /despiece
- NUNCA en POS ni inventario vitrina
- POS filtra: ->where('location', '!=', 'boveda')
- Input POS: cajera ingresa MONTO Bs → sistema calcula kg inverso
- Stock descuenta SOLO cuando sale.status = 'paid'

### ROLES — NUNCA VIOLAR
- super_admin: todo, todas las sucursales
- admin: todo en su sucursal, no crea admins ni sucursales
- contador: reportes y caja solo lectura, sin costos de bóveda
- cajero: solo POS e inventario sin costos

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
- Solo roles admin/super_admin pueden anular — motivo obligatorio ≥5 chars

### DISEÑO Y UX — ESTÁNDAR OBLIGATORIO
- Dark UI por defecto, clase .light en html root para modo claro
- Toggle luna/sol persiste en users.theme (dark|light)
- Fuente: Plus Jakarta Sans — NUNCA Figtree
- Sin colores hardcodeados — usar CSS vars
- Mobile first — todo funciona en celular
- Touch targets mínimo 44px
- Feedback visual en cada acción: loading, success, error
- Módulo de ayuda (botón ?): OBLIGATORIO en cada módulo
  • Tab "Cómo funciona": pasos numerados, ícono + título + descripción + tip 💡
  • Tab "Preguntas frecuentes": acordeón colapsable
  • Lenguaje simple — el operador no es técnico
  • Referencia de diseño: copiar exactamente el formato de Bóveda y Fábrica

### BASE DE DATOS
- syntimeat_db — 35 migraciones corridas
- Conexión readonly a synticorex DB para dollar_rates únicamente
- Campos monetarios: decimal(10,2) con sufijo _usd para referencia
- Campos de peso: decimal(8,3)
- Snapshots en sale_items: guardar product_name y prices al momento de venta
- NUNCA modificar migraciones ya corridas — crear nuevas si hace falta

### SCOPE
- 1 archivo modificado por request salvo instrucción explícita
- NUNCA tocar C:\laragon\www\synticorex
- NUNCA crear worktrees — todo directo en main

## CHECKLIST PRE-ENTREGA

- [ ] declare(strict_types=1) en archivos PHP
- [ ] <script setup> en componentes Vue
- [ ] @vite() en lugar de asset()
- [ ] Sin colores hardcodeados — usando CSS vars
- [ ] Eager loading en queries con relaciones
- [ ] Sin archivos fuera del scope tocados
- [ ] Moneda: price_usd × rate = total_bs al cobrar
- [ ] Sin worktrees — git worktree list muestra solo main
- [ ] Módulo de ayuda actualizado si el módulo cambió
- [ ] TestFlowSeeder pasa 22/22 si el cambio afecta el flujo core

## AGENTES DISPONIBLES

| Agente      | Cuándo usarlo                              |
|-------------|---------------------------------------------|
| @consultant | Antes de ejecutar — análisis y viabilidad   |
| @executor   | Implementar algo ya definido                |
| @reviewer   | Auditar código entregado                    |
| @debugger   | Diagnosticar un error específico            |

Flujo:
- Tarea ambigua → @consultant primero
- Tarea clara   → @executor directamente
- Post-impl     → @reviewer
- Bug           → @debugger