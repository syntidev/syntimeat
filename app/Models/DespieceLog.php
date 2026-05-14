<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DespieceLog extends Model
{
    protected $fillable = [
        'business_id',
        'user_id',
        'product_id',
        'location_from',
        'quantity_kg_from',
        'notes',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_kg_from' => 'decimal:3',
            'processed_at'     => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DespieceItem::class);
    }
}
