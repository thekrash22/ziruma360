<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Policies\MembershipPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Membresia de una cuenta en un conjunto (specs S73-S77).
 *
 * La policy vive en `App\Policies\MembershipPolicy` y se declara aqui de
 * forma explicita: el nombre de la clase no sigue la convencion de
 * descubrimiento automatico del framework.
 */
#[UsePolicy(MembershipPolicy::class)]
class ResidentialComplexMembership extends Model
{
    use HasFactory;

    protected $table = 'residential_complex_memberships';

    protected $fillable = [
        'residential_complex_id',
        'user_id',
        'status',
        'invited_by',
        'invited_at',
        'joined_at',
        'last_active_at',
    ];

    protected $casts = [
        'status' => MembershipStatus::class,
        'invited_at' => 'datetime',
        'joined_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    /**
     * Check if the membership is active.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * Check if the user can access the complex.
     */
    public function canAccess(): bool
    {
        return $this->status->canAccess();
    }

    /**
     * Activate the membership.
     */
    public function activate(): void
    {
        $this->update([
            'status' => MembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
    }

    /**
     * Suspend the membership.
     */
    public function suspend(): void
    {
        $this->update(['status' => MembershipStatus::SUSPENDED]);
    }

    /**
     * Revoke the membership.
     */
    public function revoke(): void
    {
        $this->update(['status' => MembershipStatus::REVOKED]);
    }

    /**
     * Update last active timestamp.
     */
    public function touchLastActive(): void
    {
        $this->update(['last_active_at' => now()]);
    }

    /**
     * Relations
     */
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Los roles asignados a la membresia, con su vigencia.
     *
     * El orden es el de la vigencia: primero las asignaciones con una
     * ventana explicita (de la mas antigua a la mas reciente) y despues las
     * perpetuas, que no caducan.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'membership_roles', 'membership_id')
            ->withPivot('valid_from', 'valid_until')
            ->withTimestamps()
            ->orderByRaw('membership_roles.valid_from asc nulls last');
    }

    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class, 'membership_id');
    }
}
