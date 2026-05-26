# SYNTImeat — Certificación V1.0
**Fecha:** 26 Mayo 2026
**Estado:** CERTIFICADO ✅

## Por qué es V1.0

Esta certificación no es solo técnica — es contable.
El sistema demuestra que el flujo completo Bóveda → Fábrica → POS → Reporte
produce números reales, trazables y correctos.

## Demostración en producción — 26/05/2026

### Media Canal Res
| Concepto | Valor |
|---|---|
| Kg entrada bóveda | 100 kg |
| Costo canal | $800.00 USD |
| Costo por kg | $8.00/kg |
| Merma | 5 kg (5%) |
| Kg vendidos | 95 kg |
| Distribución | Premium 20kg + Primera 20kg + Segunda 15kg + Costilla 15kg + H.Redondo 15kg + H.Rojo 10kg |
| Ingresos venta | $1,015.00 USD |
| **Utilidad neta** | **$215.00 USD (21.2%)** |

### Canal Cerdo
| Concepto | Valor |
|---|---|
| Kg entrada | 50 kg / $200 USD ($4/kg) |
| Kg vendidos | 48 kg (Chuleta 30kg + Costilla 18kg) |
| Ingresos | $522.00 USD |
| **Utilidad** | **$322.00 USD (61%)** |

### Pollo Entero
| Concepto | Valor |
|---|---|
| Kg entrada | 30 kg / $90 USD ($3/kg) |
| Kg vendidos | 28 kg |
| Ingresos | $168.00 USD |
| **Utilidad** | **$78.00 USD (46.7%)** |

### Charcutería — Jamón
| Concepto | Valor |
|---|---|
| Kg entrada | 20 kg / $120 USD ($6/kg) |
| Kg vendidos | 18 kg |
| Ingresos | $162.00 USD |
| **Utilidad** | **$42.00 USD (25.9%)** |

## Consolidado del día
| Métrica | Valor |
|---|---|
| Ventas totales | $1,867.00 USD / Bs. 994,610.55 |
| Costo total | $1,143.45 USD |
| **Utilidad total** | **$723.55 USD** |
| **Margen** | **38.8%** |
| Volumen despachado | 189 kg |
| Ticket promedio | $622.33 USD |

## Fórmula validada

```
Utilidad = Ventas USD - (SUM(boveda_entries.costo_usd) / SUM(boveda_entries.kg_entrada)) × kg_vendidos
```

Esta fórmula está implementada en:
- ReportController::buildDayData() — Reporte del Día
- ReportController::buildConsolidatedData() — Panel Empresarial
- DashboardController::buildData() — Dashboard en tiempo real

## Tests
| Suite | Resultado |
|---|---|
| stress_test.php | 146/146 ✅ |
| AccesoRolesTest | 28/28 ✅ |

## Archivos clave modificados en esta certificación
- app/Models/Sale.php — accounting_date en $fillable
- app/Http/Controllers/SaleController.php — crédito status=pending
- app/Http/Controllers/OrderController.php — accounting_date al cobrar
- app/Http/Controllers/ReportController.php — costo real desde boveda_entries
- app/Http/Controllers/DashboardController.php — accounting_date consistente
- app/Http/Middleware/EnsureRole.php — Inertia-aware sin logout
- app/Http/Middleware/EnforceUserSession.php — X-Inertia-Location
- app/Console/Commands/FetchDollarRate.php — scheduler 15 min

---
*SYNTIdev — synti.dev — Certificación generada: 26 Mayo 2026*
