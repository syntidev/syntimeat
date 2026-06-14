<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    // ─── Listado principal ────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user       = Auth::user();
        $businessId = $user->business_id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $query = Client::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->withCount('sales')
            ->withMax('sales', 'sold_at');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('cedula', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('name')->paginate(20)->withQueryString();

        $kpiBase    = Client::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $total      = (clone $kpiBase)->count();
        $newMonth   = (clone $kpiBase)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $saleBase   = Sale::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));
        $identified = (clone $saleBase)->whereNotNull('client_id')->count();
        $anonymous  = (clone $saleBase)->whereNull('client_id')->count();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'kpis'    => compact('total', 'newMonth', 'identified', 'anonymous'),
            'filters' => ['q' => $request->get('q', '')],
        ]);
    }

    // ─── Crear ────────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business_id;
        $branchId   = $user->branch_id
            ?? session('current_branch_id')
            ?? \App\Models\Branch::where('business_id', $businessId)->orderBy('id')->value('id');

        $data = $request->validate([
            'cedula'  => ['nullable', 'string', 'max:20'],
            'name'    => ['required', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
        ]);

        if (! empty($data['cedula'])) {
            $exists = Client::where('business_id', $businessId)
                ->where('cedula', $data['cedula'])
                ->exists();

            if ($exists) {
                return response()->json(['errors' => ['cedula' => ['Ya existe un cliente con esa cédula.']]], 422);
            }
        }

        if (! empty($data['phone'])) {
            $exists = Client::where('business_id', $businessId)
                ->where('phone', $data['phone'])
                ->exists();

            if ($exists) {
                return response()->json(['errors' => ['phone' => ['Ya existe un cliente con ese teléfono.']]], 422);
            }
        }

        $client = Client::create([
            ...$data,
            'business_id' => $businessId,
            'branch_id'   => $branchId,
        ]);

        return response()->json(['ok' => true, 'id' => $client->id]);
    }

    // ─── Actualizar ───────────────────────────────────────────────────────────

    public function update(Request $request, Client $client): JsonResponse
    {
        $businessId = Auth::user()->business_id;
        abort_unless($client->business_id === $businessId, 403);

        $data = $request->validate([
            'cedula'  => ['nullable', 'string', 'max:20'],
            'name'    => ['required', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
            'active'  => ['boolean'],
        ]);

        if (! empty($data['cedula'])) {
            $exists = Client::where('business_id', $businessId)
                ->where('cedula', $data['cedula'])
                ->where('id', '!=', $client->id)
                ->exists();

            if ($exists) {
                return response()->json(['errors' => ['cedula' => ['Ya existe un cliente con esa cédula.']]], 422);
            }
        }

        if (! empty($data['phone'])) {
            $exists = Client::where('business_id', $businessId)
                ->where('phone', $data['phone'])
                ->where('id', '!=', $client->id)
                ->exists();

            if ($exists) {
                return response()->json(['errors' => ['phone' => ['Ya existe un cliente con ese teléfono.']]], 422);
            }
        }

        $client->update($data);

        return response()->json(['ok' => true]);
    }

    // ─── Eliminar ─────────────────────────────────────────────────────────────

    public function destroy(Client $client): JsonResponse
    {
        $businessId = Auth::user()->business_id;
        abort_unless($client->business_id === $businessId, 403);

        if ($client->sales()->exists()) {
            $client->update(['active' => false]);
            return response()->json(['ok' => true, 'deactivated' => true, 'message' => 'Cliente desactivado (tiene ventas asociadas).']);
        }

        $client->delete();
        return response()->json(['ok' => true, 'deactivated' => false]);
    }

    // ─── Detalle + historial ──────────────────────────────────────────────────

    public function show(Client $client): Response
    {
        $businessId = Auth::user()->business_id;
        abort_unless($client->business_id === $businessId, 403);

        $sales = Sale::where('client_id', $client->id)
            ->with(['items', 'salePayments.paymentMethod'])
            ->orderByDesc('sold_at')
            ->limit(20)
            ->get();

        return Inertia::render('Clients/Index', [
            'client'      => $client,
            'clientSales' => $sales,
            'clients'     => [],
            'kpis'        => null,
            'filters'     => ['q' => ''],
        ]);
    }

    // ─── Búsqueda JSON para POS ───────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business_id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $q = $request->get('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clients = Client::where('business_id', $businessId)
            ->when($branchId, fn ($q2) => $q2->where('branch_id', $branchId))
            ->where('active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('cedula', 'like', "%{$q}%");
            })
            ->select('id', 'cedula', 'name', 'phone')
            ->limit(10)
            ->get();

        return response()->json($clients);
    }
}
