<?php

namespace Tests\Feature;

use App\Enums\ApartmentOperationalStatus;
use App\Enums\PropertyUnitType;
use App\Models\Apartment;
use App\Models\ApartmentType;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApartmentsTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complex = ResidentialComplex::factory()->create();
    }

    /**
     * Crea una unidad del conjunto indicado.
     */
    protected function unit(?PropertyUnitType $type = null): PropertyUnit
    {
        return PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => $type ?? PropertyUnitType::APARTMENT,
        ]);
    }

    // ==================== Creación ====================

    public function test_can_create_apartment_over_an_apartment_unit(): void
    {
        $unit = $this->unit();

        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $unit->id,
        ]);

        $this->assertNotNull($apartment->uuid);
        $this->assertDatabaseHas('apartments', [
            'uuid' => $apartment->uuid,
            'property_unit_id' => $unit->id,
        ]);

        $this->assertEquals($unit->id, $apartment->propertyUnit->id);
        $this->assertEquals($this->complex->id, $apartment->residentialComplex->id);
    }

    public function test_apartment_factory_creates_a_valid_apartment(): void
    {
        $apartment = Apartment::factory()->create();

        $this->assertNotNull($apartment->property_unit_id);
        $this->assertEquals(
            $apartment->residential_complex_id,
            $apartment->propertyUnit->residential_complex_id
        );
        $this->assertSame(
            PropertyUnitType::APARTMENT,
            $apartment->propertyUnit->unit_type
        );
    }

    public function test_operational_status_defaults_to_vacant(): void
    {
        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
        ])->refresh();

        $this->assertSame(ApartmentOperationalStatus::VACANT, $apartment->operational_status);
        $this->assertDatabaseHas('apartments', [
            'id' => $apartment->id,
            'operational_status' => 'vacant',
        ]);
    }

    public function test_short_term_rental_enabled_defaults_to_false(): void
    {
        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
        ])->refresh();

        $this->assertFalse($apartment->short_term_rental_enabled);
        $this->assertDatabaseHas('apartments', [
            'id' => $apartment->id,
            'short_term_rental_enabled' => false,
        ]);
    }

    public function test_operational_status_is_stored_as_enum_value(): void
    {
        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
            'operational_status' => ApartmentOperationalStatus::UNDER_WORKS,
        ]);

        $this->assertDatabaseHas('apartments', [
            'id' => $apartment->id,
            'operational_status' => 'under_works',
        ]);

        $this->assertInstanceOf(ApartmentOperationalStatus::class, $apartment->operational_status);
        $this->assertSame(ApartmentOperationalStatus::UNDER_WORKS, $apartment->operational_status);
    }

    // ==================== Extensión 1:1 ====================

    public function test_property_unit_can_only_extend_one_apartment(): void
    {
        $unit = $this->unit();

        Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $unit->id,
        ]);

        $this->expectException(QueryException::class);

        Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $unit->id,
        ]);
    }

    public function test_apartment_type_is_nullable(): void
    {
        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
            'apartment_type_id' => null,
        ]);

        $this->assertNull($apartment->apartment_type_id);
        $this->assertNull($apartment->apartmentType);
    }

    public function test_apartment_can_reference_an_apartment_type(): void
    {
        $type = ApartmentType::factory()->for($this->complex)->create();

        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
            'apartment_type_id' => $type->id,
        ]);

        $this->assertEquals($type->id, $apartment->apartmentType->id);
    }

    // ==================== TEC-S01 ====================

    public function test_apartment_requires_an_apartment_type_unit(): void
    {
        $unit = $this->unit(PropertyUnitType::PRIVATE_PARKING);

        $this->expectException(QueryException::class);

        Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $unit->id,
        ]);
    }

    public function test_apartment_cannot_be_moved_to_a_non_apartment_unit(): void
    {
        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
        ]);

        $parking = $this->unit(PropertyUnitType::PRIVATE_PARKING);

        $this->expectException(QueryException::class);

        $apartment->update(['property_unit_id' => $parking->id]);
    }

    // ==================== Coherencia de conjunto ====================

    public function test_apartment_must_belong_to_the_same_complex_as_its_unit(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();
        $unit = $this->unit();

        $this->expectException(QueryException::class);

        Apartment::factory()->for($otherComplex)->create([
            'property_unit_id' => $unit->id,
        ]);
    }

    public function test_apartment_is_not_visible_from_another_complex(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();

        $apartment = Apartment::factory()->for($this->complex)->create([
            'property_unit_id' => $this->unit()->id,
        ]);

        $this->assertNull(
            Apartment::forComplex($otherComplex)->find($apartment->id)
        );

        $this->assertNotNull(
            Apartment::forComplex($this->complex)->find($apartment->id)
        );
    }
}
