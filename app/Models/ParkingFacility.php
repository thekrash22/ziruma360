<?php

namespace App\Models;

use App\Enums\ParkingFacilityStatus;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instalacion de parqueadero comunitario (spec 3.10 / S55).
 *
 * Registra la instalacion: nombre, tipo, capacidad total, capacidad
 * operativa, modo de asignacion y estado. El tipo y el modo de asignacion son
 * cadenas libres: el spec no enumera sus valores.
 *
 * La logica de reservas, disponibilidad por intervalo y concurrencia
 * pertenece al modulo de rentas cortas y no se implementa aqui.
 *
 * @property int $id
 * @property int $residential_complex_id
 * @property string $name
 * @property string $facility_type
 * @property int|null $total_capacity
 * @property int|null $operational_capacity
 * @property string $assignment_mode
 * @property ParkingFacilityStatus $status
 * @property string|null $notes
 */
class ParkingFacility extends Model
{
    use BelongsToComplex;

    protected $table = 'parking_facilities';

    protected $fillable = [
        'residential_complex_id',
        'name',
        'facility_type',
        'total_capacity',
        'operational_capacity',
        'assignment_mode',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => ParkingFacilityStatus::class,
        'total_capacity' => 'integer',
        'operational_capacity' => 'integer',
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Espacios numerados de esta instalacion, si aplica.
     */
    public function spaces(): HasMany
    {
        return $this->hasMany(ParkingSpace::class, 'parking_facility_id');
    }
}
