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
        'branch_id',
        'category_id',
        'subcategory_id',
        'name',
        'sku',
        'barcode',
        'sale_mode',
        'base_unit_label',
        'fraction_allowed',
        'price_per_kg_usd',
        'price_per_kg_bs',
        'price_per_unit_usd',
        'price_per_unit_bs',
        'cost_per_kg_usd',
        'min_stock',
        'location',
        'image_path',
        'sort_order',
        'active',
        'fabricable',
        'is_favorite',
        'stock_product_id',
    ];

    protected function casts(): array
    {
        return [
            'fraction_allowed'    => 'boolean',
            'fabricable'          => 'boolean',
            'price_per_kg_usd'    => 'decimal:2',
            'price_per_kg_bs'     => 'decimal:2',
            'price_per_unit_usd'  => 'decimal:2',
            'price_per_unit_bs'   => 'decimal:2',
            'cost_per_kg_usd'     => 'decimal:4',
            'min_stock'           => 'decimal:3',
            'sort_order'          => 'integer',
            'active'              => 'boolean',
            'is_favorite'         => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function stockPool(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'stock_product_id');
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
