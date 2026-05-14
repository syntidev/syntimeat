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
        $businessId = Auth::user()->business->id;

        $query = Client::where('business_id', $businessId)
            ->withCount('sales')
            ->withMax('sales', 'sold_at');

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('client_code', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderBy('name')->paginate(20)->withQueryString();

        // KPIs
        $total     = Client::where('business_id', $businessId)->count();
        $newMonth  = Client::where('business_id', $businessId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $identified = Sale::where('business_id', $businessId)
            ->whereNotNull('client_id')
            ->count();
        $anonymous  = Sale::where('business_id', $businessId)
            ->whereNull('client_id')
            ->count();

        return Inertia::render('Clients/Index', [
            'clients'       => $clients,
            'kpis'          => compact('total', 'newMonth', 'identified', 'anonymous'),
            'filters'       => ['q' => $request->get('q', '')],
        ]);
    }

    // ─── Crear ────────────────────────────────────────────────────────────────

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $businessId = Auth::user()->business->id;

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
        ]);

        if (! empty($data['phone'])) {
            $exists = Client::where('business_id', $businessId)
                ->where('phone', $data['phone'])
                ->exists();

            if ($exists) {
                return back()->withErrors(['phone' => 'Ya existe un cliente con ese teléfono.']);
            }
        }

        Client::create(['business_id' => $businessId, ...$data]);

        return back();
    }

    // ─── Actualizar ───────────────────────────────────────────────────────────

    public function update(Request $request, Client $client): \Illuminate\Http\RedirectResponse
    {
        $businessId = Auth::user()->business->id;
        abort_unless($client->business_id === $businessId, 403);

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'email'   => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string'],
            'notes'   => ['nullable', 'string'],
            'active'  => ['boolean'],
        ]);

        if (! empty($data['phone'])) {
            $exists = Client::where('business_id', $businessId)
                ->where('phone', $data['phone'])
                ->where('id', '!=', $client->id)
                ->exists();

            if ($exists) {
                return back()->withErrors(['phone' => 'Ya existe un cliente con ese teléfono.']);
            }
        }

        $client->update($data);

        return back();
    }

    // ─── Detalle + historial ──────────────────────────────────────────────────

    public function show(Client $client): Response
    {
        $businessId = Auth::user()->business->id;
        abort_unless($client->business_id === $businessId, 403);

        $sales = Sale::where('client_id', $client->id)
            ->with(['items', 'salePayments.paymentMethod'])
            ->orderByDesc('sold_at')
            ->limit(20)
            ->get();

        return Inertia::render('Clients/Index', [
            'client'        => $client,
            'clientSales'   => $sales,
            'clients'       => [],
            'kpis'          => null,
            'filters'       => ['q' => ''],
        ]);
    }

    // ─── Búsqueda JSON para POS ───────────────────────────────────────────────

    public function search(Request $request): JsonResponse
    {
        $businessId = Auth::user()->business->id;
        $q          = $request->get('q', '');

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clients = Client::where('business_id', $businessId)
            ->where('active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('client_code', 'like', "%{$q}%");
            })
            ->select('id', 'client_code', 'name', 'phone')
            ->limit(10)
            ->get();

        return response()->json($clients);
    }
}
