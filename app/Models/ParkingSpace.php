<?php

namespace App\Models;

use App\Enums\ParkingSpaceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToComplex;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Espacio numerado de una instalacion de parqueadero (spec 3.10 / S55).
 *
 * El numero del espacio es unico dentro de su instalacion. La FK compuesta
 * hacia `parking_facilities` impide que un espacio pertenezca a una
 * instalacion de otro conjunto (ACE-C02/ACE-P07).
 *
 * La logica de reservas, disponibilidad por intervalo y concurrencia
 * pertenece al modulo de rentas cortas y no se implementa aqui.
 *
 * @property int $id
 * @property int $residential_complex_id
 * @property int $parking_facility_id
 * @property string $number
 * @property ParkingSpaceStatus $status
 * @property string|null $notes
 */
class ParkingSpace extends Model
{
    // The physical structure is audited (spec §1.6).
    use Auditable, BelongsToComplex;

    protected $table = 'parking_spaces';

    protected $fillable = [
        'residential_complex_id',
        'parking_facility_id',
        'number',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => ParkingSpaceStatus::class,
    ];

    protected $hidden = [
        'id',
    ];

    /**
     * Instalacion a la que pertenece el espacio.
     */
    public function parkingFacility(): BelongsTo
    {
        return $this->belongsTo(ParkingFacility::class, 'parking_facility_id');
    }
}
