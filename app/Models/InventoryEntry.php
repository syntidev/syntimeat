<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryEntry extends Model
{
    protected $fillable = [
        'business_id',
        'product_id',
        'boveda_entry_id',
        'quantity_kg',
        'waste_kg',
        'cost_per_kg_usd',
        'supplier',
        'notes',
        'location',
        'entered_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_kg'     => 'decimal:3',
            'waste_kg'        => 'decimal:3',
            'net_kg'          => 'decimal:3',
            'cost_per_kg_usd' => 'decimal:2',
            'entered_at'      => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bovedaEntry(): BelongsTo
    {
        return $this->belongsTo(BovedaEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
