<?php

namespace Tests\Feature;

use App\Enums\PropertyUnitRelationStatus;
use App\Enums\PropertyUnitRelationType;
use App\Enums\PropertyUnitType;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitRelation;
use App\Models\ResidentialComplex;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ValueError;

/**
 * T9 / S50-S52: `property_unit_relations` (spec §3.7).
 *
 * Cubre la vinculacion entre unidades (anexos, parqueaderos), sus garantias a
 * nivel de base de datos (mismo conjunto, no relacion consigo misma, vigencia)
 * y las reglas RN-S05 y ACE-S02.
 */
class PropertyUnitRelationsTest extends TestCase
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

    private function unit(
        string $number,
        PropertyUnitType $type = PropertyUnitType::APARTMENT,
        ?ResidentialComplex $complex = null
    ): PropertyUnit {
        return PropertyUnit::factory()
            ->for($complex ?? $this->complex)
            ->ofType($type)
            ->create(['number' => $number]);
    }

    public function test_a_relation_links_two_units_of_the_same_complex(): void
    {
        $apartment = $this->unit('A-101');
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        $relation = PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => PropertyUnitRelationType::ANNEX,
            'status' => PropertyUnitRelationStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('property_unit_relations', [
            'id' => $relation->id,
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => 'annex',
        ]);

        $this->assertEquals($apartment->id, $relation->sourcePropertyUnit->id);
        $this->assertEquals($parking->id, $relation->targetPropertyUnit->id);
    }

    public function test_a_relation_cannot_point_to_a_unit_of_another_complex(): void
    {
        $apartment = $this->unit('A-101');
        $otherParking = $this->unit(
            'P-99',
            PropertyUnitType::PRIVATE_PARKING,
            ResidentialComplex::factory()->create()
        );

        $this->expectException(QueryException::class);

        PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $otherParking->id,
            'relation_type' => PropertyUnitRelationType::ANNEX,
        ]);
    }

    public function test_a_relation_cannot_point_to_itself(): void
    {
        $apartment = $this->unit('A-101');

        $this->expectException(QueryException::class);

        PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $apartment->id,
            'relation_type' => PropertyUnitRelationType::ANNEX,
        ]);
    }

    public function test_validity_period_cannot_end_before_it_starts(): void
    {
        $apartment = $this->unit('A-101');
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        $this->expectException(QueryException::class);

        PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => PropertyUnitRelationType::ANNEX,
            'valid_from' => '2026-01-10',
            'valid_until' => '2026-01-01',
        ]);
    }

    public function test_relation_type_is_rejected_outside_the_allowed_domain(): void
    {
        $apartment = $this->unit('A-101');
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        $this->expectException(ValueError::class);

        PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => 'owner_of',
        ]);
    }

    public function test_the_database_rejects_a_relation_type_outside_the_allowed_domain(): void
    {
        $apartment = $this->unit('A-101');
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        $this->expectException(QueryException::class);

        DB::table('property_unit_relations')->insert([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => 'owner_of',
        ]);
    }

    public function test_a_private_parking_can_exist_without_an_apartment(): void
    {
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        $this->assertDatabaseHas('property_units', [
            'id' => $parking->id,
            'unit_type' => PropertyUnitType::PRIVATE_PARKING->value,
        ]);

        $this->assertDatabaseCount('property_unit_relations', 0);
    }

    public function test_a_relation_does_not_create_an_ownership(): void
    {
        $apartment = $this->unit('A-101');
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => PropertyUnitRelationType::ANNEX,
        ]);

        // RN-S05: relacionar un parqueadero con un apartamento no implica
        // misma titularidad: la relacion no crea registros de titularidad.
        $this->assertDatabaseCount('property_unit_ownerships', 0);
    }

    public function test_a_relation_is_removed_when_its_source_unit_is_deleted(): void
    {
        $apartment = $this->unit('A-101');
        $parking = $this->unit('P-01', PropertyUnitType::PRIVATE_PARKING);

        PropertyUnitRelation::create([
            'residential_complex_id' => $this->complex->id,
            'source_property_unit_id' => $apartment->id,
            'target_property_unit_id' => $parking->id,
            'relation_type' => PropertyUnitRelationType::ANNEX,
        ]);

        $this->assertDatabaseCount('property_unit_relations', 1);

        $apartment->delete();

        $this->assertDatabaseCount('property_unit_relations', 0);
    }
}
