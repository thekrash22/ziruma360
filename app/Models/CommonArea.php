<?php

namespace App\Models;

use App\Enums\CommonAreaStatus;
use App\Enums\CommonAreaType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Area comun del conjunto (spec §3.8 / S53).
 *
 * Son zonas de uso compartido (piscina, salon social, BBQ, gimnasio, juegos,
 * canchas, terraza, parqueadero comunitario). Son distintas de las unidades
 * privadas y de los sistemas de asignacion de parqueaderos: un area comun no
 * es una `property_unit` ni genera titularidades.
 *
 * Las reglas de reserva quedan preparadas (`is_reservable`); el modulo de
 * reservas es futuro.
 *
 * @property int $id
 * @property int $residential_complex_id
 * @property int|null $building_id
 * @property string $name
 * @property string $code
 * @property CommonAreaType $area_type
 * @property string|null $description
 * @property string|null $location
 * @property int|null $capacity
 * @property string|null $area_m2
 * @property bool $is_reservable
 * @property CommonAreaStatus $status
 * @property array|null $features
 */
class CommonArea extends Model
{
    // The physical structure is audited (spec §1.6).
    use Auditable, BelongsToComplex;

    protected $table = 'common_areas';

    protected $fillable = [
        'residential_complex_id',
        'building_id',
        'name',
        'code',
        'area_type',
        'description',
        'location',
        'capacity',
        'area_m2',
        'is_reservable',
        'status',
        'features',
    ];

    protected $casts = [
        'area_type' => CommonAreaType::class,
        'status' => CommonAreaStatus::class,
        'is_reservable' => 'boolean',
        'capacity' => 'integer',
        'area_m2' => 'decimal:2',
        'features' => 'array',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Edificio donde esta el area comun (nullable: puede estar en un area
     * exterior del conjunto).
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * Asignaciones de uso exclusivo de este bien comun (spec §3.9).
     */
    public function exclusiveUseAssignments(): HasMany
    {
        return $this->hasMany(CommonPropertyExclusiveUseAssignment::class, 'common_area_id');
    }
}
