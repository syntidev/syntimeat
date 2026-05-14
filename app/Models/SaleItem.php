<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name',
        'input_type',
        'quantity_value',
        'unit_label',
        'price_per_kg_usd',
        'price_per_unit_usd',
        'subtotal_usd',
        'subtotal_bs',
        'rate_used',
        'discount_usd',
    ];

    protected function casts(): array
    {
        return [
            'quantity_value'     => 'decimal:3',
            'price_per_kg_usd'   => 'decimal:2',
            'price_per_unit_usd' => 'decimal:2',
            'subtotal_usd'       => 'decimal:2',
            'subtotal_bs'        => 'decimal:2',
            'rate_used'          => 'decimal:4',
            'discount_usd'       => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
