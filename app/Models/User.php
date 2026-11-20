<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    /**
     * Get all memberships for this user.
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ResidentialComplexMembership::class);
    }

    /**
     * Get all active memberships for this user.
     */
    public function activeMemberships(): HasMany
    {
        return $this->memberships()->where('status', 'active');
    }

    /**
     * Get the complexes where the user is an active member.
     */
    public function complexes()
    {
        return $this->belongsToMany(
            ResidentialComplex::class,
            'residential_complex_memberships'
        )->where('residential_complex_memberships.status', 'active');
    }

    /**
     * Check if user is member of a specific complex.
     */
    public function isMemberOf(int $complexId): bool
    {
        return $this->memberships()
            ->where('residential_complex_id', $complexId)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Check if user is admin of a specific complex.
     */
    public function isComplexAdmin(int $complexId): bool
    {
        return $this->memberships()
            ->where('residential_complex_id', $complexId)
            ->where('status', 'active')
            ->whereHas('roles', function ($query) {
                $query->where('key', 'complex_admin')
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_from')
                        ->orWhere('membership_roles.valid_from', '<=', now()))
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_until')
                        ->orWhere('membership_roles.valid_until', '>=', now()));
            })
            ->exists();
    }

    /**
     * Check if user is platform admin.
     *
     * El rol de plataforma solo se reconoce con una membresia **activa** y una
     * asignacion de rol **vigente**: una membresia suspendida o revocada, y
     * una asignacion vencida o todavia no vigente, no otorgan poder de
     * plataforma.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->activeMemberships()
            ->whereHas('roles', function ($query) {
                $query->where('key', 'platform_admin')
                    ->whereNull('roles.residential_complex_id')
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_from')
                        ->orWhere('membership_roles.valid_from', '<=', now()))
                    ->where(fn (Builder $roles) => $roles
                        ->whereNull('membership_roles.valid_until')
                        ->orWhere('membership_roles.valid_until', '>=', now()));
            })
            ->exists();
    }
}
