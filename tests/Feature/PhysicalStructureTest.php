<?php

namespace Tests\Feature;

use App\Enums\FloorType;
use App\Models\ApartmentType;
use App\Models\Building;
use App\Models\BuildingFloor;
use App\Models\ResidentialComplex;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhysicalStructureTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();

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

    // ==================== Buildings ====================

    public function test_can_create_building(): void
    {
        $building = Building::factory()->for($this->complex)->create([
            'name' => 'Torre A',
            'code' => 'A',
        ]);

        $this->assertDatabaseHas('buildings', [
            'uuid' => $building->uuid,
            'name' => 'Torre A',
            'code' => 'A',
        ]);
    }

    public function test_building_belongs_to_complex(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        $this->assertEquals($this->complex->id, $building->residentialComplex->id);
    }

    public function test_building_code_is_unique_per_complex(): void
    {
        Building::factory()->for($this->complex)->create(['code' => 'A']);

        $this->expectException(QueryException::class);

        Building::factory()->for($this->complex)->create(['code' => 'A']);
    }

    public function test_same_building_code_allowed_in_different_complexes(): void
    {
        $building1 = Building::factory()->for($this->complex)->create(['code' => 'A']);
        $complex2 = ResidentialComplex::factory()->create();
        $building2 = Building::factory()->for($complex2)->create(['code' => 'A']);

        $this->assertNotEquals($building1->id, $building2->id);
    }

    public function test_building_has_elevators_constraint(): void
    {
        // El CHECK vive en la base (PostgreSQL): un edificio con ascensor
        // declarado pero sin cantidad de ascensores no es valido.
        $this->expectException(QueryException::class);

        Building::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'has_elevators' => true,
            'elevator_count' => 0,
        ]);
    }

    public function test_building_can_have_elevators(): void
    {
        $building = Building::factory()
            ->for($this->complex)
            ->withElevators(2)
            ->create();

        $this->assertTrue($building->has_elevators);
        $this->assertEquals(2, $building->elevator_count);
    }

    public function test_building_uuid_is_generated(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        $this->assertNotNull($building->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $building->uuid
        );
    }

    public function test_building_uses_uuid_route_key(): void
    {
        $building = Building::factory()->for($this->complex)->create();
        $routeKey = $building->getRouteKeyName();

        $this->assertEquals('uuid', $routeKey);
    }

    // ==================== Building Floors ====================

    public function test_can_create_floor(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        $floor = BuildingFloor::factory()->for($building)->create([
            'floor_number' => 1,
            'floor_type' => FloorType::RESIDENTIAL,
        ]);

        $this->assertDatabaseHas('building_floors', [
            'uuid' => $floor->uuid,
            'floor_number' => 1,
        ]);
    }

    public function test_floor_belongs_to_building(): void
    {
        $building = Building::factory()->for($this->complex)->create();
        $floor = BuildingFloor::factory()->for($building)->create();

        $this->assertEquals($building->id, $floor->building->id);
    }

    public function test_floor_number_is_unique_per_building(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        BuildingFloor::factory()->for($building)->create(['floor_number' => 1]);

        $this->expectException(QueryException::class);

        BuildingFloor::factory()->for($building)->create(['floor_number' => 1]);
    }

    public function test_negative_floor_number_for_basements(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        $basement = BuildingFloor::factory()->for($building)->basement()->create();

        $this->assertLessThan(0, $basement->floor_number);
        $this->assertTrue($basement->isBasement());
    }

    public function test_floor_display_name(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        $floorWithName = BuildingFloor::factory()->for($building)->create([
            'floor_number' => 5,
            'name' => 'Piso Ejecutivo',
        ]);

        $floorWithoutName = BuildingFloor::factory()->for($building)->create([
            'floor_number' => 6,
            'name' => null,
        ]);

        $this->assertEquals('Piso Ejecutivo', $floorWithName->display_name);
        $this->assertEquals('Piso 6', $floorWithoutName->display_name);
    }

    public function test_building_has_many_floors(): void
    {
        $building = Building::factory()->for($this->complex)->create();

        // Create floors with unique floor numbers
        for ($i = 1; $i <= 5; $i++) {
            BuildingFloor::factory()->for($building)->create([
                'floor_number' => $i,
                'floor_type' => FloorType::RESIDENTIAL,
            ]);
        }

        $this->assertCount(5, $building->fresh()->floors);
    }

    // ==================== Apartment Types ====================

    public function test_can_create_apartment_type(): void
    {
        $type = ApartmentType::factory()->for($this->complex)->create([
            'code' => '2H',
            'name' => '2 Habitaciones',
            'area_m2' => 75.50,
        ]);

        $this->assertDatabaseHas('apartment_types', [
            'uuid' => $type->uuid,
            'code' => '2H',
            'name' => '2 Habitaciones',
        ]);
    }

    public function test_apartment_type_belongs_to_complex(): void
    {
        $type = ApartmentType::factory()->for($this->complex)->create();

        $this->assertEquals($this->complex->id, $type->residentialComplex->id);
    }

    public function test_apartment_type_code_is_unique_per_complex(): void
    {
        ApartmentType::factory()->for($this->complex)->create(['code' => '3H']);

        $this->expectException(QueryException::class);

        ApartmentType::factory()->for($this->complex)->create(['code' => '3H']);
    }

    public function test_apartment_type_has_features(): void
    {
        $type = ApartmentType::factory()->for($this->complex)->create([
            'features' => [
                'bedrooms' => 3,
                'bathrooms' => 2,
                'balcony' => true,
            ],
        ]);

        $this->assertEquals(3, $type->getFeature('bedrooms'));
        $this->assertTrue($type->getFeature('balcony'));
        $this->assertNull($type->getFeature('nonexistent'));
    }

    // ==================== Multi-tenant Isolation ====================

    public function test_cannot_access_building_from_different_complex(): void
    {
        $complex2 = ResidentialComplex::factory()->create();
        $building = Building::factory()->for($complex2)->create();

        $this->assertNull(
            Building::forComplex($this->complex)->find($building->id)
        );
    }

    public function test_cannot_access_floor_from_different_complex(): void
    {
        $complex2 = ResidentialComplex::factory()->create();
        $building = Building::factory()->for($complex2)->create();
        $floor = BuildingFloor::factory()->for($building)->create();

        $this->assertNull(
            BuildingFloor::forComplex($this->complex)->find($floor->id)
        );
    }

    public function test_cannot_access_apartment_type_from_different_complex(): void
    {
        $complex2 = ResidentialComplex::factory()->create();
        $type = ApartmentType::factory()->for($complex2)->create();

        $this->assertNull(
            ApartmentType::forComplex($this->complex)->find($type->id)
        );
    }
}
