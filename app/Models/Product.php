<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'business_id',
        'category_id',
        'subcategory_id',
        'name',
        'sku',
        'barcode',
        'sale_mode',
        'base_unit_label',
        'fraction_allowed',
        'price_per_kg_usd',
        'price_per_unit_usd',
        'cost_per_kg_usd',
        'cost_per_unit_usd',
        'min_stock',
        'location',
        'image_path',
        'sort_order',
        'active',
        'fabricable',
    ];

    protected function casts(): array
    {
        return [
            'fraction_allowed'    => 'boolean',
            'fabricable'          => 'boolean',
            'price_per_kg_usd'    => 'decimal:2',
            'price_per_unit_usd'  => 'decimal:2',
            'cost_per_kg_usd'     => 'decimal:2',
            'cost_per_unit_usd'   => 'decimal:2',
            'min_stock'           => 'decimal:3',
            'sort_order'          => 'integer',
            'active'              => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(InventoryEntry::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
