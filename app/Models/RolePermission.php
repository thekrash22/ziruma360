<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Permission granted to a role (specs S5, S97 and S98).
 *
 * The permission belongs to the complex of the role: that is the complex that
 * goes into the audit trail, and the model always completes the
 * `residential_complex_id` column when the row is created (spec S98).
 *
 * The permission of a catalog role (a role with no complex) is a platform
 * object: it is skipped by the audit without failing the write.
 */
class RolePermission extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'residential_complex_id',
        'role_id',
        'permission_key',
    ];

    protected static function booted(): void
    {
        static::creating(function (RolePermission $permission) {
            $permission->residential_complex_id = $permission->resolveComplexId();

            if ($permission->residential_complex_id === null && ! $permission->roleIsCatalog()) {
                throw new LogicException(
                    'A role permission cannot be written without a resolvable complex: its audit trail would be lost silently.'
                );
            }
        });
    }

    /**
     * The complex of the role the permission belongs to.
     *
     * The role is read straight from the table: the permission can be
     * written outside the scope of the complex of its role.
     */
    public function resolveComplexId(): ?int
    {
        if ($this->role_id === null) {
            return null;
        }

        $complexId = DB::table('roles')
            ->where('id', $this->role_id)
            ->value('residential_complex_id');

        return $complexId === null ? null : (int) $complexId;
    }

    /**
     * Whether the role is a catalog role: a role of the platform that belongs
     * to no complex.
     */
    public function roleIsCatalog(): bool
    {
        if ($this->role_id === null) {
            return false;
        }

        $role = DB::table('roles')->where('id', $this->role_id)->first(['residential_complex_id']);

        if ($role === null) {
            return false;
        }

        return $role->residential_complex_id === null;
    }

    /**
     * A permission of a catalog role is a platform object.
     *
     * The permission of a role that belongs to a complex but cannot resolve
     * it is not a platform object: it fails visibly, because the trail would
     * be lost silently.
     */
    public function auditIsPlatformObject(): bool
    {
        if ($this->residential_complex_id !== null) {
            return false;
        }

        return $this->roleIsCatalog();
    }

    /**
     * Relations
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
