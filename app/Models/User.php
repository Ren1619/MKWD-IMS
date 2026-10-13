<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property UserRole $role
 * @property bool $is_active
 * @property bool $local_fallback_enabled
 * @property Carbon|null $last_login_at
 * @property Carbon|null $last_hris_authenticated_at
 * @property Carbon|null $fallback_valid_until
 * @property Carbon|null $last_fallback_authenticated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['hris_reference_id', 'name', 'email', 'password', 'role', 'is_active', 'email_verified_at', 'last_login_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $attributes = [
        'local_fallback_enabled' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'local_fallback_enabled' => 'boolean',
            'last_login_at' => 'datetime',
            'last_hris_authenticated_at' => 'datetime',
            'fallback_valid_until' => 'datetime',
            'last_fallback_authenticated_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function canManageInventory(): bool
    {
        return $this->role->canManageInventory();
    }

    public function canViewPropertyGovernance(): bool
    {
        if ($this->canManageInventory()) {
            return true;
        }

        $positionName = data_get($this->employee?->metadata, 'position.name');
        if (! is_string($positionName)) {
            return false;
        }

        foreach (config('inventory.property_access.oversight_positions', []) as $position) {
            if (is_string($position) && strcasecmp($positionName, $position) === 0) {
                return true;
            }
        }

        return false;
    }

    public function canViewAllPropertyAssets(): bool
    {
        return $this->canViewPropertyGovernance();
    }

    /** @return BelongsTo<HrisReference, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrisReference::class, 'hris_reference_id');
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
