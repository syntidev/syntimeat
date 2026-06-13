<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CashRegister;
use App\Models\InventoryEntry;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Models\PaymentTerminal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleAbono;
use App\Models\SalePayment;
use App\Services\DollarRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly DollarRateService $rates) {}

    // ─── Vista principal ──────────────────────────────────────────────────────

    public function index(): Response
    {
        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;
        $rate       = $this->rates->getTodayRate();
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $activeOrders = Order::with(['items.product.category'])
            ->where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['open', 'pending'])
            ->orderByDesc('created_at')
            ->get();

        $historial = Order::with(['items'])
            ->where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereIn('status', ['paid', 'cancelled', 'completed'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get();

        $products = Product::with('category')
            ->where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $paymentTerminals = PaymentTerminal::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'bank_name', 'method', 'commercial_number']);

        $cobradosHoy = Order::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'paid')
            ->whereDate('updated_at', today())
            ->count();

        // Cobros pendientes: crédito (payment_status) + delivery (status=pending)
        $creditPending   = Sale::with(['items', 'cashier'])
            ->where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('payment_status', 'pendiente_cobro')
            ->orderByDesc('created_at')
            ->get();

        $deliveryPending = Sale::with(['items', 'cashier'])
            ->where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('origin', 'delivery')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        $cobrosPendientes = $creditPending->merge($deliveryPending)
            ->sortByDesc('created_at')
            ->map(fn (Sale $s) => $this->formatSalePending($s, $rate))
            ->values();

        return Inertia::render('Orders/Index', [
            'pedidosActivos'  => $activeOrders->map(fn (Order $o) => $this->formatOrder($o, $rate))->values(),
            'historial'       => $historial->map(fn (Order $o) => $this->formatOrderBrief($o, $rate))->values(),
            'cobrosPendientes'=> $cobrosPendientes,
            'products'        => $products,
            'paymentMethods'   => $paymentMethods,
            'paymentTerminals' => $paymentTerminals,
            'todayRate'        => $rate,
            'kpis'            => [
                'abiertos'           => $activeOrders->count(),
                'internos'           => $activeOrders->where('client_type', 'internal')->count(),
                'total_pendiente_bs' => round($activeOrders->sum(fn ($o) => (float) $o->total_usd * $rate), 2),
                'cobrados_hoy'       => $cobradosHoy,
                'cobros_pendientes'  => $cobrosPendientes->count(),
            ],
        ]);
    }

    // ─── Crear pedido ─────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_name'            => ['required', 'string', 'max:100'],
            'client_type'            => ['required', 'in:internal,external'],
            'notes'                  => ['nullable', 'string', 'max:500'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity_value' => ['required', 'numeric', 'min:0.001'],
            'items.*.input_type'     => ['required', 'in:weight,unit'],
        ]);

        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? \App\Models\Branch::where('business_id', $businessId)->orderBy('id')->value('id'));
        $rate       = $this->rates->getTodayRate();

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
                'product_id'         => $product->id,
                'product_name'       => $product->name,
                'input_type'         => $item['input_type'],
                'quantity_value'     => $qty,
                'unit_label'         => $product->base_unit_label ?? ($item['input_type'] === 'weight' ? 'kg' : 'und'),
                'price_per_kg_usd'   => $priceKg,
                'price_per_unit_usd' => $priceUnit,
                'subtotal_usd'       => round($subtotal, 2),
            ];
        }

        $order = DB::transaction(function () use ($businessId, $branchId, $user, $data, $totalUsd, $itemsToCreate) {
            $order = Order::create([
                'business_id' => $businessId,
                'branch_id'   => $branchId,
                'client_name' => $data['client_name'],
                'client_type' => $data['client_type'],
                'status'      => 'pending',
                'total_usd'   => round($totalUsd, 2),
                'notes'       => $data['notes'] ?? null,
                'created_by'  => $user->id,
            ]);

            foreach ($itemsToCreate as $item) {
                $order->items()->create($item);
            }

            return $order->load(['items.product.category']);
        });

        return response()->json(['order' => $this->formatOrder($order, $rate)]);
    }

    // ─── Cobrar pedido (abonos parciales) ───────────────────────────────────

    public function collect(Request $request, Order $order): JsonResponse
    {
        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;

        abort_unless($order->business_id === $businessId, 403);
        abort_unless(in_array($order->status, ['open', 'pending'], true), 422, 'Pedido no está activo.');

        $cashRegister = CashRegister::where('business_id', $businessId)
            ->where('opened_by', $user->id)
            ->whereNull('closed_at')
            ->first();

        if (! $cashRegister) {
            return response()->json(['error' => 'Debe abrir su caja antes de procesar pagos.'], 422);
        }

        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount_bs'         => ['required', 'numeric', 'min:0.01'],
            'rate'              => ['required', 'numeric', 'min:0.0001'],
            'reference'         => ['nullable', 'string', 'max:50'],
            'notes'             => ['nullable', 'string', 'max:255'],
        ]);

        $method = PaymentMethod::where('id', $data['payment_method_id'])
            ->where('business_id', $businessId)
            ->firstOrFail();

        $rate      = (float) $data['rate'];
        $amountBs  = round((float) $data['amount_bs'], 2);
        $amountUsd = $rate > 0 ? round($amountBs / $rate, 4) : 0.0;
        $totalUsd  = (float) $order->total_usd;
        $totalBs   = round($totalUsd * $rate, 2);

        $result = DB::transaction(function () use (
            $order, $rate, $totalBs, $totalUsd, $amountBs, $amountUsd,
            $method, $data, $businessId, $user, $cashRegister
        ) {
            // 1. Registrar abono
            OrderPayment::create([
                'order_id'          => $order->id,
                'business_id'       => $businessId,
                'branch_id'         => $order->branch_id,
                'payment_method_id' => $method->id,
                'amount_bs'         => $amountBs,
                'amount_usd'        => $amountUsd,
                'rate'              => $rate,
                'notes'             => $data['notes'] ?? $data['reference'] ?? null,
                'created_by'        => $user->id,
            ]);

            // 2. Sumar acumulado en orders
            $newPaidBs  = round((float) $order->amount_paid_bs + $amountBs, 2);
            $newPaidUsd = round((float) $order->amount_paid_usd + $amountUsd, 4);
            $order->update([
                'amount_paid_bs'  => $newPaidBs,
                'amount_paid_usd' => $newPaidUsd,
            ]);

            // 3. Descontar inventario solo la primera vez
            if (! $order->inventory_released) {
                foreach ($order->items as $item) {
                    if ($item->input_type !== 'weight') {
                        continue;
                    }
                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'product_id'  => $item->product_id,
                        'quantity_kg' => -abs((float) $item->quantity_value),
                        'waste_kg'    => 0,
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Abono pedido #{$order->id}",
                    ]);
                }
                $order->update(['inventory_released' => true]);
            }

            // 4. Marcar completado si saldo saldado (margen 0.1%)
            $balanceBs = round($totalBs - $newPaidBs, 2);
            if ($newPaidBs >= $totalBs * 0.999) {
                $prefix       = $order->business->ticket_prefix ?? 'VEN';
                $ticketNum    = Sale::where('business_id', $businessId)->count() + 1;
                $ticketNumber = 'P-' . $prefix . '-' . str_pad((string) $ticketNum, 4, '0', STR_PAD_LEFT);

                $sale = Sale::create([
                    'business_id'         => $businessId,
                    'ticket_number'       => $ticketNumber,
                    'status'              => 'paid',
                    'payment_status'      => 'paid',
                    'order_id'            => $order->id,
                    'total_usd'           => $totalUsd,
                    'rate_used'           => $rate,
                    'total_bs'            => $totalBs,
                    'payment_method'      => substr($method->name, 0, 30),
                    'amount_received_usd' => round($newPaidBs / $rate, 2),
                    'change_usd'          => 0,
                    'sold_at'             => now(),
                    'cashier_id'          => $user->id,
                    'cash_register_id'    => $cashRegister->id,
                    'client_name'         => $order->client_name,
                    'notes'               => "Pedido #{$order->id} — abonos",
                ]);

                foreach ($order->items as $item) {
                    $sale->items()->create([
                        'product_id'         => $item->product_id,
                        'product_name'       => $item->product_name,
                        'input_type'         => $item->input_type,
                        'quantity_value'     => $item->quantity_value,
                        'unit_label'         => $item->unit_label,
                        'price_per_kg_usd'   => $item->price_per_kg_usd,
                        'price_per_unit_usd' => $item->price_per_unit_usd,
                        'subtotal_usd'       => $item->subtotal_usd,
                        'discount_usd'       => 0,
                    ]);
                }

                foreach ($order->payments()->get() as $pmt) {
                    SalePayment::create([
                        'sale_id'           => $sale->id,
                        'payment_method_id' => $pmt->payment_method_id,
                        'amount_bs'         => (float) $pmt->amount_bs,
                        'amount_usd'        => (float) $pmt->amount_usd,
                        'reference'         => null,
                    ]);
                }

                $order->update(['status' => 'completed']);
                $balanceBs = 0;

                ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id'     => $user->id,
                    'action'      => 'order.completed',
                    'model_type'  => Order::class,
                    'model_id'    => $order->id,
                    'new_values'  => [
                        'ticket_number' => $ticketNumber,
                        'total_bs'      => $totalBs,
                        'rate_used'     => $rate,
                    ],
                ]);
            } else {
                ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id'     => $user->id,
                    'action'      => 'order.partial_payment',
                    'model_type'  => Order::class,
                    'model_id'    => $order->id,
                    'new_values'  => [
                        'amount_bs'  => $amountBs,
                        'paid_bs'    => $newPaidBs,
                        'balance_bs' => $balanceBs,
                    ],
                ]);
            }

            return [
                'completed'  => $order->fresh()->status === 'completed',
                'paid_bs'    => $newPaidBs,
                'balance_bs' => $balanceBs,
                'total_bs'   => $totalBs,
            ];
        });

        return response()->json(array_merge(['success' => true, 'order_id' => $order->id], $result));
    }

    // ─── Historial de abonos de un pedido ─────────────────────────────────────

    public function payments(Order $order): JsonResponse
    {
        abort_unless($order->business_id === Auth::user()->business_id, 403);
        $rate = $this->rates->getTodayRate();
        return response()->json([
            'payments'   => $order->payments()->with('paymentMethod')->get(),
            'total_bs'   => round((float) $order->total_usd * $rate, 2),
            'paid_bs'    => (float) $order->amount_paid_bs,
            'balance_bs' => max(0, round((float) $order->total_usd * $rate, 2) - (float) $order->amount_paid_bs),
        ]);
    }


    // ─── Despachar sin cobrar (crédito temporal) ─────────────────────────────

    public function dispatch(Request $request, Order $order): JsonResponse
    {
        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;

        abort_unless($order->business_id === $businessId, 403);
        abort_unless(in_array($order->status, ['open', 'pending'], true), 422, 'Pedido no está activo.');

        $rate      = $this->rates->getTodayRate();
        $totalUsd  = (float) $order->total_usd;
        $totalBs   = round($totalUsd * $rate, 2);
        $prefix    = $business->ticket_prefix ?? 'VEN';
        $ticketNum = Sale::where('business_id', $businessId)->count() + 1;
        $ticket    = 'P-' . $prefix . '-' . str_pad((string) $ticketNum, 4, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($order, $rate, $totalUsd, $totalBs, $businessId, $user, $ticket) {
            $sale = Sale::create([
                'business_id'    => $businessId,
                'ticket_number'  => $ticket,
                'status'         => 'paid',
                'payment_status' => 'pendiente_cobro',
                'order_id'       => $order->id,
                'total_usd'      => $totalUsd,
                'rate_used'      => $rate,
                'total_bs'       => $totalBs,
                'sold_at'        => now(),
                'cashier_id'     => $user->id,
                'client_name'    => $order->client_name,
                'origin'         => 'delivery',
                'notes'          => "Pedido #{$order->id} — despachado sin cobrar",
            ]);

            foreach ($order->items as $item) {
                $sale->items()->create([
                    'product_id'         => $item->product_id,
                    'product_name'       => $item->product_name,
                    'input_type'         => $item->input_type,
                    'quantity_value'     => $item->quantity_value,
                    'unit_label'         => $item->unit_label,
                    'price_per_kg_usd'   => $item->price_per_kg_usd,
                    'price_per_unit_usd' => $item->price_per_unit_usd,
                    'subtotal_usd'       => $item->subtotal_usd,
                    'discount_usd'       => 0,
                ]);

                // Descuento de inventario al salir (no al cobrar)
                if ($item->input_type === 'weight') {
                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'product_id'  => $item->product_id,
                        'quantity_kg' => -abs((float) $item->quantity_value),
                        'waste_kg'    => 0,
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Despacho pedido #{$order->id} — {$ticket}",
                    ]);
                }
            }

            $order->update(['status' => 'paid']);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'order.dispatched',
                'model_type'  => Order::class,
                'model_id'    => $order->id,
                'new_values'  => ['ticket_number' => $ticket, 'total_bs' => $totalBs, 'payment_status' => 'pendiente_cobro'],
            ]);
        });

        return response()->json(['ok' => true, 'order_id' => $order->id]);
    }

    // ─── Registrar cobro en venta pendiente (abonos parciales) ──────────────────

    public function collectPending(Request $request, Sale $sale): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        abort_unless($sale->business_id === $businessId, 403);
        abort_unless($sale->payment_status === 'pendiente_cobro', 422, 'Venta ya fue cobrada.');

        $cashRegister = CashRegister::where('business_id', $businessId)
            ->where('opened_by', $user->id)
            ->whereNull('closed_at')
            ->first();

        if (! $cashRegister) {
            return response()->json(['error' => 'Debe abrir su caja antes de registrar cobros.'], 422);
        }

        $data = $request->validate([
            'payments'                          => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id'      => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount_bs'              => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference'              => ['nullable', 'string', 'max:50'],
            'rate'                              => ['required', 'numeric', 'min:0.0001'],
            'client_name'                       => ['nullable', 'string', 'max:100'],
            'client_phone'                      => ['nullable', 'string', 'max:30'],
            'client_id'                         => ['nullable', 'integer'],
        ]);

        $rate         = (float) $data['rate'];
        $totalBs      = $sale->total_bs !== null
            ? (float) $sale->total_bs
            : round((float) $sale->total_usd * $rate, 2);
        $totalAbonoBs = round(array_sum(array_column($data['payments'], 'amount_bs')), 2);

        $methodIds = array_column($data['payments'], 'payment_method_id');
        $methods   = PaymentMethod::whereIn('id', $methodIds)
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('id');

        foreach ($methodIds as $mid) {
            abort_unless($methods->has($mid), 403, 'Método de pago no pertenece al negocio.');
        }

        $result = DB::transaction(function () use (
            $sale, $rate, $totalBs, $totalAbonoBs, $data, $methods,
            $businessId, $user, $cashRegister
        ) {
            // a. Crear sale_abonos
            foreach ($data['payments'] as $pmt) {
                $amtBs  = round((float) $pmt['amount_bs'], 2);
                $amtUsd = $rate > 0 ? round($amtBs / $rate, 4) : 0.0;
                SaleAbono::create([
                    'sale_id'           => $sale->id,
                    'business_id'       => $businessId,
                    'branch_id'         => $sale->branch_id,
                    'payment_method_id' => $pmt['payment_method_id'],
                    'amount_bs'         => $amtBs,
                    'amount_usd'        => $amtUsd,
                    'rate'              => $rate,
                    'reference'         => $pmt['reference'] ?? null,
                    'created_by'        => $user->id,
                ]);
            }

            // b. Sumar acumulado
            $newPaidBs  = round((float) $sale->amount_paid_bs + $totalAbonoBs, 2);
            $newPaidUsd = $rate > 0 ? round($newPaidBs / $rate, 4) : 0.0;
            $sale->update([
                'amount_paid_bs'  => $newPaidBs,
                'amount_paid_usd' => $newPaidUsd,
            ]);

            // c. Descuento de inventario para delivery (solo la primera vez)
            if (! $sale->inventory_released && $sale->origin === 'delivery') {
                foreach ($sale->items as $item) {
                    if ($item->input_type !== 'weight') {
                        continue;
                    }
                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'product_id'  => $item->product_id,
                        'quantity_kg' => -abs((float) $item->quantity_value),
                        'waste_kg'    => 0,
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Abono delivery {$sale->ticket_number}",
                    ]);
                }
                $sale->update(['inventory_released' => true]);
            }

            $balanceBs = round($totalBs - $newPaidBs, 2);

            // d. Marcar pagado si saldo cubierto (margen 0.1%)
            if ($newPaidBs >= $totalBs * 0.999) {
                $firstMethod = $methods[$data['payments'][0]['payment_method_id']];

                $sale->update([
                    'status'              => 'paid',
                    'payment_status'      => 'paid',
                    'rate_used'           => $rate,
                    'total_bs'            => $totalBs,
                    'payment_method'      => substr($firstMethod->name, 0, 30),
                    'amount_received_usd' => $rate > 0 ? round($newPaidBs / $rate, 2) : 0.0,
                    'change_usd'          => 0,
                    'sold_at'             => now(),
                    'cash_register_id'    => $cashRegister->id,
                ]);

                if (empty($sale->getRawOriginal('accounting_date'))) {
                    $nowCollect = now('America/Caracas');
                    $acctDate   = $nowCollect->hour >= 19
                        ? $nowCollect->copy()->addDay()->toDateString()
                        : $nowCollect->toDateString();
                    $sale->update(['accounting_date' => $acctDate]);
                }

                foreach ($sale->abonos()->get() as $abono) {
                    SalePayment::create([
                        'sale_id'           => $sale->id,
                        'payment_method_id' => $abono->payment_method_id,
                        'amount_bs'         => (float) $abono->amount_bs,
                        'amount_usd'        => (float) $abono->amount_usd,
                        'reference'         => $abono->reference,
                    ]);
                }

                $balanceBs = 0;

                ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id'     => $user->id,
                    'action'      => 'sale.pending_collected',
                    'model_type'  => Sale::class,
                    'model_id'    => $sale->id,
                    'new_values'  => ['ticket_number' => $sale->ticket_number, 'total_bs' => $totalBs],
                ]);
            } else {
                ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id'     => $user->id,
                    'action'      => 'sale.partial_payment',
                    'model_type'  => Sale::class,
                    'model_id'    => $sale->id,
                    'new_values'  => [
                        'amount_bs'  => $totalAbonoBs,
                        'paid_bs'    => $newPaidBs,
                        'balance_bs' => $balanceBs,
                    ],
                ]);
            }

            return [
                'completed'  => $sale->fresh()->payment_status === 'paid',
                'paid_bs'    => $newPaidBs,
                'balance_bs' => $balanceBs,
                'total_bs'   => $totalBs,
            ];
        });

        return response()->json(array_merge(['ok' => true, 'sale_id' => $sale->id], $result));
    }

    // ─── Historial de abonos de una venta pendiente ───────────────────────────

    public function saleAbonos(Sale $sale): JsonResponse
    {
        abort_unless($sale->business_id === Auth::user()->business_id, 403);
        $rate    = $this->rates->getTodayRate();
        $totalBs = $sale->total_bs !== null
            ? (float) $sale->total_bs
            : round((float) $sale->total_usd * $rate, 2);
        return response()->json([
            'abonos'     => $sale->abonos()->with('paymentMethod')->get(),
            'total_bs'   => $totalBs,
            'paid_bs'    => (float) $sale->amount_paid_bs,
            'balance_bs' => max(0, $totalBs - (float) $sale->amount_paid_bs),
        ]);
    }


        // ─── Cancelar pedido ──────────────────────────────────────────────────────

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;

        abort_unless($order->business_id === $businessId, 403);
        abort_unless(in_array($order->status, ['open', 'pending'], true), 422, 'Pedido no está activo.');

        // Admin = primer usuario registrado del negocio
        $ownerId = $business->users()->orderBy('id')->value('id');
        abort_unless($user->id === $ownerId, 403, 'Solo el administrador puede cancelar pedidos.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        DB::transaction(function () use ($order, $data, $user, $businessId) {
            $order->update(['status' => 'cancelled']);

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'order.cancelled',
                'model_type'  => Order::class,
                'model_id'    => $order->id,
                'new_values'  => ['reason' => $data['reason']],
            ]);
        });

        return response()->json(['ok' => true, 'order_id' => $order->id]);
    }

    // ─── Vista delivery activo ────────────────────────────────────────────────

    public function deliveryIndex(): Response
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $rate       = $this->rates->getTodayRate();
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $pending = Sale::with(['items', 'cashier'])
            ->where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->where('origin', 'delivery')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $s) => $this->formatSaleDelivery($s, $rate))
            ->values();

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Orders/Delivery', [
            'pedidos'        => $pending,
            'paymentMethods' => $paymentMethods,
            'todayRate'      => $rate,
        ]);
    }

    // ─── Confirmar cobro de delivery (Sale con origin=delivery) ──────────────

    public function confirmDelivery(Request $request, Sale $sale): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        abort_unless($sale->business_id === $businessId, 403);
        abort_unless($sale->origin === 'delivery', 422, 'Venta no es delivery.');
        abort_unless($sale->status === 'pending', 422, 'Venta no está pendiente de cobro.');

        $cashRegister = CashRegister::where('business_id', $businessId)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->whereNull('closed_at')
            ->first();

        if (! $cashRegister) {
            return response()->json(['error' => 'Debe abrir la caja antes de procesar pagos'], 422);
        }

        $data = $request->validate([
            'payments'                     => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount_bs'         => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference'         => ['nullable', 'string', 'max:50'],
        ]);

        $rate    = $this->rates->getTodayRate();
        $totalBs = (float) $sale->total_bs;   // leer el Bs. guardado al momento de crear la venta

        $methodIds = array_column($data['payments'], 'payment_method_id');
        $methods   = PaymentMethod::whereIn('id', $methodIds)
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('id');

        foreach ($methodIds as $mid) {
            abort_unless($methods->has($mid), 403, 'Método de pago no pertenece al negocio.');
        }

        $sumPaidBs = array_sum(array_column($data['payments'], 'amount_bs'));
        if (round($sumPaidBs, 2) < $totalBs) {
            return response()->json(
                ['error' => "Monto insuficiente. Total: {$totalBs} Bs., Pagado: " . round($sumPaidBs, 2) . ' Bs.'],
                422
            );
        }

        $firstMethod = $methods[$data['payments'][0]['payment_method_id']];

        DB::transaction(function () use ($sale, $rate, $totalBs, $firstMethod, $data, $businessId, $user, $cashRegister) {
            $sale->update([
                'status'                => 'paid',
                'delivery_status'       => 'collected',
                'delivery_confirmed_at' => now(),
                'rate_used'             => $rate,
                'total_bs'              => $totalBs,
                'payment_method'        => substr($firstMethod->name, 0, 30),
                'amount_received_usd'   => $rate > 0 ? round(array_sum(array_column($data['payments'], 'amount_bs')) / $rate, 2) : 0.0,
                'change_usd'            => 0,
                'sold_at'               => now(),
                'cash_register_id'      => $cashRegister->id,
            ]);

            foreach ($data['payments'] as $pmt) {
                SalePayment::create([
                    'sale_id'           => $sale->id,
                    'payment_method_id' => $pmt['payment_method_id'],
                    'amount_bs'         => round((float) $pmt['amount_bs'], 2),
                    'amount_usd'        => $rate > 0 ? round((float) $pmt['amount_bs'] / $rate, 2) : 0.0,
                    'reference'         => $pmt['reference'] ?? null,
                ]);
            }

            foreach ($sale->items as $item) {
                if ($item->input_type !== 'weight') {
                    continue;
                }

                InventoryEntry::create([
                    'business_id' => $businessId,
                    'product_id'  => $item->product_id,
                    'quantity_kg' => -abs((float) $item->quantity_value),
                    'waste_kg'    => 0,
                    'entered_at'  => now(),
                    'created_by'  => $user->id,
                    'notes'       => "Delivery cobrado {$sale->ticket_number}",
                ]);
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'sale.delivery_confirmed',
                'model_type'  => Sale::class,
                'model_id'    => $sale->id,
                'new_values'  => ['ticket_number' => $sale->ticket_number, 'total_bs' => $totalBs],
            ]);
        });

        return response()->json(['ok' => true, 'sale_id' => $sale->id]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatOrder(Order $order, float $rate): array
    {
        return [
            'id'          => $order->id,
            'client_name' => $order->client_name,
            'client_type' => $order->client_type,
            'status'      => $order->status,
            'total_usd'   => (float) $order->total_usd,
            'total_bs'    => round((float) $order->total_usd * $rate, 2),
            'notes'           => $order->notes,
            'amount_paid_bs'  => (float) $order->amount_paid_bs,
            'amount_paid_usd' => (float) $order->amount_paid_usd,
            'created_at'      => $order->created_at?->toDateTimeString(),
            'updated_at'  => $order->updated_at?->toDateTimeString(),
            'items'       => $order->items->map(fn (OrderItem $i) => [
                'id'             => $i->id,
                'product_id'     => $i->product_id,
                'product_name'   => $i->product_name,
                'input_type'     => $i->input_type,
                'quantity_value' => (float) $i->quantity_value,
                'unit_label'     => $i->unit_label,
                'subtotal_usd'   => (float) $i->subtotal_usd,
                'subtotal_bs'    => round((float) $i->subtotal_usd * $rate, 2),
                'category'       => $i->relationLoaded('product') && $i->product
                    ? ($i->product->category?->name ?? '—')
                    : '—',
            ])->values()->toArray(),
        ];
    }

    private function formatOrderBrief(Order $order, float $rate): array
    {
        return [
            'id'          => $order->id,
            'client_name' => $order->client_name,
            'client_type' => $order->client_type,
            'status'      => $order->status,
            'total_bs'    => round((float) $order->total_usd * $rate, 2),
            'items_count' => $order->items->count(),
            'updated_at'  => $order->updated_at?->toDateTimeString(),
        ];
    }

    private function formatSalePending(Sale $sale, float $rate): array
    {
        // sale_type determina qué endpoint usa el frontend al cobrar
        $saleType = $sale->origin === 'delivery' ? 'delivery' : 'credit';

        return [
            'id'            => $sale->id,
            'ticket_number' => $sale->ticket_number,
            'client_name'   => $sale->client_name,
            'client_phone'  => $sale->client_phone,
            'origin'        => $sale->origin,
            'sale_type'     => $saleType,
            'total_usd'     => (float) $sale->total_usd,
            'total_bs'      => (float) $sale->total_bs,
            'cashier_name'  => $sale->cashier?->name ?? '—',
            'created_at'    => $sale->created_at?->toDateTimeString(),
            'order_id'      => $sale->order_id,
            'amount_paid_bs' => (float) $sale->amount_paid_bs,
            'items'         => $sale->items->map(fn ($i) => [
                'product_name'   => $i->product_name,
                'input_type'     => $i->input_type,
                'quantity_value' => (float) $i->quantity_value,
                'unit_label'     => $i->unit_label,
                'subtotal_bs'    => (float) $i->subtotal_bs,
            ])->values()->toArray(),
        ];
    }

    private function formatSaleDelivery(Sale $sale, float $rate): array
    {
        return [
            'id'             => $sale->id,
            'ticket_number'  => $sale->ticket_number,
            'status'         => $sale->status,
            'origin'         => $sale->origin,
            'channel'        => $sale->channel,
            'delivery_status'=> $sale->delivery_status,
            'client_name'    => $sale->client_name,
            'client_phone'   => $sale->client_phone,
            'total_usd'      => (float) $sale->total_usd,
            'total_bs'       => (float) $sale->total_bs,
            'cashier_name'   => $sale->cashier?->name ?? '—',
            'created_at'     => $sale->created_at?->toDateTimeString(),
            'items'          => $sale->items->map(fn ($i) => [
                'product_name'   => $i->product_name,
                'input_type'     => $i->input_type,
                'quantity_value' => (float) $i->quantity_value,
                'unit_label'     => $i->unit_label,
                'subtotal_usd'   => (float) $i->subtotal_usd,
                'subtotal_bs'    => (float) $i->subtotal_bs,
            ])->values()->toArray(),
        ];
    }
}
