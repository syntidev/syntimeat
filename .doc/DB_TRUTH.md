# SYNTImeat — Archivo de Verdad: tabla `businesses`
# Generado: Mayo 2026 — Referencia obligatoria antes de tocar OnboardingController

## Columnas y restricciones reales en syntimeat_db.businesses

| Columna                  | Tipo            | NULL | Default        | Notas                                      |
|--------------------------|-----------------|------|----------------|--------------------------------------------|
| id                       | bigint unsigned | NO   | autoincrement  |                                            |
| name                     | varchar(255)    | NO   | —              | Requerido en form                          |
| legal_name               | varchar(255)    | NO   | —              | Opcional en form → fallback a `name`       |
| rif                      | varchar(255)    | NO   | —              | Opcional en form → fallback a `''`         |
| logo_path                | varchar(255)    | YES  | NULL           | OK nullable                                |
| address                  | varchar(255)    | YES  | NULL           | OK nullable                                |
| city                     | varchar(255)    | YES  | NULL           | El form lo pide como required              |
| state                    | varchar(255)    | YES  | NULL           | El form lo pide como required              |
| phone                    | varchar(255)    | YES  | NULL           | OK nullable                                |
| currency_default         | enum            | NO   | 'USD'          | Default en DB — no requiere fallback       |
| rate_source              | enum            | NO   | 'bcv'          | Default en DB — no requiere fallback       |
| rate_margin              | decimal(5,2)    | NO   | 0.00           | Default en DB — no requiere fallback       |
| weight_unit              | varchar(5)      | NO   | 'kg'           | Default en DB — no requiere fallback       |
| ticket_prefix            | varchar(10)     | NO   | 'VEN'          | Default en DB — no requiere fallback       |
| ticket_footer            | text            | YES  | NULL           | OK nullable                                |
| sale_capture_mode        | enum            | NO   | 'classic'      | Default en DB — no requiere fallback       |
| line_input_mode          | enum            | NO   | 'weight'       | Default en DB — no requiere fallback       |
| preticket_enabled        | tinyint(1)      | NO   | 0              | Default en DB — no requiere fallback       |
| preticket_expiry_minutes | int             | NO   | 30             | Default en DB — no requiere fallback       |
| price_lock_policy        | enum            | NO   | 'at_capture'   | Default en DB — no requiere fallback       |
| onboarding_completed     | tinyint(1)      | NO   | 0              | Default en DB — no requiere fallback       |
| active                   | tinyint(1)      | NO   | 1              | Default en DB — no requiere fallback       |
| settings                 | json            | YES  | NULL           | OK nullable                                |
| created_at               | timestamp       | YES  | NULL           | Manejado por Eloquent                      |
| updated_at               | timestamp       | YES  | NULL           | Manejado por Eloquent                      |

## Regla fija para el OnboardingController — paso 1

Los campos `legal_name` y `rif` son `NOT NULL` en DB pero opcionales en el form.
Siempre aplicar estos fallbacks ANTES del `Business::create()` o `update()`:

```php
$validated['legal_name'] = $validated['legal_name'] ?? $validated['name'];
$validated['rif']        = $validated['rif'] ?? '';
$validated['phone']      = $validated['phone'] ?? '';
```

## Campos $fillable del modelo Business (verificados)

name, legal_name, rif, logo_path, address, city, state, phone,
currency_default, rate_source, rate_margin, weight_unit, ticket_prefix,
ticket_footer, sale_capture_mode, line_input_mode, preticket_enabled,
preticket_expiry_minutes, price_lock_policy, onboarding_completed, active, settings

## Validación en step1 (OnboardingController)

| Campo      | Regla          | Fallback en controller |
|------------|----------------|------------------------|
| name       | required       | —                      |
| legal_name | nullable       | → $name                |
| rif        | nullable       | → ''                   |
| city       | required       | —                      |
| state      | required       | —                      |
| phone      | nullable       | → ''                   |
