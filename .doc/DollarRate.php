<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DollarRate extends Model
{
    /**
     * Indicates if the model should be timestamped.
     * Only uses created_at, not updated_at.
     *
     * @var bool
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'rate',
        'source',
        'currency_type',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    /**
     * Scope: only USD rates.
     */
    public function scopeUsd($query)
    {
        return $query->where('currency_type', 'USD');
    }

    /**
     * Scope: only EUR rates.
     */
    public function scopeEur($query)
    {
        return $query->where('currency_type', 'EUR');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
