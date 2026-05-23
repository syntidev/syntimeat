<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryEntry;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        $businessId = Auth::user()->business_id;

        $categories = Category::with(['subcategories' => fn ($q) => $q->orderBy('sort_order')])
            ->where('business_id', $businessId)
            ->where('name', '!=', 'Bóveda')
            ->orderBy('sort_order')
            ->get();

        $branchId = session('current_branch_id') ?? Auth::user()->branch_id;

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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'category_id'        => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id'     => ['nullable', 'integer', 'exists:subcategories,id'],
            'sale_mode'          => ['required', 'in:weight,unit'],
            'price_per_kg_usd'   => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,weight'],
            'price_per_unit_usd' => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,unit'],
            'min_stock'          => ['nullable', 'numeric', 'min:0'],
            'fabricable'         => ['boolean'],
            'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $businessId = Auth::user()->business_id;

        $product = Product::create([
            'business_id'        => $businessId,
            'category_id'        => $validated['category_id'],
            'subcategory_id'     => $validated['subcategory_id'] ?? null,
            'name'               => $validated['name'],
            'sale_mode'          => $validated['sale_mode'],
            'base_unit_label'    => $validated['sale_mode'] === 'weight' ? 'kg' : 'und',
            'price_per_kg_usd'   => $validated['price_per_kg_usd'] ?? null,
            'price_per_unit_usd' => $validated['price_per_unit_usd'] ?? null,
            'min_stock'          => $validated['min_stock'] ?? 0,
            'fabricable'         => $validated['fabricable'] ?? false,
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

        return redirect()->route('catalog.index')->with('success', 'Producto creado.');
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name'               => ['required', 'string', 'max:120'],
            'category_id'        => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id'     => ['nullable', 'integer', 'exists:subcategories,id'],
            'sale_mode'          => ['required', 'in:weight,unit'],
            'price_per_kg_usd'   => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,weight'],
            'price_per_unit_usd' => ['nullable', 'numeric', 'min:0', 'required_if:sale_mode,unit'],
            'min_stock'          => ['nullable', 'numeric', 'min:0'],
            'fabricable'         => ['boolean'],
            'active'             => ['boolean'],
            'image'              => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image'       => ['nullable', 'boolean'],
        ]);

        $updates = [
            'category_id'        => $validated['category_id'],
            'subcategory_id'     => $validated['subcategory_id'] ?? null,
            'name'               => $validated['name'],
            'sale_mode'          => $validated['sale_mode'],
            'base_unit_label'    => $validated['sale_mode'] === 'weight' ? 'kg' : 'und',
            'price_per_kg_usd'   => $validated['price_per_kg_usd'] ?? null,
            'price_per_unit_usd' => $validated['price_per_unit_usd'] ?? null,
            'min_stock'          => $validated['min_stock'] ?? 0,
            'fabricable'         => $validated['fabricable'] ?? $product->fabricable,
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

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:80'],
            'color'          => ['nullable', 'string', 'max:20'],
            'icon'           => ['nullable', 'string', 'max:80'],
            'macro_category' => ['nullable', 'string', 'in:RES,POLLO,CERDO,TRASTES,MISC'],
        ]);

        $businessId = Auth::user()->business_id;

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
