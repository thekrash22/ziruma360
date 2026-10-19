<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class MembershipRole extends Model
{
    use HasFactory;

    protected $table = 'membership_roles';

    protected $fillable = [
        'membership_id',
        'role_id',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
    ];

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
