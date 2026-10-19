<?php

namespace App\Models;

use App\Enums\ComplexPermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'residential_complex_id',
        'key',
        'name',
        'description',
        'is_system',
        'is_editable',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_editable' => 'boolean',
    ];

    /**
     * Check if this is a system role.
     */
    public function isSystem(): bool
    {
        return $this->is_system;
    }

    /**
     * Check if this role can be edited.
     */
    public function canBeEdited(): bool
    {
        return !$this->is_system && $this->is_editable;
    }

    /**
     * Check if this role belongs to a complex (vs system role).
     */
    public function isComplexRole(): bool
    {
        return $this->residential_complex_id !== null;
    }

    /**
     * Check if the role has a specific permission.
     */
    public function hasPermission(string|ComplexPermission $permission): bool
    {
        $key = $permission instanceof ComplexPermission ? $permission->value : $permission;
        return $this->permissions()->where('permission_key', $key)->exists();
    }

    /**
     * Grant a permission to the role.
     */
    public function grantPermission(string|ComplexPermission $permission): void
    {
        $key = $permission instanceof ComplexPermission ? $permission->value : $permission;
        $this->permissions()->firstOrCreate(['permission_key' => $key]);
    }

    /**
     * Revoke a permission from the role.
     */
    public function revokePermission(string|ComplexPermission $permission): void
    {
        $key = $permission instanceof ComplexPermission ? $permission->value : $permission;
        $this->permissions()->where('permission_key', $key)->delete();
    }

    /**
     * Relations
     */
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function membershipRoles(): HasMany
    {
        return $this->hasMany(MembershipRole::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            ResidentialComplexMembership::class,
            'membership_roles',
            'role_id'
        )
            ->withPivot('valid_from', 'valid_until')
            ->withTimestamps();
    }
}
