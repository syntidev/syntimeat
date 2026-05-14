<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BovedaProduct extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'unit',
        'active',
        'sort_order',
        'requires_despiece',
        'vitrina_product_id',
    ];

    protected $casts = [
        'active'             => 'boolean',
        'sort_order'         => 'integer',
        'requires_despiece'  => 'boolean',
        'vitrina_product_id' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
