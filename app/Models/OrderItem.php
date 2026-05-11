<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'input_type',
        'quantity_value',
        'unit_label',
        'price_per_kg_usd',
        'price_per_unit_usd',
        'subtotal_usd',
    ];

    protected function casts(): array
    {
        return [
            'quantity_value'     => 'decimal:3',
            'price_per_kg_usd'   => 'decimal:2',
            'price_per_unit_usd' => 'decimal:2',
            'subtotal_usd'       => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
