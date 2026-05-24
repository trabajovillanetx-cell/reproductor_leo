<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Support\SubscriptionTime;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name', 'username',
    'email',
    'password',
    'provider_password',
    'role',
    'parent_id',
    'status',
    'expires_at',
    'is_demo',
])]
#[Hidden(['password', 'provider_password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'provider_password' => 'encrypted',
            'expires_at' => 'datetime',
            'is_demo' => 'boolean',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function resellerCredits(): HasOne
    {
        return $this->hasOne(ResellerCredit::class, 'reseller_id');
    }

    public function playbackTokens(): HasMany
    {
        return $this->hasMany(PlaybackToken::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    public function streamingProfiles(): HasMany
    {
        return $this->hasMany(CustomerProfile::class)->orderBy('sort_order');
    }

    /**
     * Usuarios finales (clientes) cuyo padre inmediato es este usuario.
     * No incluye clientes de sub-revendedores ni de ningún otro nivel de la red.
     */
    public function scopeWhereDirectCustomerOf(Builder $query, int $providerUserId): Builder
    {
        return $query
            ->where('role', UserRole::Customer)
            ->where('parent_id', $providerUserId);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isReseller(): bool
    {
        return $this->role === UserRole::Reseller;
    }

    public function isVendor(): bool
    {
        return $this->role === UserRole::Vendor;
    }

    /** Cuenta con bolsa de créditos (revendedor o vendedor). */
    public function holdsCredits(): bool
    {
        return $this->isReseller() || $this->isVendor();
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function hasActiveSubscription(): bool
    {
        if (! $this->isCustomer()) {
            return true;
        }

        if ($this->status === UserStatus::Suspended) {
            return false;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return SubscriptionTime::isDateWindowOpen($this->expires_at);
    }

    public function syncExpiredState(): void
    {
        if (! $this->isCustomer()) {
            return;
        }

        if ($this->expires_at !== null && SubscriptionTime::isExpiredByInstant($this->expires_at)) {
            $this->status = UserStatus::Expired;
            $this->save();
        }
    }
}
