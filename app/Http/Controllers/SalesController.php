<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesController extends Controller
{
    // ─── Listado de ventas del día ────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        $date    = $request->input('date', now()->toDateString());
        $cashier = $request->input('cashier');
        $method  = $request->input('method');
        $status  = $request->input('status');

        $query = Sale::with(['cashier:id,name', 'salePayments.paymentMethod'])
            ->where('business_id', $businessId)
            ->whereDate('sold_at', $date)
            ->latest('sold_at');

        if ($cashier) {
            $query->where('cashier_id', $cashier);
        }

        if ($method) {
            $query->where('payment_method', $method);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $sales = $query->get()->map(function (Sale $sale): array {
            return [
                'id'                  => $sale->id,
                'ticket_number'       => $sale->ticket_number,
                'status'              => $sale->status,
                'cashier'             => $sale->cashier?->name,
                'payment_method'      => $sale->payment_method,
                'total_bs'            => (float) $sale->total_bs,
                'total_usd'           => (float) $sale->total_usd,
                'rate_used'           => (float) $sale->rate_used,
                'sold_at'             => $sale->sold_at?->format('H:i'),
                'items_count'         => $sale->items->count(),
                'cancelled_at'        => $sale->cancelled_at?->format('d/m H:i'),
                'cancellation_reason' => $sale->cancellation_reason,
            ];
        });

        // Totales del día
        $totals = [
            'total_bs'     => (float) $sales->where('status', 'paid')->sum('total_bs'),
            'total_ventas' => $sales->where('status', 'paid')->count(),
            'anuladas'     => $sales->where('status', 'cancelled')->count(),
        ];

        // Filtros disponibles
        $cashiers = User::where('business_id', $businessId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'type']);

        return Inertia::render('Sales/Index', [
            'sales'          => $sales,
            'totals'         => $totals,
            'cashiers'       => $cashiers,
            'paymentMethods' => $paymentMethods,
            'filters'        => compact('date', 'cashier', 'method', 'status'),
        ]);
    }

    // ─── Anular venta ─────────────────────────────────────────────────────────

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        $user = Auth::user();

        abort_if(
            ! in_array($user->role, ['admin', 'supervisor'], true),
            403,
            'Sin permiso para anular ventas.'
        );

        abort_if($sale->business_id !== $user->business_id, 403);

        abort_if(
            $sale->status !== 'paid',
            422,
            'Solo se pueden anular ventas pagadas.'
        );

        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($sale, $user, $request): void {
            $sale->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancelled_by'        => $user->id,
                'cancellation_reason' => $request->input('reason'),
            ]);

            ActivityLog::create([
                'business_id' => $user->business_id,
                'user_id'     => $user->id,
                'action'      => 'void_sale',
                'model_type'  => Sale::class,
                'model_id'    => $sale->id,
                'old_values'  => ['status' => 'paid'],
                'new_values'  => [
                    'status' => 'cancelled',
                    'reason' => $request->input('reason'),
                ],
                'ip_address'  => $request->ip(),
            ]);
        });

        // Invalidar cache de stock crítico
        cache()->forget("stock_critico_count_{$user->business_id}");

        return back()->with('success', "Venta {$sale->ticket_number} anulada.");
    }
}
