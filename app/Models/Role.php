<?php

namespace App\Models;

use App\Enums\ComplexPermission;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    // The roles of the complex are audited (spec §1.6). The catalog roles
    // (residential_complex_id null) are platform objects: they are skipped.
    use Auditable, HasFactory;

    protected $fillable = [
        'residential_complex_id',
        'key',
        'name',
        'description',
        'is_system',
        'is_editable',
        'is_active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_editable' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A deleted role takes its permissions and its assignments with it
        // through the cascade of the database, which does not fire the model
        // events: their trail would be lost. They are removed through the
        // model, so every one of them leaves its `deleted` trail (spec §1.6).
        static::deleting(function (Role $role): void {
            $role->permissions()->get()->each(
                fn (RolePermission $permission) => $permission->delete()
            );

            $role->membershipRoles()->get()->each(
                fn (MembershipRole $assignment) => $assignment->delete()
            );
        });
    }

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
        return ! $this->is_system && $this->is_editable;
    }

    /**
     * Check if this role belongs to a complex (vs system role).
     */
    public function isComplexRole(): bool
    {
        return $this->residential_complex_id !== null;
    }

    /**
     * Check if this role is in force.
     *
     * Un rol desactivado conserva su traza y sus asignaciones, pero deja de
     * estar en vigor: no concede permisos (spec S88).
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
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
     *
     * The permission is deleted through the model so the revocation leaves an
     * audit trail (spec §1.6).
     */
    public function revokePermission(string|ComplexPermission $permission): void
    {
        $key = $permission instanceof ComplexPermission ? $permission->value : $permission;

        $model = $this->permissions()->where('permission_key', $key)->first();

        $model?->delete();
    }

    /**
     * A role of the catalog belongs to no complex: it is a platform object.
     *
     * The roles of the complex are audited; the catalog ones are skipped
     * without failing the write, because a trail without a complex is
     * impossible (RN-C04).
     */
    public function auditIsPlatformObject(): bool
    {
        return $this->residential_complex_id === null;
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
            ->using(MembershipRole::class)
            ->withPivot('valid_from', 'valid_until')
            ->withTimestamps();
    }
}
