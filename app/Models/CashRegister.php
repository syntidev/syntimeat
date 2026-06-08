<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Branch;

class CashRegister extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'cash_point_id',
        'name',
        'opened_at',
        'closed_at',
        'opening_amount_usd',
        'opening_amount_bs',
        'expected_cash_usd',
        'counted_cash_usd',
        'difference_usd',
        'rate_at_opening',
        'notes',
        'opened_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'          => 'datetime',
            'closed_at'          => 'datetime',
            'opening_amount_usd' => 'decimal:2',
            'opening_amount_bs'  => 'decimal:2',
            'expected_cash_usd'  => 'decimal:2',
            'counted_cash_usd'   => 'decimal:2',
            'difference_usd'     => 'decimal:2',
            'rate_at_opening'    => 'decimal:4',
        ];
    }

    /**
     * Caja (sesión) activa para el contexto actual — fuente única de verdad.
     * Prioriza la caja fijada en sesión (validada por branch + abierta).
     * Fallback: si hay exactamente UNA caja abierta en el branch, usar esa
     * (mantiene intacto el flujo de una sola caja). 0 o >1 sin selección → null.
     */
    public static function resolveActive(int $businessId, ?int $branchId, ?int $sessionId): ?self
    {
        $base = static::where('business_id', $businessId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereNull('closed_at');

        if ($sessionId) {
            $selected = (clone $base)->where('id', $sessionId)->first();
            if ($selected) {
                return $selected;
            }
        }

        $open = $base->orderBy('opened_at')->get();

        return $open->count() === 1 ? $open->first() : null;
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashPoint(): BelongsTo
    {
        return $this->belongsTo(CashPoint::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
