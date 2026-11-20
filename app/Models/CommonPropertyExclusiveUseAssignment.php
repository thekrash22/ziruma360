<?php

namespace App\Models;

use App\Enums\ExclusiveUseAssignmentStatus;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Asignacion en uso exclusivo de un bien comun a una unidad (spec §3.9 / S54).
 *
 * RN-S06: un bien comun asignado en uso exclusivo no se registra como
 * propiedad privada. La asignacion se separa de la titularidad legal: esta
 * tabla no crea ni modifica `property_unit_ownerships`.
 *
 * @property int $id
 * @property int $residential_complex_id
 * @property int $common_area_id
 * @property int $property_unit_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_until
 * @property string|null $document_reference
 * @property ExclusiveUseAssignmentStatus $status
 */
class CommonPropertyExclusiveUseAssignment extends Model
{
    use BelongsToComplex;

    protected $table = 'common_property_exclusive_use_assignments';

    protected $fillable = [
        'residential_complex_id',
        'common_area_id',
        'property_unit_id',
        'valid_from',
        'valid_until',
        'document_reference',
        'status',
    ];

    protected $casts = [
        'status' => ExclusiveUseAssignmentStatus::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Bien comun asignado en uso exclusivo.
     */
    public function commonArea(): BelongsTo
    {
        return $this->belongsTo(CommonArea::class, 'common_area_id');
    }

    /**
     * Unidad beneficiaria de la asignacion.
     */
    public function propertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'property_unit_id');
    }
}
