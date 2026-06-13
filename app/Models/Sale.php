<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Order;

class Sale extends Model
{
    // Items siempre se necesitan junto con la venta
    protected $with = ['items'];

    protected $fillable = [
        'business_id',
        'ticket_number',
        'status',
        'total_usd',
        'payment_method',
        'amount_received_usd',
        'change_usd',
        'rate_used',
        'total_bs',
        'notes',
        'sold_at',
        'accounting_date',
        'cashier_id',
        'cash_register_id',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'client_name',
        'client_phone',
        'client_id',
        'origin',
        'channel',
        'delivery_status',
        'delivery_confirmed_at',
        'payment_status',
        'order_id',
        'branch_id',
        'amount_paid_bs',
        'amount_paid_usd',
        'inventory_released',
    ];

    protected function casts(): array
    {
        return [
            'total_usd'          => 'decimal:2',
            'amount_received_usd' => 'decimal:2',
            'change_usd'         => 'decimal:2',
            'rate_used'          => 'decimal:4',
            'total_bs'           => 'decimal:2',
            'sold_at'                  => 'datetime',
            'cancelled_at'             => 'datetime',
            'delivery_confirmed_at'    => 'datetime',
            'amount_paid_bs'          => 'decimal:2',
            'amount_paid_usd'         => 'decimal:4',
            'inventory_released'      => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function salePayments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(SaleAbono::class);
    }

    public function isPendingCollection(): bool
    {
        return $this->payment_status === 'pendiente_cobro';
    }
}
