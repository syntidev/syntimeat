# AGENTS.md — SYNTIMEAT
# Instrucciones universales para agentes autónomos
# Versión: 1.0 | Mayo 2026

## PROYECTO

POS para PYMES venezolanas. Laravel 13.8 + Inertia + Vue 3 + Tailwind 4.
Ruta local: C:\laragon\www\syntimeat\

## LEER SIEMPRE PRIMERO

1. CLAUDE.md — gobernanza y reglas críticas
2. SYNTImeat_Master_Handoff_v3.docx en .doc/ — arquitectura completa

## AGENTES

| Agente      | Archivo                                | Cuándo                          |
|-------------|----------------------------------------|---------------------------------|
| @consultant | .github/agents/consultant.agent.md    | Análisis antes de ejecutar      |
| @executor   | .github/agents/executor.agent.md      | Implementar ya definido         |
| @reviewer   | .github/agents/reviewer.agent.md      | Auditar código entregado        |
| @debugger   | .github/agents/debugger.agent.md      | Diagnosticar error específico   |

## REGLAS QUE NINGÚN AGENTE PUEDE VIOLAR

- declare(strict_types=1) en todo PHP
- <script setup> en todo Vue
- Moneda: Bs. al cliente, price_usd × rate = total_bs en DB
- Stock descuenta solo al status=paid
- NUNCA tocar synticorex
- 1 archivo por request
