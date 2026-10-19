<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
                $query->where('key', 'complex_admin');
            })
            ->exists();
    }

    /**
     * Check if user is platform admin.
     */
    public function isPlatformAdmin(): bool
    {
        return $this->memberships()
            ->whereHas('roles', function ($query) {
                $query->where('key', 'platform_admin');
            })
            ->exists();
    }
}
