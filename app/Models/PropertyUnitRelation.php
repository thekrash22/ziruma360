<?php

namespace App\Models;

use App\Enums\PropertyUnitRelationStatus;
use App\Enums\PropertyUnitRelationType;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Relacion entre dos unidades (spec §3.7 / S50).
 *
 * Registra la vinculacion entre unidades (un apartamento y su parqueadero o
 * deposito, por ejemplo). No implica titularidad ni concede permisos: uso,
 * titularidad y autorizacion son conceptos separados (RN-S05).
 *
 * @property int $id
 * @property int $residential_complex_id
 * @property int $source_property_unit_id
 * @property int $target_property_unit_id
 * @property PropertyUnitRelationType $relation_type
 * @property Carbon|null $valid_from
 * @property Carbon|null $valid_until
 * @property PropertyUnitRelationStatus $status
 */
class PropertyUnitRelation extends Model
{
    use BelongsToComplex;

    protected $table = 'property_unit_relations';

    protected $fillable = [
        'residential_complex_id',
        'source_property_unit_id',
        'target_property_unit_id',
        'relation_type',
        'valid_from',
        'valid_until',
        'status',
        'notes',
    ];

    protected $casts = [
        'relation_type' => PropertyUnitRelationType::class,
        'status' => PropertyUnitRelationStatus::class,
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Unidad origen de la relacion (por ejemplo, el apartamento).
     */
    public function sourcePropertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'source_property_unit_id');
    }

    /**
     * Unidad destino de la relacion (por ejemplo, el parqueadero o deposito).
     */
    public function targetPropertyUnit(): BelongsTo
    {
        return $this->belongsTo(PropertyUnit::class, 'target_property_unit_id');
    }
}
