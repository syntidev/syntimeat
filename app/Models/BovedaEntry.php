<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BovedaEntry extends Model
{
    protected $fillable = [
        'business_id',
        'branch_id',
        'product_type',
        'description',
        'kg_entrada',
        'costo_usd',
        'waste_kg',
        'kg_surtido_vitrina',
        'supplier',
        'entered_at',
        'closed_at',
        'despiece_completado_at',
        'pair_id',
    ];

    protected function casts(): array
    {
        return [
            'kg_entrada'             => 'decimal:3',
            'costo_usd'              => 'decimal:2',
            'waste_kg'               => 'decimal:3',
            'kg_surtido_vitrina'     => 'decimal:3',
            'kg_disponible'          => 'decimal:3',
            'entered_at'             => 'datetime',
            'closed_at'              => 'datetime',
            'despiece_completado_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bovedaProduct(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BovedaProduct::class, 'name', 'product_type');
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(InventoryEntry::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('closed_at');
    }
}
