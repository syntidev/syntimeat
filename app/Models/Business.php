<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PaymentMethod;
use App\Models\PaymentTerminal;

class Business extends Model
{
    protected $fillable = [
        'name',
        'legal_name',
        'rif',
        'logo_path',
        'address',
        'city',
        'state',
        'phone',
        'currency_default',
        'rate_source',
        'rate_margin',
        'weight_unit',
        'ticket_prefix',
        'ticket_footer',
        'sale_capture_mode',
        'line_input_mode',
        'preticket_enabled',
        'preticket_expiry_minutes',
        'price_lock_policy',
        'onboarding_completed',
        'active',
        'max_branches',
        'settings',
        'theme_color',
    ];

    protected function casts(): array
    {
        return [
            'rate_margin'              => 'decimal:2',
            'preticket_enabled'        => 'boolean',
            'preticket_expiry_minutes' => 'integer',
            'onboarding_completed'     => 'boolean',
            'active'                   => 'boolean',
            'max_branches'             => 'integer',
            'settings'                 => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(InventoryEntry::class);
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function paymentTerminals(): HasMany
    {
        return $this->hasMany(PaymentTerminal::class);
    }
}
