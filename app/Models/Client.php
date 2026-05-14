<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'business_id',
        'client_code',
        'name',
        'phone',
        'email',
        'address',
        'notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $client): void {
            if (! empty($client->client_code)) {
                return;
            }

            $last = static::where('business_id', $client->business_id)
                ->orderByDesc('id')
                ->value('client_code');

            $next = 1;
            if ($last && preg_match('/(\d+)$/', $last, $m)) {
                $next = (int) $m[1] + 1;
            }

            $client->client_code = 'CLI-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
