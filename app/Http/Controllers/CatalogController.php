<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Subcategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(): Response
    {
        $businessId = Auth::user()->business_id;

        $categories = Category::with(['subcategories' => fn ($q) => $q->orderBy('sort_order')])
            ->where('business_id', $businessId)
            ->orderBy('sort_order')
            ->get();

        $products = Product::with(['category', 'subcategory'])
            ->where('business_id', $businessId)
            ->orderBy('category_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

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
        ]);

        $businessId = Auth::user()->business_id;

        Product::create([
            'business_id'        => $businessId,
            'category_id'        => $validated['category_id'],
            'subcategory_id'     => $validated['subcategory_id'] ?? null,
            'name'               => $validated['name'],
            'sale_mode'          => $validated['sale_mode'],
            'base_unit_label'    => $validated['sale_mode'] === 'weight' ? 'kg' : 'und',
            'price_per_kg_usd'   => $validated['price_per_kg_usd'] ?? null,
            'price_per_unit_usd' => $validated['price_per_unit_usd'] ?? null,
            'min_stock'          => $validated['min_stock'] ?? 0,
            'active'             => true,
        ]);

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
            'active'             => ['boolean'],
        ]);

        $product->update([
            'category_id'        => $validated['category_id'],
            'subcategory_id'     => $validated['subcategory_id'] ?? null,
            'name'               => $validated['name'],
            'sale_mode'          => $validated['sale_mode'],
            'base_unit_label'    => $validated['sale_mode'] === 'weight' ? 'kg' : 'und',
            'price_per_kg_usd'   => $validated['price_per_kg_usd'] ?? null,
            'price_per_unit_usd' => $validated['price_per_unit_usd'] ?? null,
            'min_stock'          => $validated['min_stock'] ?? 0,
            'active'             => $validated['active'] ?? $product->active,
        ]);

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

    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon'  => ['nullable', 'string', 'max:80'],
        ]);

        $businessId = Auth::user()->business_id;

        $maxSort = Category::where('business_id', $businessId)->max('sort_order') ?? 0;

        Category::create([
            'business_id' => $businessId,
            'name'        => $validated['name'],
            'color'       => $validated['color'] ?? null,
            'icon'        => $validated['icon'] ?? null,
            'sort_order'  => $maxSort + 1,
            'active'      => true,
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
            'name'  => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:20'],
            'icon'  => ['nullable', 'string', 'max:80'],
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
}
