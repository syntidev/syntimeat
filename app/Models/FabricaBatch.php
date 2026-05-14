<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FabricaBatch extends Model
{
    protected $fillable = [
        'business_id',
        'created_by',
        'output_product_id',
        'output_kg',
        'output_units',
        'input_cost_usd',
        'notes',
        'produced_at',
    ];

    protected function casts(): array
    {
        return [
            'output_kg'       => 'decimal:3',
            'output_units'    => 'decimal:3',
            'input_cost_usd'  => 'decimal:2',
            'produced_at'     => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outputProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'output_product_id');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(FabricaInput::class);
    }
}
