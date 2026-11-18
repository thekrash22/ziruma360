<?php

namespace Tests\Feature;

use App\Enums\PropertyUnitStatus;
use App\Enums\PropertyUnitType;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyUnitsTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complex = ResidentialComplex::factory()->create();
    }

    // ==================== Creación ====================

    public function test_can_create_property_unit_with_auto_generated_uuid(): void
    {
        $unit = PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
            'number' => 'A-101',
        ]);

        $this->assertNotNull($unit->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $unit->uuid
        );

        $this->assertDatabaseHas('property_units', [
            'uuid' => $unit->uuid,
            'number' => 'A-101',
        ]);
    }

    public function test_property_unit_belongs_to_complex(): void
    {
        $unit = PropertyUnit::factory()->for($this->complex)->create();

        $this->assertEquals($this->complex->id, $unit->residentialComplex->id);
    }

    public function test_property_unit_belongs_to_building_and_building_floor(): void
    {
        $building = Building::factory()->for($this->complex)->create();
        $floor = BuildingFloor::factory()->for($building)->create(['floor_number' => 3]);

        $unit = PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $building->id,
            'building_floor_id' => $floor->id,
        ]);

        $this->assertEquals($building->id, $unit->building->id);
        $this->assertEquals($floor->id, $unit->buildingFloor->id);
    }

    // ==================== Unicidad del número (S11) ====================

    public function test_number_is_unique_per_complex_building_and_type(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $building->id,
            'unit_type' => PropertyUnitType::APARTMENT,
            'number' => '101',
        ]);

        $this->expectException(QueryException::class);

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $building->id,
            'unit_type' => PropertyUnitType::APARTMENT,
            'number' => '101',
        ]);
    }

    public function test_number_is_unique_even_when_building_is_null(): void
    {
        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => null,
            'unit_type' => PropertyUnitType::PRIVATE_PARKING,
            'number' => 'P-01',
        ]);

        $this->expectException(QueryException::class);

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => null,
            'unit_type' => PropertyUnitType::PRIVATE_PARKING,
            'number' => 'P-01',
        ]);
    }

    public function test_same_number_is_allowed_in_another_complex(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => null,
            'unit_type' => PropertyUnitType::STORAGE,
            'number' => 'S-01',
        ]);

        $unit = PropertyUnit::factory()->for($otherComplex)->create([
            'building_id' => null,
            'unit_type' => PropertyUnitType::STORAGE,
            'number' => 'S-01',
        ]);

        $this->assertNotNull($unit->id);
        $this->assertEquals($otherComplex->id, $unit->residential_complex_id);
    }

    public function test_same_number_is_allowed_in_another_building(): void
    {
        $buildingA = Building::factory()->for($this->complex)->create();
        $buildingB = Building::factory()->for($this->complex)->create();

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $buildingA->id,
            'unit_type' => PropertyUnitType::APARTMENT,
            'number' => '101',
        ]);

        $unit = PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $buildingB->id,
            'unit_type' => PropertyUnitType::APARTMENT,
            'number' => '101',
        ]);

        $this->assertNotNull($unit->id);
    }

    public function test_same_number_is_allowed_with_another_unit_type(): void
    {
        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => null,
            'unit_type' => PropertyUnitType::STORAGE,
            'number' => 'X-01',
        ]);

        $unit = PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => null,
            'unit_type' => PropertyUnitType::COMMERCIAL_UNIT,
            'number' => 'X-01',
        ]);

        $this->assertNotNull($unit->id);
    }

    // ==================== Coherencia piso ↔ edificio ====================

    public function test_building_floor_must_belong_to_the_same_building(): void
    {
        $buildingA = Building::factory()->for($this->complex)->create();
        $buildingB = Building::factory()->for($this->complex)->create();
        $floorOfBuildingB = BuildingFloor::factory()->for($buildingB)->create();

        $this->expectException(QueryException::class);

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $buildingA->id,
            'building_floor_id' => $floorOfBuildingB->id,
        ]);
    }

    public function test_building_floor_of_the_same_building_is_accepted(): void
    {
        $building = Building::factory()->for($this->complex)->create();
        $floor = BuildingFloor::factory()->for($building)->create();

        $unit = PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $building->id,
            'building_floor_id' => $floor->id,
        ]);

        $this->assertEquals($floor->id, $unit->buildingFloor->id);
    }

    // ==================== Enums ====================

    public function test_unit_type_and_status_are_stored_as_enum_values(): void
    {
        $unit = PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => PropertyUnitType::PRIVATE_PARKING,
            'status' => PropertyUnitStatus::UNDER_REVIEW,
        ]);

        $this->assertDatabaseHas('property_units', [
            'id' => $unit->id,
            'unit_type' => 'private_parking',
            'status' => 'under_review',
        ]);

        $this->assertInstanceOf(PropertyUnitType::class, $unit->unit_type);
        $this->assertInstanceOf(PropertyUnitStatus::class, $unit->status);
        $this->assertSame(PropertyUnitType::PRIVATE_PARKING, $unit->unit_type);
        $this->assertSame(PropertyUnitStatus::UNDER_REVIEW, $unit->status);
    }

    public function test_scopes_active_and_of_type(): void
    {
        $active = PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);

        PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => PropertyUnitType::STORAGE,
            'status' => PropertyUnitStatus::INACTIVE,
        ]);

        $this->assertTrue(PropertyUnit::active()->ofType(PropertyUnitType::APARTMENT)->get()->contains($active));
        $this->assertCount(1, PropertyUnit::active()->get());
        $this->assertCount(1, PropertyUnit::ofType(PropertyUnitType::STORAGE)->get());
        $this->assertCount(1, PropertyUnit::ofType(PropertyUnitType::APARTMENT)->get());
    }

    // ==================== Integridad referencial ====================

    public function test_building_with_units_cannot_be_deleted(): void
    {
        $building = Building::factory()->for($this->complex)->create();
        $floor = BuildingFloor::factory()->for($building)->create();

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $building->id,
            'building_floor_id' => $floor->id,
        ]);

        $this->expectException(QueryException::class);

        $building->delete();
    }

    public function test_floor_with_units_cannot_be_deleted(): void
    {
        $building = Building::factory()->for($this->complex)->create();
        $floor = BuildingFloor::factory()->for($building)->create();

        PropertyUnit::factory()->for($this->complex)->create([
            'building_id' => $building->id,
            'building_floor_id' => $floor->id,
        ]);

        $this->expectException(QueryException::class);

        $floor->delete();
    }

    public function test_building_without_units_can_be_deleted(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        $building->delete();

        $this->assertDatabaseMissing('buildings', ['id' => $building->id]);
    }
}
