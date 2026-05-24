# prod_catalog_replace.ps1
# TAREA: Reemplazar catálogo de producción por CatalogSeederChaguaramas
# Ejecutar desde: C:\laragon\www\syntimeat\
# Uso: .\prod_catalog_replace.ps1
# BORRAR después de ejecutar.

$SSH = "ssh -i $env:USERPROFILE\.ssh\id_ed25519 -o StrictHostKeyChecking=no root@187.124.241.213"
$BASE = "cd /var/www/syntimeat &&"

function Run($label, $cmd) {
    Write-Host "`n=== $label ===" -ForegroundColor Cyan
    $full = "$BASE $cmd"
    $result = Invoke-Expression "$SSH '$full'" 2>&1
    Write-Host $result
    return $result
}

Write-Host "`n=====================================================" -ForegroundColor Yellow
Write-Host " REEMPLAZO CATALOGO PRODUCCION - Chaguaramas" -ForegroundColor Yellow
Write-Host "=====================================================" -ForegroundColor Yellow

# PASO 1 — Borrar en orden (respetando foreign keys)
Write-Host "`n[PASO 1] Borrando datos existentes..." -ForegroundColor Magenta

$deletes = @(
    "inventory_entries",
    "sale_items",
    "sales",
    "boveda_entries",
    "despiece_logs",
    "despiece_items",
    "products",
    "categories",
    "subcategories"
)

foreach ($table in $deletes) {
    Run "DELETE $table" "php artisan tinker --execute=`"DB::table('$table')->delete(); echo '$table: OK';`""
}

# PASO 2 — Correr seeder
Write-Host "`n[PASO 2] Corriendo CatalogSeederChaguaramas..." -ForegroundColor Magenta
Run "SEEDER" "php artisan db:seed --class=CatalogSeederChaguaramas --force"

# PASO 3 — Verificar
Write-Host "`n[PASO 3] Verificando conteos..." -ForegroundColor Magenta
Run "COUNT products"   "php artisan tinker --execute=`"echo 'products: ' . DB::table('products')->count();`""
Run "COUNT categories" "php artisan tinker --execute=`"echo 'categories: ' . DB::table('categories')->count();`""

Write-Host "`n=====================================================" -ForegroundColor Yellow
Write-Host " LISTO. Verifica: products >= 44, categories > 0" -ForegroundColor Yellow
Write-Host "=====================================================" -ForegroundColor Yellow
