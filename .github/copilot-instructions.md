# SYNTIMEAT — INSTRUCCIONES PARA COPILOT
# Versión: 1.0 | Mayo 2026

## PROYECTO

POS Laravel 13.8 + Vue 3 + Inertia + Tailwind 4 para PYMES venezolanas.
Ruta: C:\laragon\www\syntimeat\

## REGLAS PHP
- declare(strict_types=1) en todo archivo PHP
- Early return — nunca nesting > 2 niveles
- Eager loading siempre — cero N+1
- NUNCA asset() → siempre @vite()

## REGLAS VUE
- Siempre <script setup>
- Componentes propios — sin Bootstrap
- CSS vars: var(--brand), var(--bg-card), var(--text-primary)

## MONEDA — CRÍTICO
- Operación en BOLÍVARES (Bs.)
- price_usd × tasa_del_día = total_bs al cobrar
- DB guarda: price_usd + rate_used + total_bs
- Ticket al cliente: BOLÍVARES
- Tasa desde dollar_rates (synticorex readonly)

## INVENTARIO
- Stock descuenta SOLO al status=paid
- waste_kg en entradas — stock = net_kg

## PRODUCTOS
- sale_mode=weight → kg decimal → price_per_kg_usd
- sale_mode=unit → entero → price_per_unit_usd

## DISEÑO
- Dark UI — CSS vars en :root y .light
- Fuente: Plus Jakarta Sans
- Sin colores hardcodeados

## SCOPE
- 1 archivo por request
- NUNCA tocar C:\laragon\www\synticorex
