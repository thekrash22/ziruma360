<?php

namespace Tests\Feature;

use App\Enums\LeaseStatus;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Models\UnitLease;
use App\Models\UnitOccupancy;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnitLeasesTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();

        // El contexto de conjunto es estatico: si otro archivo de prueba lo
        // dejo seteado, el scope de conjunto filtraria las relaciones.
        ComplexContext::clear();

        $this->complex = ResidentialComplex::factory()->create();
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

    public function test_can_create_lease_with_unit_and_tenant_of_the_same_complex(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('unit_leases', [
            'id' => $lease->id,
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'status' => 'active',
        ]);

        $this->assertEquals($unit->id, $lease->propertyUnit->id);
        $this->assertEquals($tenant->id, $lease->tenantPerson->id);
        $this->assertNotNull($lease->uuid);
    }

    public function test_accepts_a_lease_without_landlord(): void
    {
        $lease = $this->lease();

        $this->assertNull($lease->fresh()->landlord_person_id);
    }

    public function test_accepts_a_lease_with_a_landlord_of_the_same_complex(): void
    {
        $landlord = $this->person();

        $lease = $this->lease(['landlord_person_id' => $landlord->id]);

        $this->assertEquals($landlord->id, $lease->fresh()->landlord_person_id);
        $this->assertEquals($landlord->id, $lease->landlordPerson->id);
    }

    // ==================== RN-P09: la ocupación del arrendatario ====================

    public function test_creating_a_lease_creates_the_tenant_occupancy(): void
    {
        $lease = $this->lease(['starts_on' => '2026-01-01']);

        $this->assertDatabaseHas('unit_occupancies', [
            'lease_id' => $lease->id,
            'property_unit_id' => $lease->property_unit_id,
            'person_id' => $lease->tenant_person_id,
            'occupancy_type' => 'tenant_resident',
            'status' => 'active',
            'is_primary_contact' => false,
        ]);

        $occupancy = UnitOccupancy::where('lease_id', $lease->id)->first();

        $this->assertNotNull($occupancy);
        $this->assertEquals('2026-01-01', $occupancy->valid_from->toDateString());
        $this->assertCount(1, $lease->occupancies);
    }

    // ==================== RN-P10: el fin del arrendamiento ====================

    public function test_terminating_a_lease_closes_the_occupancy_and_revokes_the_delegations(): void
    {
        $lease = $this->lease([
            'starts_on' => '2026-01-01',
            'ends_on' => '2026-06-30',
            'delegated_permissions' => ['manage_employees' => true],
        ]);

        $lease->update(['status' => LeaseStatus::TERMINATED]);

        $this->assertDatabaseHas('unit_leases', [
            'id' => $lease->id,
            'status' => 'terminated',
        ]);

        // Las delegaciones asociadas quedan revocadas (RN-P10).
        $this->assertNull($lease->fresh()->delegated_permissions);

        $occupancy = UnitOccupancy::where('lease_id', $lease->id)->first();

        $this->assertEquals('closed', $occupancy->status->value);
        $this->assertEquals('2026-06-30', $occupancy->valid_until->toDateString());
    }

    public function test_ending_a_lease_also_closes_the_occupancy(): void
    {
        $lease = $this->lease(['starts_on' => '2026-01-01']);

        $lease->update(['status' => LeaseStatus::ENDED]);

        $occupancy = UnitOccupancy::where('lease_id', $lease->id)->first();

        $this->assertEquals('closed', $occupancy->status->value);
        $this->assertNotNull($occupancy->valid_until);
    }

    // ==================== CHECKs de dominio ====================

    public function test_rejects_a_period_that_ends_before_it_starts(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_leases_period_check');

        $this->lease(['starts_on' => '2026-06-30', 'ends_on' => '2026-01-01']);
    }

    public function test_rejects_an_invalid_status(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        // Insercion directa: el cast del enum cortaria antes de llegar a la
        // base, y lo que se quiere comprobar es que el CHECK vive en la base.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_leases_status_check');

        DB::table('unit_leases')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'starts_on' => '2026-01-01',
            'status' => 'pending',
        ]);
    }

    public function test_rejects_delegated_permissions_that_are_not_an_object(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('delegated_permissions');

        $this->lease(['delegated_permissions' => ['manage_employees']]);
    }

    public function test_rejects_delegated_permissions_with_non_boolean_values(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('valores booleanos');

        $this->lease(['delegated_permissions' => ['manage_employees' => 'yes']]);
    }

    public function test_accepts_delegated_permissions_as_boolean_values(): void
    {
        $lease = $this->lease([
            'delegated_permissions' => [
                'manage_employees' => true,
                'invite_visitors' => false,
            ],
        ]);

        $permissions = $lease->fresh()->delegated_permissions;

        $this->assertTrue($permissions['manage_employees']);
        $this->assertFalse($permissions['invite_visitors']);
    }

    // ==================== Aislamiento multi-tenant ====================

    public function test_rejects_a_tenant_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $tenant = Person::factory()->for($other)->create();
        $unit = $this->unit();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_leases_tenant_complex_fk');

        UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
        ]);
    }

    public function test_rejects_a_landlord_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $landlord = Person::factory()->for($other)->create();
        $unit = $this->unit();
        $tenant = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_leases_landlord_complex_fk');

        UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'landlord_person_id' => $landlord->id,
        ]);
    }

    public function test_rejects_a_unit_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $unit = PropertyUnit::factory()->for($other)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);
        $tenant = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('unit_leases_property_unit_complex_fk');

        UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
        ]);
    }
}
