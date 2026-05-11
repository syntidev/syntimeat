<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\CashRegister;
use App\Models\InventoryEntry;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Services\DollarRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function __construct(private readonly DollarRateService $rates) {}

    // ─── POS View ────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;

        $products = Product::with(['category', 'subcategory'])
            ->where('business_id', $businessId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        $categories = Category::where('business_id', $businessId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        $cashRegister = CashRegister::where('business_id', $businessId)
            ->whereNull('closed_at')
            ->whereDate('opened_at', today())
            ->first();

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('POS/Index', [
            'products'       => $products,
            'categories'     => $categories,
            'cashRegister'   => $cashRegister,
            'todayRate'      => $this->rates->getTodayRate(),
            'paymentMethods' => $paymentMethods,
            'ticketPrefix'   => $business->ticket_prefix ?? 'VEN',
        ]);
    }

    // ─── Crear venta (status=open) ────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity_value' => ['required', 'numeric', 'min:0.001'],
            'items.*.input_type'     => ['required', 'string', 'in:weight,unit'],
        ]);

        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();
        $products   = Product::whereIn('id', $productIds)
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('id');

        foreach ($productIds as $pid) {
            abort_unless($products->has($pid), 403, 'Producto no pertenece al negocio.');
        }

        $totalUsd      = 0.0;
        $itemsToCreate = [];

        foreach ($data['items'] as $item) {
            $product   = $products[$item['product_id']];
            $qty       = (float) $item['quantity_value'];
            $priceKg   = (float) ($product->price_per_kg_usd ?? 0);
            $priceUnit = (float) ($product->price_per_unit_usd ?? 0);
            $subtotal  = $item['input_type'] === 'weight'
                ? $qty * $priceKg
                : $qty * $priceUnit;

            $totalUsd += $subtotal;

            $itemsToCreate[] = [
                'product_id'       => $product->id,
                'product_name'     => $product->name,
                'input_type'       => $item['input_type'],
                'quantity_value'   => $qty,
                'unit_label'       => $product->base_unit_label ?? ($item['input_type'] === 'weight' ? 'kg' : 'und'),
                'price_per_kg_usd' => $priceKg,
                'price_per_unit_usd' => $priceUnit,
                'subtotal_usd'     => round($subtotal, 2),
                'discount_usd'     => 0,
            ];
        }

        $ticketNumber = $this->generateTicketNumber($businessId, $business->ticket_prefix ?? 'VEN');

        $sale = DB::transaction(function () use ($businessId, $user, $ticketNumber, $totalUsd, $itemsToCreate) {
            $sale = Sale::create([
                'business_id'   => $businessId,
                'ticket_number' => $ticketNumber,
                'status'        => 'open',
                'total_usd'     => round($totalUsd, 2),
                'cashier_id'    => $user->id,
            ]);

            foreach ($itemsToCreate as $item) {
                $sale->items()->create($item);
            }

            return $sale->load('items');
        });

        return response()->json(['sale' => $sale]);
    }

    // ─── Confirmar pago ───────────────────────────────────────────────────────

    public function pay(Request $request, Sale $sale): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        abort_unless($sale->business_id === $businessId, 403);
        abort_unless($sale->status === 'open', 422, 'Venta no está abierta.');

        $data = $request->validate([
            'payment_method_id'  => ['required', 'integer', 'exists:payment_methods,id'],
            'amount_received_bs' => ['required', 'numeric', 'min:0'],
        ]);

        $paymentMethod = PaymentMethod::where('id', $data['payment_method_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        $rate            = $this->rates->getTodayRate();
        $totalUsd        = (float) $sale->total_usd;
        $totalBs         = round($totalUsd * $rate, 2);
        $amountReceivedBs  = (float) $data['amount_received_bs'];
        $changeBs        = max(0.0, $amountReceivedBs - $totalBs);
        $amountReceivedUsd = $rate > 0 ? round($amountReceivedBs / $rate, 2) : 0.0;
        $changeUsd       = $rate > 0 ? round($changeBs / $rate, 2) : 0.0;

        DB::transaction(function () use (
            $sale, $rate, $totalBs, $amountReceivedUsd, $changeUsd,
            $paymentMethod, $businessId, $user
        ) {
            $sale->update([
                'status'             => 'paid',
                'rate_used'          => $rate,
                'total_bs'           => $totalBs,
                'payment_method'     => substr($paymentMethod->name, 0, 30),
                'amount_received_usd' => $amountReceivedUsd,
                'change_usd'         => $changeUsd,
                'sold_at'            => now(),
            ]);

            // Descontar inventario solo para items tipo weight
            foreach ($sale->items as $item) {
                if ($item->input_type !== 'weight') {
                    continue;
                }

                InventoryEntry::create([
                    'business_id'    => $businessId,
                    'product_id'     => $item->product_id,
                    'quantity_kg'    => -abs((float) $item->quantity_value),
                    'waste_kg'       => 0,
                    'entered_at'     => now(),
                    'created_by'     => $user->id,
                    'notes'          => "Venta {$sale->ticket_number}",
                ]);
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'sale.paid',
                'model_type'  => Sale::class,
                'model_id'    => $sale->id,
                'new_values'  => [
                    'ticket_number' => $sale->ticket_number,
                    'total_usd'     => $sale->total_usd,
                    'total_bs'      => $totalBs,
                    'rate_used'     => $rate,
                    'method'        => $paymentMethod->name,
                ],
            ]);
        });

        return response()->json(['sale' => $sale->fresh()]);
    }

    // ─── Cancelar venta (solo admin = primer usuario del negocio) ────────────

    public function cancel(Request $request, Sale $sale): JsonResponse
    {
        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;

        abort_unless($sale->business_id === $businessId, 403);
        abort_unless(! in_array($sale->status, ['cancelled'], true), 422, 'Venta ya está cancelada.');

        // Admin = primer usuario registrado del negocio
        $ownerId = $business->users()->orderBy('id')->value('id');
        abort_unless($user->id === $ownerId, 403, 'Solo el administrador puede cancelar ventas.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($sale, $data, $user, $businessId) {
            $sale->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancelled_by'        => $user->id,
                'cancellation_reason' => $data['reason'],
            ]);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'sale.cancelled',
                'model_type'  => Sale::class,
                'model_id'    => $sale->id,
                'new_values'  => [
                    'ticket_number' => $sale->ticket_number,
                    'reason'        => $data['reason'],
                ],
            ]);
        });

        return response()->json(['ok' => true]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateTicketNumber(int $businessId, string $prefix): string
    {
        $count = Sale::where('business_id', $businessId)->count() + 1;
        return $prefix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }
}
