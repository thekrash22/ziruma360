<?php

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationType;
use App\Enums\LeaseStatus;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Autorización de gestión (spec §4.8).
 *
 * Quién puede actuar sobre un inmueble, distinto de ser titular o
 * residente.
 *
 * RN-P15: la vigencia se verifica al ejecutar cada operación, no solo al
 * crear la autorización. Por eso el estado no cambia cuando el periodo
 * vence: lo que concede permisos es la combinación de `status = active` con
 * el momento de la operación dentro de `valid_from`..`valid_until` (vista
 * `effective_property_unit_authorizations` en la base y ámbito `effective`
 * aquí).
 *
 * RN-P16: revocar impide nuevas operaciones pero conserva el historial, y
 * una revocación es irreversible (lo garantiza la base).
 */
class PropertyUnitAuthorization extends Model
{
    use HasFactory, BelongsToComplex;

    protected $fillable = [
        'uuid',
        'residential_complex_id',
        'property_unit_id',
        'person_id',
        'user_id',
        'lease_id',
        'authorization_type',
        'scope',
        'valid_from',
        'valid_until',
        'status',
        'granted_by',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'authorization_type' => AuthorizationType::class,
        'status' => AuthorizationStatus::class,
        'scope' => 'array',
        // El periodo de vigencia es un instante (`timestamp` en la base), no
        // un dia: castearlo como `date` lo trunca a la medianoche y concede
        // permisos antes del inicio real o los caduca antes del fin real,
        // ademas de discrepar de la vista `effective_property_unit_authorizations`.
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'id',
    ];

    protected static function booted(): void
    {
        static::creating(function (PropertyUnitAuthorization $authorization) {
            if (empty($authorization->uuid)) {
                $authorization->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Autorizaciones vigentes en el momento dado (RN-P15).
     *
     * Es el equivalente en la aplicación de la vista
     * `effective_property_unit_authorizations`: ninguna autorización futura
     * o vencida concede permisos, ni una cuyo arrendamiento dejó de estar
     * vigente.
     *
     * @param  Builder<PropertyUnitAuthorization>  $query
     */
    public function scopeEffective(Builder $query, ?Carbon $at = null): Builder
    {
        $moment = $at ?? Carbon::now();
        $day = $moment->toDateString();

        return $query->where('status', AuthorizationStatus::ACTIVE->value)
            ->where('valid_from', '<=', $moment)
            ->where(function (Builder $query) use ($moment) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $moment);
            })
            ->where(function (Builder $query) use ($day) {
                $query->whereNull('lease_id')
                    ->orWhereHas('lease', fn (Builder $lease) => $lease->where('status', LeaseStatus::ACTIVE->value)
                        ->whereDate('starts_on', '<=', $day)
                        ->where(fn (Builder $lease) => $lease->whereNull('ends_on')
                            ->orWhereDate('ends_on', '>=', $day)));
            });
    }

    /**
     * La autorización concede permisos en el momento dado (RN-P15).
     *
     * La decisión se toma sobre el estado actual de la base: una fila
     * revocada o un arrendamiento que dejó de estar vigente tienen que
     * impedir la operación aunque la instancia del modelo conserve atributos
     * antiguos.
     */
    public function isEffectiveAt(?Carbon $at = null): bool
    {
        $moment = $at ?? Carbon::now();

        if ($this->id === null) {
            return false;
        }

        $current = static::query()->whereKey($this->id)->first();

        if ($current === null) {
            return false;
        }

        if ($current->status !== AuthorizationStatus::ACTIVE) {
            return false;
        }

        if ($current->valid_from === null || $current->valid_from->greaterThan($moment)) {
            return false;
        }

        if ($current->valid_until !== null && $current->valid_until->lessThan($moment)) {
            return false;
        }

        return $current->hasEffectiveLeaseAt($moment);
    }

    /** Una autorización solo concede permisos con un arrendamiento vigente. */
    protected function hasEffectiveLeaseAt(Carbon $moment): bool
    {
        if ($this->lease_id === null) {
            return true;
        }

        $lease = $this->lease;

        if ($lease === null || $lease->status !== LeaseStatus::ACTIVE) {
            return false;
        }

        // `starts_on` y `ends_on` son fechas: el arrendamiento esta vigente
        // desde el primer dia y hasta el final del ultimo (mismo criterio que
        // la vista `effective_property_unit_authorizations`).
        $day = $moment->copy()->startOfDay();

        return ($lease->starts_on === null || ! $lease->starts_on->greaterThan($day))
            && ($lease->ends_on === null || ! $lease->ends_on->copy()->endOfDay()->lessThan($moment));
    }

    /**
     * Revoca la autorización (RN-P16).
     *
     * La fila se conserva con su historial; la base impide revertir una
     * revocación.
     */
    public function revoke(?User $revokedBy = null): void
    {
        $this->status = AuthorizationStatus::REVOKED;
        $this->revoked_by = $revokedBy?->id;
        $this->revoked_at = Carbon::now();
        $this->save();
    }

    /**
     * Relations
     */
    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Cuenta que puede ejercer la autorización (nula si la persona todavia
     * no activó su cuenta).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Arrendamiento del que depende la autorización (opcional).
     */
    public function lease(): BelongsTo
    {
        return $this->belongsTo(UnitLease::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
