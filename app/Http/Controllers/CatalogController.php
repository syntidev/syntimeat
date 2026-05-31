<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\SimpleExport;
use App\Imports\EmptyImport;
use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatalogController extends Controller
{
    public function index(): Response
{
    $user       = Auth::user();
    $businessId = $user->business_id;
    $branchId   = in_array($user->role, ['branch_admin', 'cashier'])
        ? $user->branch_id
        : (session('current_branch_id') ?? $user->branch_id);

    $categories = Category::with(['subcategories' => fn ($q) => $q->orderBy('sort_order')])
        ->where('business_id', $businessId)
        ->where('name', '!=', 'Bóveda')
        ->orderBy('sort_order')
        ->get();

    $products = Product::with(['category', 'subcategory'])
        ->where('business_id', $businessId)
        ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
        ->where('location', '!=', 'boveda')
        ->orderBy('category_id')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    $stockIn = InventoryEntry::where('business_id', $businessId)
        ->selectRaw('product_id, SUM(net_kg) as total_net')
        ->groupBy('product_id')
        ->pluck('total_net', 'product_id');

    $stockOut = DB::table('sale_items')
        ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
        ->where('sales.business_id', $businessId)
        ->where('sales.status', 'paid')
        ->selectRaw('sale_items.product_id, SUM(sale_items.quantity_value) as total_sold')
        ->groupBy('sale_items.product_id')
        ->pluck('total_sold', 'product_id');

    $products->each(function (Product $product) use ($stockIn, $stockOut): void {
        $net                    = (float) ($stockIn[$product->id]  ?? 0);
        $sold                   = (float) ($stockOut[$product->id] ?? 0);
        $product->current_stock = round($net - $sold, 3);
    });

    return Inertia::render('Catalog/Index', [
        'categories' => $categories,
        'products'   => $products,
    ]);
}

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'barcode'            => ['nullable', 'string', 'max:50'],
            'category_id'        => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id'     => ['nullable', 'integer', 'exists:subcategories,id'],
            'sale_mode'          => ['required', 'in:weight,unit'],
            'price_per_kg_usd'   => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,weight'],
            'price_per_unit_usd' => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,unit'],
            'min_stock'          => ['nullable', 'numeric', 'min:0'],
            'fabricable'         => ['boolean'],
            'is_favorite'        => ['boolean'],
            'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = Auth::user();
        $businessId = $user->business_id;
        $branchId = $user->branch_id
            ?? session('current_branch_id')
            ?? \App\Models\Branch::where('business_id', $businessId)->orderBy('id')->value('id');

        // Validar nombre único por sucursal
        $existe = Product::where('business_id', $businessId)
            ->where('name', $validated['name'])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->exists();
        if ($existe) {
            return response()->json(['errors' => ['name' => ['Ya existe un producto con ese nombre en esta sucursal.']]], 422);
        }

        if ($validated['sale_mode'] === 'unit') {
            $validated['min_stock'] = (int) round($validated['min_stock'] ?? 0);
        }

        $product = Product::create([
            'business_id'        => $businessId,
            'branch_id'          => $branchId,
            'category_id'        => $validated['category_id'],
            'subcategory_id'     => $validated['subcategory_id'] ?? null,
            'name'               => $validated['name'],
            'barcode'            => $validated['barcode'] ?? null,
            'sale_mode'          => $validated['sale_mode'],
            'base_unit_label'    => $validated['sale_mode'] === 'weight' ? 'kg' : 'und',
            'price_per_kg_usd'   => $validated['price_per_kg_usd'] ?? null,
            'price_per_unit_usd' => $validated['price_per_unit_usd'] ?? null,
            'min_stock'          => $validated['min_stock'] ?? 0,
            'fabricable'         => $validated['fabricable'] ?? false,
            'is_favorite'        => $validated['is_favorite'] ?? false,
            'active'             => true,
        ]);

        if ($request->hasFile('image')) {
            $imagePath = $this->processProductImage(
                $request->file('image'),
                $product->id,
                $product->name,
                $businessId,
            );
            if ($imagePath !== '') {
                $product->update(['image_path' => $imagePath]);
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'product' => [
                    'id'   => $product->id,
                    'name' => $product->name,
                ],
            ], 201);
        }

        return redirect()->route('catalog.index')->with('success', 'Producto creado.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'barcode'            => ['nullable', 'string', 'max:50'],
            'category_id'        => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id'     => ['nullable', 'integer', 'exists:subcategories,id'],
            'sale_mode'          => ['required', 'in:weight,unit'],
            'price_per_kg_usd'   => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,weight'],
            'price_per_unit_usd' => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,unit'],
            'min_stock'          => ['nullable', 'numeric', 'min:0'],
            'fabricable'         => ['boolean'],
            'is_favorite'        => ['boolean'],
            'active'             => ['boolean'],
            'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image'       => ['nullable', 'boolean'],
        ]);

        if ($validated['sale_mode'] === 'unit') {
            $validated['min_stock'] = (int) round($validated['min_stock'] ?? 0);
        }

        $updates = [
            'category_id'        => $validated['category_id'],
            'subcategory_id'     => $validated['subcategory_id'] ?? null,
            'name'               => $validated['name'],
            'barcode'            => $validated['barcode'] ?? null,
            'sale_mode'          => $validated['sale_mode'],
            'base_unit_label'    => $validated['sale_mode'] === 'weight' ? 'kg' : 'und',
            'price_per_kg_usd'   => $validated['price_per_kg_usd'] ?? null,
            'price_per_unit_usd' => $validated['price_per_unit_usd'] ?? null,
            'min_stock'          => $validated['min_stock'] ?? 0,
            'fabricable'         => $validated['fabricable'] ?? $product->fabricable,
            'is_favorite'        => $validated['is_favorite'] ?? $product->is_favorite,
            'active'             => $validated['active'] ?? $product->active,
        ];

        if ($request->boolean('remove_image') && $product->image_path) {
            $this->deleteProductImage($product->image_path);
            $updates['image_path'] = null;
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                $this->deleteProductImage($product->image_path);
            }
            $imagePath = $this->processProductImage(
                $request->file('image'),
                $product->id,
                $validated['name'],
                $product->business_id,
            );
            if ($imagePath !== '') {
                $updates['image_path'] = $imagePath;
            }
        }

        $product->update($updates);

        return redirect()->route('catalog.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $hasActiveSales = $product->saleItems()
            ->whereHas('sale', fn ($q) => $q->whereIn('status', ['open', 'pending']))
            ->exists();

        if ($hasActiveSales) {
            return redirect()->route('catalog.index')
                ->with('error', 'No se puede eliminar: el producto tiene ventas activas.');
        }

        $product->delete();

        return redirect()->route('catalog.index')->with('success', 'Producto eliminado.');
    }

    public function toggleFavorite(Product $product): RedirectResponse
    {
        abort_unless($product->business_id === Auth::user()->business_id, 403);

        $product->update(['is_favorite' => ! $product->is_favorite]);

        return redirect()->route('catalog.index');
    }

    // ─── Plantilla Excel de productos ─────────────────────────────────────────

    public function downloadProductTemplate(): BinaryFileResponse
    {
        $headings = ['nombre', 'categoria', 'precio_usd', 'unidad',
                     'stock_kg', 'activo', 'descripcion'];

        $example = collect([
            ['Carne Molida', 'Res', 3.50, 'weight', 50.000, 1, 'Carne fresca molida'],
            ['Pechuga Entera', 'Pollo', 2.80, 'weight', '', 1, ''],
            ['Chorizo und', 'Embutidos', 1.20, 'unit', '', 1, 'Por unidad'],
        ]);

        $export = new SimpleExport($example, $headings, 'Productos');

        return Excel::download($export, 'plantilla-productos.xlsx');
    }

    // ─── Importar productos desde Excel/CSV ───────────────────────────────────

    public function importProducts(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:4096'],
        ]);

        /** @var \App\Models\User $user */
        $user       = Auth::user();
        $businessId = $user->business_id;

        // Leer Excel — mismo helper que ContingencyController
        $rows = Excel::toCollection(new EmptyImport(), $request->file('file'))
            ->first();

        if ($rows === null || $rows->isEmpty()) {
            return response()->json(['error' => 'Archivo vacío o sin datos.'], 422);
        }

        // Verificar columnas requeridas
        $requiredCols = ['nombre', 'categoria', 'precio_usd', 'unidad'];
        $headers      = array_map('strtolower', $rows->first()->keys()->toArray());
        foreach ($requiredCols as $col) {
            if (!in_array($col, $headers, true)) {
                return response()->json(['error' => "Columna requerida faltante: {$col}"], 422);
            }
        }

        // Omitir fila de encabezado si primera celda = 'nombre'
        $data = strtolower((string) ($rows->first()['nombre'] ?? '')) === 'nombre'
            ? $rows->skip(1)
            : $rows;

        $imported = 0;
        $updated  = 0;
        $warnings = [];

        DB::beginTransaction();
        try {
            foreach ($data as $index => $row) {
                $row = array_change_key_case(
                    $row instanceof \Illuminate\Support\Collection ? $row->toArray() : (array) $row,
                    CASE_LOWER
                );

                $rowNum     = $index + 2;
                $nombre     = trim((string) ($row['nombre']     ?? ''));
                $categoria  = trim((string) ($row['categoria']  ?? ''));
                $precioRaw  = $row['precio_usd'] ?? '';
                $unidad     = strtolower(trim((string) ($row['unidad'] ?? 'weight')));
                $stockKg    = isset($row['stock_kg']) && (string) $row['stock_kg'] !== ''
                    ? (float) $row['stock_kg'] : null;
                $activoRaw  = $row['activo'] ?? 1;
                $descripcion = trim((string) ($row['descripcion'] ?? ''));

                // Validaciones de fila
                if ($nombre === '') {
                    $warnings[] = "Fila {$rowNum}: nombre vacío — omitida.";
                    continue;
                }

                if (!in_array($unidad, ['weight', 'unit'], true)) {
                    $warnings[] = "Fila {$rowNum}: unidad '{$unidad}' inválida (weight/unit) — omitida.";
                    continue;
                }

                if ($categoria === '') {
                    $warnings[] = "Fila {$rowNum}: categoría vacía — omitida.";
                    continue;
                }

                // Precio: null si vacío o 0 (solo actualiza si viene un valor real)
                $precio = ($precioRaw !== '' && $precioRaw !== null)
                    ? (float) $precioRaw
                    : null;

                // Activo
                $activo = (bool) filter_var($activoRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? (bool) ((int) $activoRaw);

                // Buscar o crear categoría (case-insensitive)
                $category = Category::where('business_id', $businessId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($categoria)])
                    ->first();

                if ($category === null) {
                    $maxSort  = Category::where('business_id', $businessId)->max('sort_order') ?? 0;
                    $category = Category::create([
                        'business_id' => $businessId,
                        'name'        => $categoria,
                        'sort_order'  => $maxSort + 1,
                        'active'      => true,
                    ]);
                }

                // Buscar producto existente (case-insensitive, mismo negocio)
                $product = Product::where('business_id', $businessId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($nombre)])
                    ->first();

                if ($product !== null) {
                    // ACTUALIZAR — solo precio si viene valor > 0
                    $updates = [
                        'category_id' => $category->id,
                        'active'      => $activo,
                    ];

                    if ($precio !== null && $precio > 0) {
                        if ($unidad === 'weight') {
                            $updates['price_per_kg_usd'] = $precio;
                        } else {
                            $updates['price_per_unit_usd'] = $precio;
                        }
                    }

                    $product->update($updates);
                    $updated++;
                } else {
                    // CREAR
                    $createData = [
                        'business_id'    => $businessId,
                        'category_id'    => $category->id,
                        'name'           => $nombre,
                        'sale_mode'      => $unidad,
                        'base_unit_label' => $unidad === 'weight' ? 'kg' : 'und',
                        'active'         => $activo,
                    ];

                    if ($precio !== null && $precio > 0) {
                        if ($unidad === 'weight') {
                            $createData['price_per_kg_usd']   = $precio;
                            $createData['price_per_unit_usd'] = null;
                        } else {
                            $createData['price_per_unit_usd'] = $precio;
                            $createData['price_per_kg_usd']   = null;
                        }
                    }

                    $product = Product::create($createData);
                    $imported++;

                    // Stock inicial (si viene)
                    if ($stockKg !== null && $stockKg > 0 && $unidad === 'weight') {
                        InventoryEntry::create([
                            'business_id'     => $businessId,
                            'product_id'      => $product->id,
                            'quantity_kg'     => $stockKg,
                            'waste_kg'        => 0,
                            'cost_per_kg_usd' => 0,
                            'notes'           => 'Importado desde Excel',
                            'entered_at'      => now(),
                            'created_by'      => $user->id,
                        ]);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('CatalogController::importProducts', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al importar: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'imported' => $imported,
            'updated'  => $updated,
            'warnings' => $warnings,
            'total'    => $imported + $updated,
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'color'          => ['nullable', 'string', 'max:20'],
            'icon'           => ['nullable', 'string', 'max:80'],
            'macro_category' => ['nullable', 'string', 'in:RES,POLLO,CERDO,TRASTES,MISC'],
        ]);

        $user = Auth::user();
        $businessId = $user->business_id;
        $branchId = $user->branch_id
            ?? session('current_branch_id')
            ?? \App\Models\Branch::where('business_id', $businessId)->orderBy('id')->value('id');

        // Validar nombre único por sucursal
        $existe = Category::where('business_id', $businessId)
            ->where('name', $validated['name'])
            ->exists();
        if ($existe) {
            return redirect()->route('catalog.index')
                ->with('error', 'Ya existe una categoría con ese nombre.');
        }

        $maxSort = Category::where('business_id', $businessId)->max('sort_order') ?? 0;

        Category::create([
            'business_id'    => $businessId,
            'name'           => $validated['name'],
            'color'          => $validated['color'] ?? null,
            'icon'           => $validated['icon'] ?? null,
            'macro_category' => $validated['macro_category'] ?? null,
            'sort_order'     => $maxSort + 1,
            'active'         => true,
        ]);

        return redirect()->route('catalog.index')->with('success', 'Categoría creada.');
    }

    public function storeSubcategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:80'],
        ]);

        $maxSort = Subcategory::where('category_id', $validated['category_id'])->max('sort_order') ?? 0;

        Subcategory::create([
            'category_id' => $validated['category_id'],
            'name'        => $validated['name'],
            'sort_order'  => $maxSort + 1,
            'active'      => true,
        ]);

        return redirect()->route('catalog.index')->with('success', 'Subcategoría creada.');
    }

    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'color'          => ['nullable', 'string', 'max:20'],
            'icon'           => ['nullable', 'string', 'max:80'],
            'macro_category' => ['nullable', 'string', 'in:RES,POLLO,CERDO,TRASTES,MISC'],
        ]);

        $category->update($validated);

        return redirect()->route('catalog.index')->with('success', 'Categoría actualizada.');
    }

    public function destroyCategory(Category $category): RedirectResponse
    {
        $hasProducts = Product::where('category_id', $category->id)
            ->where('active', true)
            ->exists();

        if ($hasProducts) {
            return redirect()->route('catalog.index')
                ->with('error', 'No se puede eliminar: la categoría tiene productos activos.');
        }

        $category->delete();

        return redirect()->route('catalog.index')->with('success', 'Categoría eliminada.');
    }

    public function updateSubcategory(Request $request, Subcategory $subcategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $subcategory->update($validated);

        return redirect()->route('catalog.index')->with('success', 'Subcategoría actualizada.');
    }

    public function destroySubcategory(Subcategory $subcategory): RedirectResponse
    {
        $hasProducts = Product::where('subcategory_id', $subcategory->id)
            ->where('active', true)
            ->exists();

        if ($hasProducts) {
            return redirect()->route('catalog.index')
                ->with('error', 'No se puede eliminar: la subcategoría tiene productos activos.');
        }

        $subcategory->delete();

        return redirect()->route('catalog.index')->with('success', 'Subcategoría eliminada.');
    }

    private function processProductImage(UploadedFile $file, int $productId, string $name, int $businessId): string
    {
        $slug     = Str::slug($name) ?: (string) $productId;
        $filename = "{$productId}_{$slug}.webp";
        $dir      = storage_path("app/public/business/{$businessId}/products");

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $destPath = "{$dir}/{$filename}";
        $mime     = $file->getMimeType() ?? '';
        $tmpPath  = $file->getPathname();

        $source = match (true) {
            str_contains($mime, 'png')  => imagecreatefrompng($tmpPath),
            str_contains($mime, 'webp') => imagecreatefromwebp($tmpPath),
            default                     => imagecreatefromjpeg($tmpPath),
        };

        if ($source === false) {
            return '';
        }

        $origW = imagesx($source);
        $origH = imagesy($source);

        if ($origW > 800 || $origH > 800) {
            $ratio = min(800 / $origW, 800 / $origH);
            $newW  = (int) round($origW * $ratio);
            $newH  = (int) round($origH * $ratio);
            $dest  = imagecreatetruecolor($newW, $newH);
            imagealphablending($dest, false);
            imagesavealpha($dest, true);
            $transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
            imagefilledrectangle($dest, 0, 0, $newW, $newH, $transparent);
            imagecopyresampled($dest, $source, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($source);
            $source = $dest;
        }

        $ok = imagewebp($source, $destPath, 82);
        imagedestroy($source);

        if (!$ok) {
            return '';
        }

        return "business/{$businessId}/products/{$filename}";
    }

    private function deleteProductImage(string $imagePath): void
    {
        $fullPath = storage_path("app/public/{$imagePath}");
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}