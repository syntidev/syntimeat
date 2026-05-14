<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricaInput extends Model
{
    protected $fillable = [
        'fabrica_batch_id',
        'product_id',
        'despiece_item_id',
        'inventory_entry_id',
        'label',
        'quantity_kg',
        'cost_usd',
    ];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:3',
            'cost_usd'    => 'decimal:2',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(FabricaBatch::class, 'fabrica_batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function despieceItem(): BelongsTo
    {
        return $this->belongsTo(DespieceItem::class);
    }

    public function inventoryEntry(): BelongsTo
    {
        return $this->belongsTo(InventoryEntry::class);
    }
}
