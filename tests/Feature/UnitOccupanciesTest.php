<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Enums\OccupancyStatus;
use App\Enums\OccupancyType;
use App\Enums\OwnershipType;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use App\Models\ResidentialComplex;
use App\Models\UnitLease;
use App\Models\UnitOccupancy;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitOccupanciesTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();

        // El contexto de conjunto vive en la sesion: si otro archivo de
        // prueba lo dejo seteado, el scope de conjunto filtraria las
        // relaciones.
        ComplexContext::clear();

        $this->complex = ResidentialComplex::factory()->create();

        // S62: sin conjunto resuelto el ambito global no devuelve ninguna fila
        // de ningun conjunto. Este archivo opera sobre un unico conjunto, asi
        // que el contexto se fija antes de cualquier lectura.
        ComplexContext::setCurrentComplexId($this->complex->id);
    }

    protected function tearDown(): void
    {
        ComplexContext::clear();

        parent::tearDown();
    }

    /**
     * Unidad del conjunto de prueba.
     */
    protected function unit(): PropertyUnit
    {
        return PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);
    }

    /**
     * Persona del conjunto de prueba.
     */
    protected function person(): Person
    {
        return Person::factory()->for($this->complex)->create();
    }

    /**
     * Ocupación del conjunto de prueba.
     */
    protected function occupancy(array $attributes = []): UnitOccupancy
    {
        $unit = $this->unit();
        $person = $this->person();

        return UnitOccupancy::factory()->create(array_merge([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ], $attributes));
    }

    /**
     * Arrendamiento del conjunto de prueba.
     */
    protected function lease(array $attributes = []): UnitLease
    {
        $unit = $this->unit();
        $tenant = $this->person();

        return UnitLease::factory()->create(array_merge([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
        ], $attributes));
    }

    // ==================== Creación ====================

    public function test_can_create_occupancy_with_unit_and_person_of_the_same_complex(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $occupancy = UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'occupancy_type' => OccupancyType::FAMILY_MEMBER,
        ]);

        $this->assertDatabaseHas('unit_occupancies', [
            'id' => $occupancy->id,
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'occupancy_type' => 'family_member',
            'status' => 'active',
        ]);

        $this->assertEquals($unit->id, $occupancy->propertyUnit->id);
        $this->assertEquals($person->id, $occupancy->person->id);
        $this->assertNotNull($occupancy->uuid);
    }

    // ==================== RN-P07: un solo contacto principal vigente ====================

    public function test_only_one_primary_contact_per_unit(): void
    {
        $unit = $this->unit();
        $first = $this->person();
        $second = $this->person();

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $first->id,
            'is_primary_contact' => true,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_primary_contact_unique');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $second->id,
            'is_primary_contact' => true,
        ]);
    }

    public function test_a_closed_primary_contact_frees_the_slot_for_a_new_one(): void
    {
        $unit = $this->unit();
        $first = $this->person();
        $second = $this->person();

        $primary = UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $first->id,
            'is_primary_contact' => true,
        ]);

        $primary->update(['status' => OccupancyStatus::CLOSED]);

        $new = UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $second->id,
            'is_primary_contact' => true,
        ]);

        $this->assertEquals('active', $new->fresh()->status->value);
    }

    public function test_rejects_two_active_occupancies_for_the_same_person_in_the_same_unit(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_active_person_per_unit_unique');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'occupancy_type' => OccupancyType::FAMILY_MEMBER,
        ]);
    }

    // ==================== RN-P08: el titular no es automáticamente residente ====================

    public function test_creating_an_ownership_does_not_create_an_occupancy(): void
    {
        $unit = $this->unit();
        $owner = $this->person();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $owner->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => 100,
        ]);

        $this->assertDatabaseCount('unit_occupancies', 0);
    }

    // ==================== RN-P09: la ocupación de un arrendamiento ====================

    public function test_rejects_an_occupancy_linked_to_a_lease_that_is_not_a_tenant_resident(): void
    {
        $lease = $this->lease();
        $other = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P09');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $lease->property_unit_id,
            'person_id' => $other->id,
            'occupancy_type' => OccupancyType::OWNER_RESIDENT,
            'lease_id' => $lease->id,
        ]);
    }

    public function test_rejects_an_occupancy_linked_to_a_lease_for_another_person(): void
    {
        $lease = $this->lease();
        $other = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P09');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $lease->property_unit_id,
            'person_id' => $other->id,
            'occupancy_type' => OccupancyType::TENANT_RESIDENT,
            'lease_id' => $lease->id,
        ]);
    }

    public function test_rejects_an_occupancy_linked_to_a_lease_in_another_unit(): void
    {
        $lease = $this->lease();
        $otherUnit = $this->unit();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P09');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $otherUnit->id,
            'person_id' => $lease->tenant_person_id,
            'occupancy_type' => OccupancyType::TENANT_RESIDENT,
            'lease_id' => $lease->id,
        ]);
    }

    // ==================== RN-P10: un arrendamiento terminado no admite ocupación vigente ====================

    public function test_a_finished_lease_does_not_admit_a_new_active_occupancy(): void
    {
        $lease = $this->lease();
        $lease->update(['status' => LeaseStatus::TERMINATED]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P10');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $lease->property_unit_id,
            'person_id' => $lease->tenant_person_id,
            'occupancy_type' => OccupancyType::TENANT_RESIDENT,
            'lease_id' => $lease->id,
        ]);
    }

    // ==================== CHECKs de dominio ====================

    public function test_rejects_an_invalid_occupancy_type(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        // Insercion directa: el cast del enum cortaria antes de llegar a la
        // base, y lo que se quiere comprobar es que el CHECK vive en la base.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_occupancy_type_check');

        DB::table('unit_occupancies')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'occupancy_type' => 'owner',
            'valid_from' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    public function test_rejects_an_invalid_status(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_status_check');

        DB::table('unit_occupancies')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'occupancy_type' => 'other_resident',
            'valid_from' => '2026-01-01',
            'status' => 'pending',
        ]);
    }

    public function test_rejects_a_validity_that_ends_before_it_starts(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_validity_check');

        $this->occupancy(['valid_from' => '2026-06-30', 'valid_until' => '2026-01-01']);
    }

    // ==================== Aislamiento multi-tenant ====================

    public function test_rejects_a_person_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $person = Person::factory()->for($other)->create();
        $unit = $this->unit();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_person_complex_fk');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ]);
    }

    public function test_rejects_a_unit_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $unit = PropertyUnit::factory()->for($other)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_occupancies_property_unit_complex_fk');

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ]);
    }

    public function test_rejects_a_lease_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $otherUnit = PropertyUnit::factory()->for($other)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);
        $otherTenant = Person::factory()->for($other)->create();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $other->id,
            'property_unit_id' => $otherUnit->id,
            'tenant_person_id' => $otherTenant->id,
        ]);

        $unit = $this->unit();
        $person = $this->person();

        // La garantia que manda aqui es la de RN-P09 (el trigger rechaza la
        // ocupacion antes de que se evaluen las FK); la FK compuesta del
        // arrendamiento es una red de seguridad adicional. Se comprueba que
        // la base rechaza la escritura, sin atar el test a un unico mensaje.
        $this->expectException(QueryException::class);

        UnitOccupancy::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'lease_id' => $lease->id,
        ]);
    }
}
