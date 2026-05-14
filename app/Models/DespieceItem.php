<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespieceItem extends Model
{
    protected $fillable = [
        'despiece_log_id',
        'product_id',
        'quantity_kg',
        'tipo',
    ];

    protected function casts(): array
    {
        return [
            'quantity_kg' => 'decimal:3',
        ];
    }

    public function despieceLog(): BelongsTo
    {
        return $this->belongsTo(DespieceLog::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
