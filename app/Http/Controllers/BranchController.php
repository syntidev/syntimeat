<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\InventoryEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    // Macro-categorías que se copian como plantilla a cada nueva sucursal
    private const TEMPLATE_MACROS = ['RES', 'POLLO', 'CERDO', 'TRASTES'];

    // ─── Vista principal ──────────────────────────────────────────────────────

    public function index(): Response
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        // Auto-inicializar Sede Principal si el negocio no tiene sucursales
        $this->ensurePrincipalBranch($businessId);

        // Cajas agrupadas por sucursal para el panel
        $cajasPorSucursal = CashRegister::where('business_id', $businessId)
            ->get(['id', 'name', 'branch_id', 'opened_at', 'closed_at'])
            ->groupBy('branch_id');

        $branches = Branch::withCount('users')
            ->where('business_id', $businessId)
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $b) => [
                'id'              => $b->id,
                'name'            => $b->name,
                'address'         => $b->address,
                'city'            => $b->city,
                'phone'           => $b->phone,
                'is_active'       => $b->is_active,
                'access_start'    => $b->access_start,
                'access_end'      => $b->access_end,
                'users_count'     => $b->users_count,
                'products_count'  => Product::where('branch_id', $b->id)->count(),
                'cash_registers'  => $cajasPorSucursal->get($b->id, collect())->values(),
            ]);

        $users = User::where('business_id', $businessId)
            ->whereNotIn('role', ['owner', 'super_admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'branch_id', 'is_active']);

        return Inertia::render('Settings/Branches', [
            'branches' => $branches,
            'users'    => $users,
        ]);
    }

    // ─── Crear sucursal ───────────────────────────────────────────────────────

    public function store(Request $request): RedirectResponse
    {
        $businessId = Auth::user()->business_id;

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:30'],
        ]);

        DB::transaction(function () use ($data, $businessId) {
            $branch = Branch::create([...$data, 'business_id' => $businessId]);
            $this->seedTemplateProducts($branch, $businessId);
        });

        return back()->with('success', 'Sucursal creada con productos plantilla.');
    }

    // ─── Editar sucursal ──────────────────────────────────────────────────────

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($branch->business_id === Auth::user()->business_id, 403);

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'address'   => ['nullable', 'string', 'max:255'],
            'city'      => ['nullable', 'string', 'max:100'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ]);

        $branch->update($data);

        return back()->with('success', 'Sucursal actualizada.');
    }

    // ─── Asignar usuario a sucursal ───────────────────────────────────────────

    public function assignUser(Request $request, Branch $branch): RedirectResponse
    {
        $businessId = Auth::user()->business_id;
        abort_unless($branch->business_id === $businessId, 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::where('id', $data['user_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        abort_if(in_array($user->role, ['owner', 'super_admin'], true), 422, 'El owner no se asigna a sucursales.');

        $user->update(['branch_id' => $branch->id]);

        return back()->with('success', "{$user->name} asignado a {$branch->name}.");
    }

    // ─── Desasignar usuario de sucursal ──────────────────────────────────────

    public function unassignUser(Request $request, Branch $branch): RedirectResponse
    {
        $businessId = Auth::user()->business_id;
        abort_unless($branch->business_id === $businessId, 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::where('id', $data['user_id'])
            ->where('business_id', $businessId)
            ->where('branch_id', $branch->id)
            ->firstOrFail();

        $user->update(['branch_id' => null]);

        return back()->with('success', "{$user->name} desasignado.");
    }

    // ─── Helpers privados ─────────────────────────────────────────────────────

    /**
     * Si el negocio no tiene ninguna sucursal, crea "Sede Principal"
     * y backfilla todos los registros huérfanos (branch_id=null).
     */
    private function ensurePrincipalBranch(int $businessId): void
    {
        if (Branch::where('business_id', $businessId)->exists()) {
            return;
        }

        $business = Business::find($businessId);

        DB::transaction(function () use ($businessId, $business) {
            $branch = Branch::create([
                'business_id' => $businessId,
                'name'        => 'Sede Principal',
                'address'     => $business?->address,
                'city'        => $business?->city,
                'phone'       => $business?->phone,
                'is_active'   => true,
            ]);

            // Backfill de todos los registros existentes sin sucursal
            Product::where('business_id', $businessId)->whereNull('branch_id')
                ->update(['branch_id' => $branch->id]);

            User::where('business_id', $businessId)
                ->whereNull('branch_id')
                ->whereNotIn('role', ['owner', 'super_admin'])
                ->update(['branch_id' => $branch->id]);

            Sale::where('business_id', $businessId)->whereNull('branch_id')
                ->update(['branch_id' => $branch->id]);

            InventoryEntry::where('business_id', $businessId)->whereNull('branch_id')
                ->update(['branch_id' => $branch->id]);

            CashRegister::where('business_id', $businessId)->whereNull('branch_id')
                ->update(['branch_id' => $branch->id]);
        });
    }

    /**
     * Copia los productos de macro-categorías plantilla desde la Sede Principal
     * a la nueva sucursal.
     */
    private function seedTemplateProducts(Branch $newBranch, int $businessId): void
    {
        $principal = Branch::where('business_id', $businessId)
            ->where('name', 'Sede Principal')
            ->first();

        if (! $principal) {
            return;
        }

        $templates = Product::with('category')
            ->where('business_id', $businessId)
            ->where('branch_id', $principal->id)
            ->whereHas('category', fn ($q) => $q->whereIn('macro_category', self::TEMPLATE_MACROS))
            ->get();

        foreach ($templates as $product) {
            Product::create([
                'business_id'       => $businessId,
                'branch_id'         => $newBranch->id,
                'category_id'       => $product->category_id,
                'subcategory_id'    => $product->subcategory_id,
                'name'              => $product->name,
                'sku'               => null, // evitar duplicados de SKU único
                'barcode'           => null,
                'sale_mode'         => $product->sale_mode,
                'base_unit_label'   => $product->base_unit_label,
                'fraction_allowed'  => $product->fraction_allowed,
                'price_per_kg_usd'  => $product->price_per_kg_usd,
                'price_per_unit_usd'=> $product->price_per_unit_usd,
                'cost_per_kg_usd'   => $product->cost_per_kg_usd,
                'cost_per_unit_usd' => $product->cost_per_unit_usd,
                'min_stock'         => $product->min_stock,
                'sort_order'        => $product->sort_order,
                'active'            => $product->active,
                'fabricable'        => $product->fabricable,
            ]);
        }
    }
}
