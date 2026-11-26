<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Assignment of a role to a membership (specs S6, S97 and S98).
 *
 * The assignment belongs to the complex of the membership: that is the
 * complex that goes into the audit trail, and the model always completes the
 * `residential_complex_id` column when the row is created (spec S98).
 *
 * The model is also the pivot of the `roles` relation of a membership, so the
 * assignments written through `attach()`/`sync()` leave the same trail as the
 * ones written through the model.
 */
class MembershipRole extends Model
{
    use AsPivot, Auditable, HasFactory;

    protected $table = 'membership_roles';

    /**
     * The pivot of a many-to-many relation does not auto-increment by default
     * (`Pivot::$incrementing = false`): the id of a row written through
     * `attach()` or `sync()` would never reach the model and the audit trail
     * would record an empty entity. The table has its own id, so the model
     * keeps it and the trail identifies the assignment.
     *
     * @var bool
     */
    public $incrementing = true;

    protected $fillable = [
        'residential_complex_id',
        'membership_id',
        'role_id',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MembershipRole $assignment) {
            $assignment->residential_complex_id = $assignment->resolveComplexId();

            if ($assignment->residential_complex_id === null) {
                throw new LogicException(
                    'A role assignment cannot be written without a resolvable complex: its audit trail would be lost silently.'
                );
            }
        });
    }

    /**
     * The complex of the membership the role is assigned to.
     *
     * The membership is read straight from the table: the assignment can be
     * written outside the scope of the complex of its membership.
     */
    public function resolveComplexId(): ?int
    {
        if ($this->membership_id === null) {
            return null;
        }

        $complexId = DB::table('residential_complex_memberships')
            ->where('id', $this->membership_id)
            ->value('residential_complex_id');

        return $complexId === null ? null : (int) $complexId;
    }

    /**
     * Check if the role assignment is currently valid.
     */
    public function isValid(?Carbon $at = null): bool
    {
        $at ??= now();

        $validFrom = $this->valid_from;
        $validUntil = $this->valid_until;

        if ($validFrom && $at->lt($validFrom)) {
            return false;
        }

        if ($validUntil && $at->gt($validUntil)) {
            return false;
        }

        return true;
    }

    /**
     * Check if the role assignment has expired.
     */
    public function isExpired(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->valid_until && $at->gt($this->valid_until);
    }

    /**
     * Check if the role assignment is pending (not yet started).
     */
    public function isPending(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->valid_from && $at->lt($this->valid_from);
    }

    /**
     * Relations
     */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplexMembership::class, 'membership_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
