<?php

namespace Tests\Feature;

use App\Enums\OwnershipStatus;
use App\Enums\OwnershipType;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use App\Models\ResidentialComplex;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyUnitOwnershipsTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();
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

    // ==================== Creación ====================

    public function test_can_create_ownership_with_unit_and_person_of_the_same_complex(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $ownership = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => 100,
        ]);

        $this->assertDatabaseHas('property_unit_ownerships', [
            'id' => $ownership->id,
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => 'owner',
        ]);

        $this->assertEquals($unit->id, $ownership->propertyUnit->id);
        $this->assertEquals($person->id, $ownership->person->id);
        $this->assertNotNull($ownership->uuid);
    }

    public function test_ownership_has_auto_generated_uuid_and_route_key(): void
    {
        $ownership = PropertyUnitOwnership::factory()->for($this->complex)->create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $ownership->uuid
        );

        $this->assertEquals('uuid', $ownership->getRouteKeyName());
    }

    // ==================== FK compuesta / ACE-C02 ====================

    public function test_ownership_requires_person_of_the_same_complex_as_the_unit(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();
        $unit = $this->unit();
        $foreignPerson = Person::factory()->for($otherComplex)->create();

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $foreignPerson->id,
        ]);
    }

    public function test_ownership_complex_must_match_the_complex_of_the_unit(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();
        $unit = $this->unit();
        $person = $this->person();

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $otherComplex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ]);
    }

    // ==================== CHECK de porcentaje ====================

    public function test_percentage_above_one_hundred_is_rejected(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'percentage' => 101,
        ]);
    }

    public function test_negative_percentage_is_rejected(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'percentage' => -1,
        ]);
    }

    public function test_percentage_check_applies_even_to_a_closed_ownership(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        // Con status 'closed' la regla RN-P04 no aplica: el CHECK de porcentaje
        // (0-100) es el que rechaza el registro.
        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'percentage' => 101,
            'status' => OwnershipStatus::CLOSED,
        ]);
    }

    public function test_percentage_between_zero_and_one_hundred_is_accepted(): void
    {
        $unit = $this->unit();

        $zero = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 0,
        ]);

        $hundred = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 100,
        ]);

        $this->assertNotNull($zero->id);
        $this->assertNotNull($hundred->id);
    }

    // ==================== CHECK de vigencia ====================

    public function test_valid_until_cannot_precede_valid_from(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'valid_from' => '2026-03-10',
            'valid_until' => '2026-03-01',
        ]);
    }

    // ==================== RN-P04: suma de porcentajes vigentes ====================

    public function test_rn_p04_rejects_a_sum_above_one_hundred(): void
    {
        $unit = $this->unit();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 60,
        ]);

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 50,
        ]);
    }

    public function test_rn_p04_accepts_a_sum_equal_to_one_hundred(): void
    {
        $unit = $this->unit();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 60,
        ]);

        $second = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 40,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_rn_p04_does_not_count_closed_ownerships(): void
    {
        $unit = $this->unit();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 60,
        ]);

        // Titularidad cerrada: no está vigente y no cuenta en la suma.
        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 50,
            'status' => OwnershipStatus::CLOSED,
        ]);

        // 60 (vigente) + 40 (nuevo) = 100: la cerrada no conta.
        $third = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 40,
        ]);

        $this->assertNotNull($third->id);
    }

    public function test_rn_p04_does_not_count_null_percentages(): void
    {
        $unit = $this->unit();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 60,
        ]);

        // Porcentaje nulo: no cuenta (0) en la suma.
        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => null,
        ]);

        // 60 (vigente) + 40 (nuevo) = 100.
        $third = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 40,
        ]);

        $this->assertNotNull($third->id);
    }

    public function test_rn_p04_applies_when_a_closed_ownership_is_reactivated(): void
    {
        $unit = $this->unit();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 40,
        ]);

        $closed = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $this->person()->id,
            'percentage' => 60,
            'status' => OwnershipStatus::CLOSED,
        ]);

        // Reactivar una titularidad cerrada no es una edición en sitio (RN-P05
        // no aplica), pero la suma de vigentes se vuelve a comprobar (RN-P04):
        // 40 (vigente) + 70 (reactivada) = 110 > 100.
        $this->expectException(QueryException::class);

        $closed->update([
            'status' => OwnershipStatus::ACTIVE,
            'percentage' => 70,
        ]);
    }

    // ==================== RN-P05: la titularidad no se edita en sitio ====================

    public function test_rn_p05_rejects_updating_the_percentage_of_an_active_ownership(): void
    {
        $ownership = PropertyUnitOwnership::factory()->for($this->complex)->create([
            'percentage' => 100,
        ]);

        $this->expectException(QueryException::class);

        $ownership->update(['percentage' => 50]);
    }

    public function test_rn_p05_rejects_updating_the_valid_from_of_an_active_ownership(): void
    {
        $ownership = PropertyUnitOwnership::factory()->for($this->complex)->create();

        $this->expectException(QueryException::class);

        $ownership->update(['valid_from' => '2027-01-01']);
    }

    public function test_rn_p05_rejects_moving_an_active_ownership_to_another_person(): void
    {
        $ownership = PropertyUnitOwnership::factory()->for($this->complex)->create();
        $otherPerson = $this->person();

        $this->expectException(QueryException::class);

        $ownership->update(['person_id' => $otherPerson->id]);
    }

    public function test_rn_p05_allows_closing_the_current_ownership(): void
    {
        $ownership = PropertyUnitOwnership::factory()->for($this->complex)->create([
            'percentage' => 100,
        ]);

        $ownership->update([
            'status' => OwnershipStatus::CLOSED,
            'valid_until' => now()->toDateString(),
        ]);

        $fresh = $ownership->fresh();

        $this->assertSame(OwnershipStatus::CLOSED, $fresh->status);
        $this->assertNotNull($fresh->valid_until);
    }

    public function test_after_closing_an_ownership_a_new_current_one_can_be_created(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $ownership = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'percentage' => 100,
        ]);

        $ownership->update(['status' => OwnershipStatus::CLOSED]);

        $new = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'percentage' => 100,
        ]);

        $this->assertNotNull($new->id);
    }

    // ==================== Unicidad de la titularidad vigente ====================

    public function test_only_one_current_ownership_per_unit_person_and_type(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => null,
        ]);

        $this->expectException(QueryException::class);

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => null,
        ]);
    }

    public function test_a_new_current_ownership_is_allowed_after_closing_the_previous_one(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $first = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => null,
        ]);

        $first->update(['status' => OwnershipStatus::CLOSED]);

        $second = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => null,
        ]);

        $this->assertNotNull($second->id);
    }

    public function test_different_ownership_types_of_the_same_person_are_allowed(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::OWNER,
            'percentage' => null,
        ]);

        $other = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'ownership_type' => OwnershipType::USFRUCTUARY,
            'percentage' => null,
        ]);

        $this->assertNotNull($other->id);
    }

    // ==================== Aislamiento multi-tenant ====================

    public function test_ownership_is_not_visible_from_another_complex(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();

        $ownership = PropertyUnitOwnership::factory()->for($this->complex)->create();

        $this->assertNull(
            PropertyUnitOwnership::forComplex($otherComplex)->find($ownership->id)
        );

        $this->assertNotNull(
            PropertyUnitOwnership::forComplex($this->complex)->find($ownership->id)
        );
    }

    public function test_property_unit_lists_its_ownerships(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $ownership = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ]);

        $this->assertCount(1, $unit->ownerships);
        $this->assertTrue($unit->ownerships->first()->is($ownership));
    }

    public function test_person_lists_its_ownerships(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $ownership = PropertyUnitOwnership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ]);

        $this->assertCount(1, $person->ownerships);
        $this->assertTrue($person->ownerships->first()->is($ownership));
    }
}
