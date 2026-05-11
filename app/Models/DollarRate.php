<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DollarRate extends Model
{
    // Sin updated_at — igual que synticorex
    public const UPDATED_AT = null;

    protected $fillable = [
        'rate',
        'source',
        'currency_type',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate'             => 'decimal:4',
            'effective_from'   => 'datetime',
            'effective_until'  => 'datetime',
            'is_active'        => 'boolean',
            'created_at'       => 'datetime',
        ];
    }

    public function scopeUsd($query)
    {
        return $query->where('currency_type', 'USD');
    }

    public function scopeEur($query)
    {
        return $query->where('currency_type', 'EUR');
    }
}
