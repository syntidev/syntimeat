# SYNTImeat — Schema Reference
# Base de datos: syntimeat_db — Mayo 2026
# Fuente: SHOW COLUMNS directo de MySQL

---

## TABLAS CORE DEL NEGOCIO

### businesses
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| name | varchar | NO | — |
| legal_name | varchar | NO | — |
| rif | varchar | NO | — |
| logo_path | varchar | YES | — |
| address | varchar | YES | — |
| city | varchar | YES | — |
| state | varchar | YES | — |
| phone | varchar | YES | — |
| currency_default | enum(USD) | NO | USD |
| rate_source | enum(bcv) | NO | bcv |
| rate_margin | decimal | NO | 0.00 |
| weight_unit | varchar | NO | kg |
| ticket_prefix | varchar | NO | VEN |
| ticket_footer | text | YES | — |
| sale_capture_mode | enum | NO | classic |
| line_input_mode | enum | NO | weight |
| preticket_enabled | tinyint | NO | 0 |
| preticket_expiry_minutes | int | NO | 30 |
| price_lock_policy | enum | NO | at_capture |
| onboarding_completed | tinyint | NO | 0 |
| active | tinyint | NO | 1 |
| max_branches | tinyint | NO | 2 |
| settings | json | YES | — |
| theme_color | varchar | NO | blue |
| created_at / updated_at | timestamp | YES | — |

### branches
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| name | varchar | NO | — |
| address | varchar | YES | — |
| city | varchar | YES | — |
| phone | varchar | YES | — |
| is_active | tinyint | NO | 1 |
| access_start | time | YES | — |
| access_end | time | YES | — |
| created_at / updated_at | timestamp | YES | — |

### users
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | YES | — |
| branch_id | bigint | YES | — |
| is_active | tinyint | NO | 1 |
| session_token | varchar | YES | — |
| access_start | time | YES | — |
| access_end | time | YES | — |
| access_days | json | YES | — |
| role | enum(cashier) | NO | cashier |
| theme | varchar | NO | dark |
| name | varchar | NO | — |
| email | varchar | YES | — |
| email_verified_at | timestamp | YES | — |
| password | varchar | NO | — |
| remember_token | varchar | YES | — |
| created_at / updated_at | timestamp | YES | — |

**Roles disponibles:** super_admin, admin, contador, cashier

---

## CATÁLOGO

### categories
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| name | varchar | NO | — |
| icon | varchar | YES | — |
| color | varchar | YES | — |
| macro_category | varchar | YES | — |
| sort_order | int | NO | 0 |
| active | tinyint | NO | 1 |
| created_at / updated_at | timestamp | YES | — |

### subcategories
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| category_id | bigint | NO | — |
| name | varchar | NO | — |
| sort_order | int | NO | 0 |
| active | tinyint | NO | 1 |
| created_at / updated_at | timestamp | YES | — |

### products
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| branch_id | bigint | YES | — |
| category_id | bigint | NO | — |
| subcategory_id | bigint | YES | — |
| name | varchar | NO | — |
| sku | varchar | YES | — |
| barcode | varchar | YES | — |
| sale_mode | enum(weight/unit) | NO | weight |
| base_unit_label | varchar | NO | kg |
| fraction_allowed | tinyint | NO | 1 |
| price_per_kg_usd | decimal | YES | — |
| price_per_unit_usd | decimal | YES | — |
| min_stock | decimal | NO | 0.000 |
| location | enum(vitrina/despensa/boveda) | NO | vitrina |
| image_path | varchar | YES | — |
| sort_order | int | NO | 0 |
| active | tinyint | NO | 1 |
| fabricable | tinyint | NO | 0 |
| created_at / updated_at | timestamp | YES | — |

**REGLA:** location=boveda → NUNCA en POS ni inventario vitrina

---

## INVENTARIO

### inventory_entries
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| branch_id | bigint | YES | — |
| business_id | bigint | NO | — |
| product_id | bigint | NO | — |
| quantity_kg | decimal | NO | — |
| waste_kg | decimal | NO | 0.000 |
| net_kg | decimal | YES | — |
| cost_per_kg_usd | decimal | YES | — |
| supplier | varchar | YES | — |
| notes | text | YES | — |
| location | enum(vitrina/despensa/boveda) | NO | vitrina |
| boveda_entry_id | bigint | YES | — |
| entered_at | datetime | NO | — |
| created_by | bigint | NO | — |
| created_at / updated_at | timestamp | YES | — |

**Stock real** = SUM(quantity_kg) por product_id (entradas positivas y negativas)

---

## BÓVEDA

### boveda_products
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| name | varchar | NO | — |
| unit | varchar | NO | kg |
| requires_despiece | tinyint | NO | 1 |
| vitrina_product_id | bigint | YES | — |
| active | tinyint | NO | 1 |
| sort_order | int | NO | 0 |
| created_at / updated_at | timestamp | YES | — |

### boveda_entries
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| product_type | varchar | YES | — |
| description | varchar | YES | — |
| kg_entrada | decimal | NO | — |
| costo_usd | decimal | NO | — |
| kg_surtido_vitrina | decimal | NO | 0.000 |
| waste_kg | decimal | NO | 0.000 |
| kg_disponible | decimal | YES | — |
| supplier | varchar | YES | — |
| entered_at | datetime | NO | — |
| closed_at | datetime | YES | — |
| despiece_completado_at | timestamp | YES | — |
| created_at / updated_at | timestamp | YES | — |

**CAMPO CLAVE:** `costo_usd` (no `cost_usd` ni `cost_per_kg_usd`)
**UTILIDAD** = ventas_categoria - costo boveda entrada (costo_usd)

---

## FÁBRICA / DESPIECE

### despiece_logs
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| user_id | bigint | NO | — |
| product_id | bigint | NO | — |
| location_from | enum(boveda) | NO | boveda |
| quantity_kg_from | decimal | NO | — |
| notes | text | YES | — |
| processed_at | datetime | NO | — |
| created_at / updated_at | timestamp | YES | — |

### despiece_items
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| despiece_log_id | bigint | NO | — |
| product_id | bigint | YES | — |
| quantity_kg | decimal | NO | — |
| tipo | enum(corte_principal/subproducto_vendible/subproducto_fabricado/waste) | NO | corte_principal |
| created_at / updated_at | timestamp | YES | — |

### fabrica_batches
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| created_by | bigint | NO | — |
| output_product_id | bigint | NO | — |
| output_kg | decimal | NO | — |
| output_units | decimal | NO | 0.000 |
| input_cost_usd | decimal | NO | 0.00 |
| notes | varchar | YES | — |
| produced_at | timestamp | NO | — |
| created_at / updated_at | timestamp | YES | — |

### fabrica_inputs
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| fabrica_batch_id | bigint | NO | — |
| product_id | bigint | YES | — |
| despiece_item_id | bigint | YES | — |
| inventory_entry_id | bigint | YES | — |
| label | varchar | YES | — |
| quantity_kg | decimal | NO | — |
| cost_usd | decimal | NO | 0.00 |
| created_at / updated_at | timestamp | YES | — |

---

## POS / VENTAS

### sales
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| branch_id | bigint | YES | — |
| ticket_number | varchar | NO | — |
| status | enum(open/paid/cancelled) | NO | open |
| payment_status | enum(paid) | NO | paid |
| total_usd | decimal | NO | 0.00 |
| payment_method | varchar | YES | — |
| amount_received_usd | decimal | YES | — |
| change_usd | decimal | YES | — |
| rate_used | decimal | YES | — |
| total_bs | decimal | YES | — |
| notes | text | YES | — |
| origin | enum(onsite/delivery) | NO | onsite |
| channel | enum(physical) | NO | physical |
| delivery_status | enum | YES | — |
| delivery_confirmed_at | datetime | YES | — |
| client_name | varchar | YES | — |
| client_phone | varchar | YES | — |
| client_id | bigint | YES | — |
| sold_at | datetime | YES | — |
| cashier_id | bigint | NO | — |
| cash_register_id | bigint | YES | — |
| order_id | bigint | YES | — |
| cancelled_at | datetime | YES | — |
| cancelled_by | bigint | YES | — |
| cancellation_reason | text | YES | — |
| created_at / updated_at | timestamp | YES | — |

**REGLA:** stock descuenta SOLO cuando status = 'paid'

### sale_items
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| sale_id | bigint | NO | — |
| product_id | bigint | NO | — |
| product_name | varchar | NO | — |
| input_type | enum(weight/unit) | NO | weight |
| quantity_value | decimal | NO | — |
| unit_label | varchar | NO | kg |
| price_per_kg_usd | decimal | YES | — |
| price_per_unit_usd | decimal | YES | — |
| subtotal_usd | decimal | NO | — |
| subtotal_bs | decimal | NO | 0.00 |
| rate_used | decimal | YES | — |
| discount_usd | decimal | NO | 0.00 |
| created_at / updated_at | timestamp | YES | — |

**SNAPSHOT:** product_name + price guardados al momento de venta

### sale_payments
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| sale_id | bigint | NO | — |
| payment_method_id | bigint | NO | — |
| amount_bs | decimal | NO | — |
| amount_usd | decimal | NO | — |
| reference | varchar | YES | — |
| created_at / updated_at | timestamp | YES | — |

---

## CAJA

### cash_registers
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| branch_id | bigint | YES | — |
| name | varchar | NO | — |
| opened_at | datetime | NO | — |
| closed_at | datetime | YES | — |
| opening_amount_usd | decimal | NO | — |
| opening_amount_bs | decimal | NO | 0.00 |
| expected_cash_usd | decimal | YES | — |
| counted_cash_usd | decimal | YES | — |
| difference_usd | decimal | YES | — |
| rate_at_opening | decimal | YES | — |
| notes | text | YES | — |
| opened_by | bigint | NO | — |
| closed_by | bigint | YES | — |
| created_at / updated_at | timestamp | YES | — |

### cash_movements
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| cash_register_id | bigint | NO | — |
| type | enum | NO | — |
| amount_usd | decimal | NO | — |
| concept | varchar | NO | — |
| created_by | bigint | NO | — |
| created_at / updated_at | timestamp | YES | — |

---

## PEDIDOS

### orders
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| client_name | varchar | NO | — |
| client_type | enum(external) | NO | external |
| status | enum(open) | NO | open |
| total_usd | decimal | NO | 0.00 |
| notes | text | YES | — |
| created_by | bigint | NO | — |
| created_at / updated_at | timestamp | YES | — |

### order_items
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| order_id | bigint | NO | — |
| product_id | bigint | NO | — |
| product_name | varchar | NO | — |
| input_type | enum(weight/unit) | NO | weight |
| quantity_value | decimal | NO | — |
| unit_label | varchar | NO | kg |
| price_per_kg_usd | decimal | YES | — |
| price_per_unit_usd | decimal | YES | — |
| subtotal_usd | decimal | NO | — |
| created_at / updated_at | timestamp | YES | — |

---

## CONFIGURACIÓN

### payment_methods
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| name | varchar | NO | — |
| type | enum | NO | — |
| bank_name | varchar | YES | — |
| is_active | tinyint | NO | 1 |
| sort_order | int | NO | 0 |
| created_at / updated_at | timestamp | YES | — |

### payment_terminals
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| method | varchar | NO | — |
| bank_name | varchar | YES | — |
| serial | varchar | YES | — |
| commercial_number | varchar | YES | — |
| is_active | tinyint | NO | 1 |
| sort_order | int | NO | 0 |
| created_at / updated_at | timestamp | YES | — |

### dollar_rates
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| rate | decimal | NO | — |
| source | varchar | NO | bcv |
| currency_type | varchar | NO | USD |
| effective_from | datetime | NO | — |
| effective_until | datetime | YES | — |
| is_active | tinyint | NO | 1 |
| created_at | timestamp | YES | — |

---

## AUDITORÍA

### activity_logs
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| user_id | bigint | NO | — |
| action | varchar | NO | — |
| model_type | varchar | NO | — |
| model_id | bigint | YES | — |
| old_values | json | YES | — |
| new_values | json | YES | — |
| ip_address | varchar | YES | — |
| created_at / updated_at | timestamp | YES | — |

### clients
| Campo | Tipo | Nulo | Default |
|-------|------|------|---------|
| id | bigint | NO | — |
| business_id | bigint | NO | — |
| cedula | varchar | YES | — |
| name | varchar | NO | — |
| phone | varchar | YES | — |
| email | varchar | YES | — |
| address | text | YES | — |
| notes | text | YES | — |
| active | tinyint | NO | 1 |
| created_at / updated_at | timestamp | YES | — |

---

## CAMPOS CRÍTICOS — NUNCA CONFUNDIR

| Variable | Tabla | Significado |
|----------|-------|-------------|
| costo_usd | boveda_entries | Costo total del canal al entrar |
| cost_per_kg_usd | inventory_entries | Costo por kg en vitrina |
| cost_usd | fabrica_inputs | Costo del ingrediente en el lote |
| input_cost_usd | fabrica_batches | Costo total de ingredientes del lote |
| price_per_kg_usd | products | Precio de venta al público |
| rate_used | sales / sale_items | Tasa BCV usada en la transacción |
| total_bs | sales | Total cobrado en bolívares |
| quantity_kg | inventory_entries | Puede ser negativo (descuento de stock) |
| kg_surtido_vitrina | boveda_entries | Kg enviados a fábrica/vitrina |
| waste_kg | boveda_entries | Merma en almacenamiento |
| waste_kg | inventory_entries | Merma en proceso |

---

## FLUJO DE DATOS COMPLETO

```
boveda_entries (costo_usd, kg_entrada)
  ↓ kg_surtido_vitrina
despiece_logs → despiece_items (quantity_kg por tipo)
  ↓ corte_principal / subproducto_vendible
inventory_entries (quantity_kg, location=vitrina)
  ↓ product_id
products (price_per_kg_usd — asignado manualmente)
  ↓
sales → sale_items (snapshot: product_name, price, rate_used)
  ↓
sale_payments (amount_bs, amount_usd)
  ↓
cash_registers → cash_movements
  ↓
UTILIDAD = SUM(sales.total_usd) - SUM(boveda_entries.costo_usd)
```

---

*SYNTImeat — syntimeat_db — Mayo 2026 — SYNTIdev*
