<?php

namespace Tests\Feature;

use App\Enums\ParkingFacilityStatus;
use App\Enums\ParkingSpaceStatus;
use App\Models\ParkingFacility;
use App\Models\ParkingSpace;
use App\Models\ResidentialComplex;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ValueError;

/**
 * T11 / S55: `parking_facilities` y `parking_spaces` (spec 3.10).
 *
 * El core registra la instalacion de parqueadero comunitario (nombre, tipo,
 * capacidad total, capacidad operativa, modo de asignacion, estado) y, si
 * aplica, sus espacios numerados. La logica de reservas, disponibilidad por
 * intervalo y concurrencia pertenece al modulo de rentas cortas y no se
 * implementa aqui.
 *
 * Cubre el alta de la instalacion y del espacio numerado, las garantias a
 * nivel de base de datos (FK compuesta que rechaza cruces entre conjuntos,
 * unicidad del numero de espacio por instalacion, CHECK de capacidades) y el
 * almacenamiento literal de `facility_type` y `assignment_mode`, que el spec
 * no enumera.
 */
class ParkingFacilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();

        ComplexContext::clear();

        $this->complex = ResidentialComplex::factory()->create();
    }

    protected function tearDown(): void
    {
        ComplexContext::clear();

        parent::tearDown();
    }

    private function facility(
        ResidentialComplex $complex,
        string $name = 'Parqueadero Sotano 1',
        string $facilityType = 'underground',
        string $assignmentMode = 'by_assignment'
    ): ParkingFacility {
        return ParkingFacility::create([
            'residential_complex_id' => $complex->id,
            'name' => $name,
            'facility_type' => $facilityType,
            'total_capacity' => 120,
            'operational_capacity' => 100,
            'assignment_mode' => $assignmentMode,
            'status' => ParkingFacilityStatus::ACTIVE,
        ]);
    }

    // ==================== Instalaciones (S55) ====================

    public function test_can_create_a_parking_facility(): void
    {
        $facility = ParkingFacility::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero Sotano 1',
            'facility_type' => 'underground',
            'total_capacity' => 120,
            'operational_capacity' => 100,
            'assignment_mode' => 'by_assignment',
            'status' => ParkingFacilityStatus::ACTIVE,
            'notes' => 'Acceso por rampa desde la calle 30.',
        ]);

        $this->assertDatabaseHas('parking_facilities', [
            'id' => $facility->id,
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero Sotano 1',
            'facility_type' => 'underground',
            'total_capacity' => 120,
            'operational_capacity' => 100,
            'assignment_mode' => 'by_assignment',
            'status' => 'active',
        ]);

        $this->assertSame(ParkingFacilityStatus::ACTIVE, $facility->status);
        $this->assertSame($this->complex->id, $facility->residentialComplex->id);
        $this->assertSame('Activa', $facility->status->label());
    }

    public function test_the_facility_type_and_the_assignment_mode_are_stored_verbatim(): void
    {
        // El spec 3.10 no enumera los tipos ni los modos de asignacion: son
        // cadenas libres y se guardan tal cual.
        $facility = $this->facility(
            $this->complex,
            'Parqueadero Visitantes',
            'surface-rotating-double',
            'free-first-come-first-served'
        );

        $this->assertDatabaseHas('parking_facilities', [
            'id' => $facility->id,
            'facility_type' => 'surface-rotating-double',
            'assignment_mode' => 'free-first-come-first-served',
        ]);

        $this->assertSame('surface-rotating-double', $facility->fresh()->facility_type);
        $this->assertSame('free-first-come-first-served', $facility->fresh()->assignment_mode);
    }

    public function test_the_database_rejects_an_operational_capacity_greater_than_the_total_capacity(): void
    {
        $this->expectException(QueryException::class);

        DB::table('parking_facilities')->insert([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero Sotano 2',
            'facility_type' => 'underground',
            'total_capacity' => 50,
            'operational_capacity' => 60,
            'assignment_mode' => 'by_assignment',
            'status' => 'active',
        ]);
    }

    public function test_the_database_rejects_a_negative_capacity(): void
    {
        $this->expectException(QueryException::class);

        DB::table('parking_facilities')->insert([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero Sotano 3',
            'facility_type' => 'underground',
            'total_capacity' => -1,
            'operational_capacity' => null,
            'assignment_mode' => 'by_assignment',
            'status' => 'active',
        ]);
    }

    public function test_the_database_rejects_a_negative_operational_capacity(): void
    {
        $this->expectException(QueryException::class);

        DB::table('parking_facilities')->insert([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero Sotano 4',
            'facility_type' => 'underground',
            'total_capacity' => 50,
            'operational_capacity' => -5,
            'assignment_mode' => 'by_assignment',
            'status' => 'active',
        ]);
    }

    public function test_the_database_rejects_a_status_outside_the_allowed_domain(): void
    {
        $this->expectException(QueryException::class);

        DB::table('parking_facilities')->insert([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero en obras',
            'facility_type' => 'underground',
            'total_capacity' => 50,
            'operational_capacity' => 50,
            'assignment_mode' => 'by_assignment',
            'status' => 'maintenance',
        ]);
    }

    public function test_the_model_rejects_a_status_outside_the_allowed_domain(): void
    {
        $this->expectException(ValueError::class);

        ParkingFacility::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Parqueadero en obras',
            'facility_type' => 'underground',
            'total_capacity' => 50,
            'operational_capacity' => 50,
            'assignment_mode' => 'by_assignment',
            'status' => 'maintenance',
        ]);
    }
    // ==================== Espacios numerados (S55) ====================

    public function test_can_create_a_numbered_parking_space(): void
    {
        $facility = $this->facility($this->complex);

        $space = ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
            'status' => ParkingSpaceStatus::ACTIVE,
            'notes' => 'Cerca del ascensor.',
        ]);

        $this->assertDatabaseHas('parking_spaces', [
            'id' => $space->id,
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
            'status' => 'active',
        ]);

        $this->assertSame(ParkingSpaceStatus::ACTIVE, $space->status);
        $this->assertSame($facility->id, $space->parkingFacility->id);
        $this->assertSame($this->complex->id, $space->residentialComplex->id);
    }

    public function test_a_parking_space_cannot_reference_a_facility_of_another_complex(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();
        $otherFacility = $this->facility($otherComplex, 'Parqueadero ajeno');

        $this->expectException(QueryException::class);

        ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $otherFacility->id,
            'number' => 'S-999',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);
    }

    public function test_a_parking_space_of_another_complex_cannot_reference_a_facility_of_this_complex(): void
    {
        $facility = $this->facility($this->complex);

        $otherComplex = ResidentialComplex::factory()->create();

        $this->expectException(QueryException::class);

        ParkingSpace::create([
            'residential_complex_id' => $otherComplex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-997',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);
    }

    public function test_a_parking_space_cannot_reference_a_facility_that_does_not_exist(): void
    {
        $this->expectException(QueryException::class);

        ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => 999999,
            'number' => 'S-998',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);
    }

    public function test_the_space_number_is_unique_per_facility(): void
    {
        $facility = $this->facility($this->complex);

        ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);
    }

    public function test_the_same_space_number_can_exist_in_another_facility(): void
    {
        $facility = $this->facility($this->complex, 'Parqueadero Sotano 1');
        $otherFacility = $this->facility($this->complex, 'Parqueadero Sotano 2');

        $space = ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);

        $otherSpace = ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $otherFacility->id,
            'number' => 'S-101',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('parking_spaces', [
            'id' => $space->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
        ]);
        $this->assertDatabaseHas('parking_spaces', [
            'id' => $otherSpace->id,
            'parking_facility_id' => $otherFacility->id,
            'number' => 'S-101',
        ]);
    }

    public function test_the_database_rejects_a_parking_space_status_outside_the_allowed_domain(): void
    {
        $facility = $this->facility($this->complex);

        $this->expectException(QueryException::class);

        DB::table('parking_spaces')->insert([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-102',
            'status' => 'reserved',
        ]);
    }

    public function test_a_facility_lists_its_spaces(): void
    {
        $facility = $this->facility($this->complex);

        $space = ParkingSpace::create([
            'residential_complex_id' => $this->complex->id,
            'parking_facility_id' => $facility->id,
            'number' => 'S-101',
            'status' => ParkingSpaceStatus::ACTIVE,
        ]);

        $this->assertCount(1, $facility->spaces);
        $this->assertSame($space->id, $facility->spaces->first()->id);
    }
}
