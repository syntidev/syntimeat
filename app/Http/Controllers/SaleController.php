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
use App\Models\SalePayment;
use App\Models\User;
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

        $branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $products = Product::with(['category', 'subcategory'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('active', true)
            ->where('location', '!=', 'boveda')
            ->orderBy('sort_order')
            ->get();

        $favoritos = Product::where('business_id', $businessId)
            ->where('is_favorite', true)
            ->where('active', true)
            ->where('location', '!=', 'boveda')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get(['id', 'name', 'price_per_kg_usd', 'price_per_unit_usd', 'sale_mode']);

        $categories = Category::where('business_id', $businessId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->get();

        // Caja activa: la fijada en sesión, o la única abierta del branch (fallback).
        $cashRegister = CashRegister::resolveActive($businessId, $branchId, session('active_cash_register_id'));

        // Cajas abiertas del branch — el POS pide elegir cuando hay más de una.
        $openRegisters = CashRegister::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('closed_at')
            ->orderBy('opened_at')
            ->get(['id', 'name'])
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]);

        // ─── Stock map para badges de inventario ──────────────────────────────
        $stockIn = InventoryEntry::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('product_id, SUM(net_kg) as total_net')
            ->groupBy('product_id')
            ->pluck('total_net', 'product_id');

        // Stock = SUM(net_kg) UNICAMENTE. Las ventas ya estan en net_kg como
        // entradas negativas ("Venta"/"Credito"/"Delivery"); NUNCA restar de nuevo.
        $stockMap = [];
        foreach ($products as $product) {
            $poolId = $product->stock_product_id ?? $product->id;
            $stockMap[$product->id] = round((float) ($stockIn[$poolId] ?? 0), 3);
        }

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $posShowKg = (bool) ($business->settings['pos']['show_kg_visual'] ?? false);

        return Inertia::render('POS/Index', [
            'products'       => $products,
            'categories'     => $categories,
            'cashRegister'   => $cashRegister,
            'openRegisters'  => $openRegisters,
            'todayRate'      => $this->rates->getTodayRate(),
            'paymentMethods' => $paymentMethods,
            'ticketPrefix'   => $business->ticket_prefix ?? 'VEN',
            'stockMap'       => $stockMap,
            'favoritos'      => $favoritos,
            'posShowKg'      => $posShowKg,
            'businessInfo'   => [
                'name'          => $business->name,
                'address'       => $business->address,
                'city'          => $business->city,
                'state'         => $business->state,
                'phone'         => $business->phone,
                'ticket_footer' => $business->ticket_footer,
                'printer_name'  => $business->settings['printer_name'] ?? '',
                'paper_width'   => $business->settings['paper_width']  ?? '80',
            ],
            'ticketPrefs'    => [
                'show_address' => (bool) ($business->settings['ticket']['show_address'] ?? true),
                'show_phone'   => (bool) ($business->settings['ticket']['show_phone']   ?? false),
                'show_client'  => (bool) ($business->settings['ticket']['show_client']  ?? true),
            ],
        ]);
    }

    // ─── Crear venta (status=open) ────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'     => ['required', 'integer', 'exists:products,id'],
            'items.*.input_type'     => ['required', 'string', 'in:weight,unit'],
            // weight items: send amount_bs (new) OR quantity_value (legacy)
            'items.*.amount_bs'      => ['sometimes', 'numeric', 'min:0.01'],
            'items.*.quantity_value' => ['sometimes', 'numeric', 'min:0.001'],
            'origin'                 => ['sometimes', 'string', 'in:onsite,delivery,credit'],
            'channel'                => ['sometimes', 'string', 'in:physical,online'],
            'client_name'            => ['nullable', 'string', 'max:100'],
            'client_phone'           => ['nullable', 'string', 'max:30'],
            'client_id'              => ['nullable', 'integer'],
        ]);

        $user       = Auth::user();
        $business   = $user->business;
        $businessId = $business->id;
        $branchId   = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? $user->branch_id);

        // Rate needed to convert amount_bs → quantity_kg for weight items
        $rate = $this->rates->getTodayRate();

        $productIds = collect($data['items'])->pluck('product_id')->unique()->values()->all();
        $products   = Product::whereIn('id', $productIds)
            ->where('business_id', $businessId)
            ->get()
            ->keyBy('id');

        foreach ($productIds as $pid) {
            abort_unless($products->has($pid), 403, 'Producto no pertenece al negocio.');
        }

        $totalUsd      = 0.0;
        $totalBs       = 0.0;
        $itemsToCreate = [];

        foreach ($data['items'] as $item) {
            $product   = $products[$item['product_id']];
            $inputType = $item['input_type'];
            $priceKg   = (float) ($product->price_per_kg_usd ?? 0);
            $priceUnit = (float) ($product->price_per_unit_usd ?? 0);

            if ($inputType === 'weight') {
                // Support both: amount_bs (new paradigm) and quantity_value (legacy)
                if (!empty($item['amount_bs'])) {
                    $amountBs   = (float) $item['amount_bs'];
                    $qty        = ($priceKg > 0 && $rate > 0) ? round($amountBs / ($priceKg * $rate), 3) : 0.0;
                    $subtotal   = $rate > 0 ? $amountBs / $rate : 0.0;
                    $subtotalBs = $amountBs; // Bs. exactos del cajero — sin reconvertir
                } else {
                    $qty        = (float) ($item['quantity_value'] ?? 0);
                    $subtotal   = $qty * $priceKg;
                    $subtotalBs = round($subtotal * $rate, 2);
                }
            } else {
                $qty        = (float) ($item['quantity_value'] ?? 0);
                $subtotal   = $qty * $priceUnit;
                $subtotalBs = round($subtotal * $rate, 2);
            }

            $totalUsd += $subtotal;
            $totalBs  += $subtotalBs;

            $itemsToCreate[] = [
                'product_id'         => $product->id,
                'product_name'       => $product->name,
                'input_type'         => $inputType,
                'quantity_value'     => $qty,
                'unit_label'         => $product->base_unit_label ?? ($inputType === 'weight' ? 'kg' : 'und'),
                'price_per_kg_usd'   => $priceKg,
                'price_per_unit_usd' => $priceUnit,
                'subtotal_usd'       => round($subtotal, 2),
                'subtotal_bs'        => $subtotalBs,
                'rate_used'          => $rate,
                'discount_usd'       => 0,
            ];
        }

        $ticketNumber = $this->generateTicketNumber($businessId, $business->ticket_prefix ?? 'VEN');

        // Validar stock suficiente antes de crear la venta
        foreach ($itemsToCreate as $item) {
            if ($item['input_type'] !== 'weight') {
                continue;
            }
            $stockCheckId    = Product::find($item['product_id'])?->stock_product_id ?? $item['product_id'];
            $stockDisponible = InventoryEntry::where('business_id', $businessId)
                ->where('product_id', $stockCheckId)
                ->selectRaw('SUM(quantity_kg - waste_kg) as total')
                ->value('total');

            $vendido = DB::table('sale_items')
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.business_id', $businessId)
                ->where('sales.status', 'paid')
                ->where('sale_items.product_id', $item['product_id'])
                ->sum('sale_items.quantity_value');

            $disponible = (float) $stockDisponible - (float) $vendido;

            // Stock negativo permitido — carnicería vende bajo pedido
            // if ((float) $item['quantity_value'] > $disponible) {
            //     return response()->json(['error' => 'Stock insuficiente para ese producto.'], 422);
            // }
        }

        $origin  = $data['origin']  ?? 'onsite';
        $channel = $data['channel'] ?? 'physical';

        // Cliente obligatorio para crédito/delivery (validación explícita — robusta ante empty→null)
        if (in_array($origin, ['credit', 'delivery'], true) && blank($data['client_name'] ?? null)) {
            return response()->json(['error' => 'Cliente obligatorio para crédito/delivery.'], 422);
        }

        // Verificar client_id contra el negocio (igual que pay())
        $clientId = null;
        if (! empty($data['client_id'])) {
            $exists = \App\Models\Client::where('id', $data['client_id'])
                ->where('business_id', $businessId)
                ->exists();
            abort_unless($exists, 403, 'Cliente no pertenece al negocio.');
            $clientId = (int) $data['client_id'];
        }

        // Caja activa del branch (delivery/crédito pueden no tener caja — continúa sin bloquear)
        $cashRegister = CashRegister::resolveActive($businessId, $branchId, session('active_cash_register_id'));

        // Crédito: despacho inmediato, cobro pendiente
        if ($origin === 'credit') {
            $nowCredit      = now('America/Caracas');
            $accountingDate = $nowCredit->toDateString();

            $sale = DB::transaction(function () use (
                $businessId, $branchId, $user, $ticketNumber, $totalUsd, $totalBs, $itemsToCreate, $channel, $rate, $cashRegister, $accountingDate, $data, $clientId
            ) {

                $sale = Sale::create([
                    'business_id'     => $businessId,
                    'branch_id'       => $branchId,
                    'ticket_number'   => $ticketNumber,
                    'status'          => 'pending',
                    'payment_status'  => 'pendiente_cobro',
                    'total_usd'       => round($totalUsd, 2),
                    'total_bs'        => $totalBs,
                    'rate_used'       => $rate,
                    'sold_at'         => now(),
                    'accounting_date' => $accountingDate,
                    'cashier_id'      => $user->id,
                    'origin'          => 'credit',
                    'channel'         => $channel,
                    'client_name'     => $data['client_name']  ?? null,
                    'client_phone'    => $data['client_phone'] ?? null,
                    'client_id'       => $clientId,
                    'cash_register_id' => $cashRegister?->id,
                ]);

                foreach ($itemsToCreate as $item) {
                    $sale->items()->create($item);
                }

                $sale->load('items');

                // Descontar inventario inmediatamente (despacho sin cobro)
                foreach ($sale->items as $item) {
                    if ($item->input_type !== 'weight') {
                        continue;
                    }
                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'branch_id'   => $branchId,
                        'product_id'  => $item->product_id,
                        'quantity_kg' => -abs((float) $item->quantity_value),
                        'waste_kg'    => 0,
                        'location'    => 'vitrina',
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Crédito {$sale->ticket_number}",
                    ]);
                }

                ActivityLog::create([
                    'business_id' => $businessId,
                    'user_id'     => $user->id,
                    'action'      => 'sale.credit_dispatched',
                    'model_type'  => Sale::class,
                    'model_id'    => $sale->id,
                    'new_values'  => ['ticket_number' => $sale->ticket_number, 'total_bs' => $totalBs],
                ]);

                return $sale;
            });

            return response()->json(['sale' => $sale, 'credit' => true]);
        }

        $status  = $origin === 'delivery' ? 'pending' : 'open';

        $sale = DB::transaction(function () use ($businessId, $branchId, $user, $ticketNumber, $totalUsd, $totalBs, $itemsToCreate, $origin, $channel, $status, $data, $cashRegister, $clientId) {
            $sale = Sale::create([
                'business_id'     => $businessId,
                'branch_id'       => $branchId,
                'ticket_number'   => $ticketNumber,
                'status'          => $status,
                'total_usd'       => round($totalUsd, 2),
                'total_bs'        => round($totalBs, 2),
                'cashier_id'      => $user->id,
                'origin'          => $origin,
                'channel'         => $channel,
                'client_name'     => $data['client_name']  ?? null,
                'client_phone'    => $data['client_phone'] ?? null,
                'client_id'       => $clientId,
                'cash_register_id'=> $cashRegister?->id,
            ]);

            foreach ($itemsToCreate as $item) {
                $sale->items()->create($item);
            }

            $sale->load('items');

            // Delivery descuenta inventario al generar el ticket (despacho sin cobro),
            // igual que crédito. Onsite ('open') NO descuenta hasta pay().
            if ($origin === 'delivery') {
                foreach ($sale->items as $item) {
                    if ($item->input_type !== 'weight') {
                        continue;
                    }
                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'branch_id'   => $branchId,
                        'product_id'  => $item->product_id,
                        'quantity_kg' => -abs((float) $item->quantity_value),
                        'waste_kg'    => 0,
                        'location'    => 'vitrina',
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Delivery {$sale->ticket_number}",
                    ]);
                }
            }

            return $sale;
        });

        return response()->json(['sale' => $sale]);
    }

    // ─── Confirmar pago (multi-método) ───────────────────────────────────────

    public function pay(Request $request, Sale $sale): JsonResponse
    {
        $user       = Auth::user();
        $businessId = $user->business->id;

        abort_unless($sale->business_id === $businessId, 403);
        abort_unless($sale->status === 'open', 422, 'Venta no está abierta.');

        $branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? $user->branch_id);

        // Caja activa: la fijada en sesión, o la única abierta del branch (fallback).
        $cashRegister = CashRegister::resolveActive($businessId, $branchId, session('active_cash_register_id'));

        if (! $cashRegister) {
            return response()->json(
                ['error' => 'Debe abrir y seleccionar una caja antes de procesar pagos'],
                422
            );
        }

        $data = $request->validate([
            'payments'                          => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id'      => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.amount_bs'              => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference'              => ['nullable', 'string', 'max:50'],            'client_id'                          => ['nullable', 'integer'],            'client_name'                       => ['nullable', 'string', 'max:100'],
            'client_phone'                      => ['nullable', 'string', 'max:30'],
        ]);

        $rate    = $this->rates->getTodayRate();
        $totalBs = (float) $sale->total_bs;

        // Validar métodos de pago del negocio y calcular suma
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

        // Verificar client_id si viene
        $clientId = null;
        if (! empty($data['client_id'])) {
            $exists = \App\Models\Client::where('id', $data['client_id'])
                ->where('business_id', $businessId)
                ->exists();
            abort_unless($exists, 403, 'Cliente no pertenece al negocio.');
            $clientId = (int) $data['client_id'];
        }

        $changeBs  = round($sumPaidBs - $totalBs, 2);
        $changeUsd = $rate > 0 ? round($changeBs / $rate, 2) : 0.0;

        // Para compatibilidad con campos legacy de Sale, tomar el primer método
        $firstMethod = $methods[$data['payments'][0]['payment_method_id']];

        $nowPay           = now('America/Caracas');
        $accountingDatePay = $nowPay->toDateString();

        DB::transaction(function () use (
            $sale, $rate, $totalBs, $changeBs, $changeUsd,
            $firstMethod, $data, $methods, $businessId, $branchId, $user, $cashRegister, $clientId, $accountingDatePay
        ) {
            $sale->update([
                'status'              => 'paid',
                'payment_status'      => 'paid',
                'rate_used'           => $rate,
                'total_bs'            => $totalBs,
                'payment_method'      => substr($firstMethod->name, 0, 30),
                'amount_received_usd' => $rate > 0 ? round(array_sum(array_column($data['payments'], 'amount_bs')) / $rate, 2) : 0.0,
                'change_usd'          => $changeUsd,
                'sold_at'             => now(),
                'accounting_date'     => $accountingDatePay,
                'cash_register_id'    => $cashRegister->id,
                'client_name'         => $data['client_name'] ?? null,
                'client_phone'        => $data['client_phone'] ?? null,
                'client_id'           => $clientId,
            ]);

            // Registrar cada pago en sale_payments
            foreach ($data['payments'] as $pmt) {
                SalePayment::create([
                    'sale_id'           => $sale->id,
                    'payment_method_id' => $pmt['payment_method_id'],
                    'amount_bs'         => round((float) $pmt['amount_bs'], 2),
                    'amount_usd'        => $rate > 0 ? round((float) $pmt['amount_bs'] / $rate, 2) : 0.0,
                    'reference'         => $pmt['reference'] ?? null,
                ]);
            }

            // ── FIFO: descontar por lote más antiguo primero ──────────────
            $sale->load('items.product');
            foreach ($sale->items as $item) {
                if ($item->input_type !== 'weight') {
                    continue;
                }

                $stockProductId = $item->product?->stock_product_id ?? $item->product_id;
                $kgRestante     = round(abs((float) $item->quantity_value), 4);

                // Lotes positivos en vitrina ordenados por entered_at ASC
                $lotes = \App\Models\InventoryEntry::where('business_id', $businessId)
                    ->where('branch_id', $branchId)
                    ->where('product_id', $stockProductId)
                    ->where('location', 'vitrina')
                    ->where('quantity_kg', '>', 0)
                    ->whereNotNull('boveda_entry_id')
                    ->orderBy('entered_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();

                foreach ($lotes as $lote) {
                    if ($kgRestante <= 0) break;
                    $kgDescontar = min($kgRestante, (float) $lote->quantity_kg);
                    \App\Models\InventoryEntry::create([
                        'business_id'     => $businessId,
                        'branch_id'       => $branchId,
                        'product_id'      => $stockProductId,
                        'boveda_entry_id' => $lote->boveda_entry_id,
                        'quantity_kg'     => -$kgDescontar,
                        'waste_kg'        => 0,
                        'cost_per_kg_usd' => $lote->cost_per_kg_usd,
                        'location'        => 'vitrina',
                        'entered_at'      => now(),
                        'created_by'      => $user->id,
                        'notes'           => "Venta FIFO {$sale->ticket_number} lote#{$lote->id}",
                    ]);
                    $kgRestante = round($kgRestante - $kgDescontar, 4);
                }

                // kg sin lote conocido — stock negativo permitido
                if ($kgRestante > 0.0001) {
                    \App\Models\InventoryEntry::create([
                        'business_id' => $businessId,
                        'branch_id'   => $branchId,
                        'product_id'  => $stockProductId,
                        'quantity_kg' => -$kgRestante,
                        'waste_kg'    => 0,
                        'location'    => 'vitrina',
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Venta FIFO {$sale->ticket_number} sin lote",
                    ]);
                }
            }

            ActivityLog::create([
                'business_id' => $businessId,
                'user_id'     => $user->id,
                'action'      => 'sale.paid',
                'model_type'  => Sale::class,
                'model_id'    => $sale->id,
                'new_values'  => [
                    'ticket_number'  => $sale->ticket_number,
                    'total_usd'      => (float) $sale->total_usd,
                    'total_bs'       => $totalBs,
                    'rate_used'      => $rate,
                    'payments_count' => count($data['payments']),
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

            // Revertir inventario si la venta estaba pagada o pending (credit/delivery despachan con inventario)
            if (in_array($sale->getOriginal('status'), ['paid', 'pending'])) {
                $sale->load('items.product');
                foreach ($sale->items as $item) {
                    if ($item->input_type !== 'weight') {
                        continue;
                    }

                    $stockProductId = $item->product?->stock_product_id ?? $item->product_id;

                    InventoryEntry::create([
                        'business_id' => $businessId,
                        'product_id'  => $stockProductId,
                        'quantity_kg' => abs((float) $item->quantity_value),
                        'waste_kg'    => 0,
                        'location'    => 'vitrina',
                        'entered_at'  => now(),
                        'created_by'  => $user->id,
                        'notes'       => "Reverso anulación {$sale->ticket_number}",
                    ]);
                }
            }

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

        cache()->forget("stock_critico_count_{$businessId}");

        return response()->json(['ok' => true]);
    }

    // ─── Historial de ventas del día ─────────────────────────────────────────

    public function historial(Request $request): Response
    {
        $user       = Auth::user();
        $businessId = $user->business_id;

        $branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : (session('current_branch_id') ?? null);

        $date    = $request->input('date', now()->toDateString());
        $cashier = $request->input('cashier');
        $method  = $request->input('method');
        $status  = $request->input('status');

        $query = Sale::with(['cashier:id,name', 'salePayments.paymentMethod', 'items.product'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('sold_at', $date)
            ->latest('sold_at');

        if ($cashier) {
            $query->where('cashier_id', $cashier);
        }

        if ($method) {
            $query->whereHas('salePayments', fn ($q) => $q->where('payment_method_id', $method));
        }

        if ($status) {
            $query->where('status', $status);
        }

        $sales = $query->get()->map(function (Sale $sale): array {
            return [
                'id'                  => $sale->id,
                'ticket_number'       => $sale->ticket_number,
                'status'              => $sale->status,
                'payment_status'      => $sale->payment_status,
                'origin'              => $sale->origin,
                'cashier'             => $sale->cashier?->name,
                'payment_method'      => $sale->payment_method,
                'total_bs'            => (float) $sale->total_bs,
                'total_usd'           => (float) $sale->total_usd,
                'rate_used'           => (float) $sale->rate_used,
                'sold_at'             => $sale->sold_at?->format('H:i'),
                'client_name'         => $sale->client_name,
                'items_count'         => $sale->items->count(),
                'items'               => $sale->items->map(fn ($i) => [
                    'product_name'   => $i->product?->name ?? $i->product_name ?? '—',
                    'quantity_value' => (float) $i->quantity_value,
                    'input_type'     => $i->input_type,
                    'subtotal_bs'    => (float) $i->subtotal_bs,
                    'subtotal_usd'   => (float) $i->subtotal_usd,
                ])->values(),
                'payments'            => $sale->salePayments->map(fn ($p) => [
                    'method' => $p->paymentMethod?->name ?? '—',
                    'amount_bs' => (float) $p->amount_bs,
                ])->values(),
                'cancelled_at'        => $sale->cancelled_at?->format('d/m H:i'),
                'cancellation_reason' => $sale->cancellation_reason,
            ];
        });

        $totals = [
            'total_bs'     => (float) $sales->where('status', 'paid')->sum('total_bs'),
            'total_ventas' => $sales->where('status', 'paid')->count(),
            'anuladas'     => $sales->where('status', 'cancelled')->count(),
        ];

        $cashiers = User::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $paymentMethods = PaymentMethod::where('business_id', $businessId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'type']);

        $rate = $this->rates->getTodayRate();

        $cobrosPendientes = Sale::with(['items', 'cashier'])
            ->where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where(function ($q) {
                $q->where('payment_status', 'pendiente_cobro')
                  ->orWhere(fn ($q2) => $q2->where('origin', 'delivery')->where('status', 'pending'));
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Sale $s) => [
                'id'           => $s->id,
                'ticket_number'=> $s->ticket_number,
                'sale_type'    => $s->origin === 'delivery' ? 'delivery' : 'credit',
                'origin'       => $s->origin,
                'client_name'  => $s->client_name,
                'client_phone' => $s->client_phone,
                'total_usd'    => (float) $s->total_usd,
                'total_bs'     => (float) $s->total_bs,
                'cashier_name' => $s->cashier?->name ?? '—',
                'created_at'   => $s->created_at?->toDateTimeString(),
                'items'        => $s->items->map(fn ($i) => [
                    'product_name'   => $i->product_name,
                    'quantity_value' => (float) $i->quantity_value,
                    'unit_label'     => $i->unit_label,
                    'subtotal_bs'    => (float) $i->subtotal_bs,
                ])->values(),
            ])
            ->values();

        return Inertia::render('Sales/Index', [
            'sales'            => $sales,
            'totals'           => $totals,
            'cashiers'         => $cashiers,
            'paymentMethods'   => $paymentMethods,
            'cobrosPendientes' => $cobrosPendientes,
            'todayRate'        => $rate,
            'filters'          => compact('date', 'cashier', 'method', 'status'),
        ]);
    }

    // ─── Anular venta (admin / super_admin) ─────────────────────────────────

    public function void(Request $request, Sale $sale): JsonResponse
    {
        $user = Auth::user();

        abort_if(
            ! in_array($user->role, ['admin', 'supervisor', 'owner', 'branch_admin', 'super_admin', 'cashier'], true),
            403,
            'Sin permiso para anular ventas.'
        );

        abort_if($sale->business_id !== $user->business->id, 403);

        abort_if(
            ! in_array($sale->status, ['paid', 'pending'], true),
            422,
            'Solo se pueden anular ventas pagadas o pendientes de cobro.'
        );

        $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $branchId = in_array($user->role, ['branch_admin', 'cashier'], true)
            ? $user->branch_id
            : ($sale->branch_id ?? null);

        DB::transaction(function () use ($sale, $user, $request, $branchId): void {
            // Revertir stock si la venta descontó inventario: 'paid' (onsite paga en pay())
            // y 'pending' (crédito/delivery descuentan en store()).
            if (in_array($sale->status, ['paid', 'pending'], true)) {
                $sale->load('items.product');
                foreach ($sale->items as $item) {
                    if ($item->input_type !== 'weight') {
                        continue;
                    }

                    $stockProductId = $item->product?->stock_product_id ?? $item->product_id;

                    InventoryEntry::create([
                        'business_id' => $sale->business_id,
                        'branch_id'   => $branchId,
                        'product_id'  => $stockProductId,
                        'quantity_kg' => abs((float) $item->quantity_value),
                        'location'    => 'vitrina',
                        'waste_kg'    => 0,
                        'entered_at'  => now(),
                        'notes'       => 'Reversión anulación #' . $sale->ticket_number,
                        'created_by'  => $user->id,
                    ]);
                }
            }

            $sale->update([
                'status'              => 'cancelled',
                'cancelled_at'        => now(),
                'cancelled_by'        => $user->id,
                'cancellation_reason' => $request->input('reason'),
            ]);

            ActivityLog::create([
                'business_id' => $user->business->id,
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

        cache()->forget("stock_critico_count_{$user->business->id}");

        return response()->json(['ok' => true]);
    }

    public function assignClient(Request $request, Sale $sale): JsonResponse
    {
        $user = Auth::user();
        abort_unless($sale->business_id === $user->business->id, 403);
        abort_unless(
            in_array($sale->status, ['pending'], true),
            422,
            'Solo se puede asignar cliente a ventas pendientes.'
        );

        $data = $request->validate([
            'client_id'   => ['nullable', 'integer', 'exists:clients,id'],
            'client_name' => ['required', 'string', 'max:100'],
        ]);

        // Verificar que el cliente pertenece al negocio
        if (! empty($data['client_id'])) {
            $client = \App\Models\Client::find($data['client_id']);
            abort_unless($client && $client->business_id === $user->business->id, 403);
            $data['client_name'] = $client->name;
        }

        $sale->update([
            'client_id'   => $data['client_id'] ?? null,
            'client_name' => $data['client_name'],
        ]);

        return response()->json(['ok' => true, 'client_name' => $sale->fresh()->client_name]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateTicketNumber(int $businessId, string $prefix): string
    {
        return DB::transaction(function () use ($businessId, $prefix): string {
            $last = Sale::where('business_id', $businessId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('ticket_number');
            $n = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;
            return $prefix . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
        });
    }
}
