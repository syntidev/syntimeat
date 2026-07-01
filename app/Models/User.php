<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'business_id', 'branch_id', 'role', 'permissions', 'theme', 'is_active', 'session_token', 'access_start', 'access_end', 'access_days'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    const ROLES = ['super_admin', 'owner', 'branch_admin', 'analyst', 'supervisor', 'cashier', 'admin'];

    // ── Helpers de rol ──────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool  { return $this->role === 'super_admin'; }
    public function isOwner(): bool       { return $this->role === 'owner'; }
    public function isBranchAdmin(): bool { return $this->role === 'branch_admin'; }
    public function isAnalyst(): bool     { return $this->role === 'analyst'; }
    public function isAdmin(): bool       { return $this->role === 'admin'; }
    public function isCashier(): bool     { return $this->role === 'cashier'; }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles, true);
    }

    public function canManageBusiness(): bool
    {
        return $this->hasRole(['super_admin', 'owner', 'admin']);
    }

    public function canManageUsers(): bool
    {
        return $this->hasRole(['super_admin', 'owner', 'branch_admin', 'admin']);
    }

    public function canVoidSales(): bool
    {
        return $this->hasRole(['super_admin', 'owner', 'branch_admin', 'admin']);
    }

    // Ver datos de todas las sucursales o solo la asignada
    public function seesTodasLasSucursales(): bool
    {
        return $this->hasRole(['super_admin', 'owner', 'analyst', 'admin']) || $this->branch_id === null;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'access_days'       => 'array',
            'permissions'       => 'json',
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

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'cashier_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'created_by');
    }
}
