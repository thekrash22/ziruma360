<?php

namespace Tests\Feature;

use App\Enums\CommonAreaStatus;
use App\Enums\CommonAreaType;
use App\Enums\ExclusiveUseAssignmentStatus;
use App\Enums\PropertyUnitType;
use App\Models\Building;
use App\Models\CommonArea;
use App\Models\CommonPropertyExclusiveUseAssignment;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ValueError;

/**
 * T10 / S53-S54: `common_areas` y `common_property_exclusive_use_assignments`
 * (spec §3.8 y §3.9).
 *
 * Cubre el alta de areas comunes, sus garantias a nivel de base de datos
 * (mismo conjunto, unicidad de code por conjunto, dominio de area_type) y el
 * regimen de bienes comunes de uso exclusivo (RN-S06: la asignacion no es
 * propiedad privada).
 */
class CommonAreasTest extends TestCase
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

    private function building(ResidentialComplex $complex, string $code = 'T-1'): Building
    {
        return Building::factory()->for($complex)->create(['code' => $code]);
    }

    private function unit(ResidentialComplex $complex, string $number = 'A-101'): PropertyUnit
    {
        return PropertyUnit::factory()
            ->for($complex)
            ->ofType(PropertyUnitType::APARTMENT)
            ->create(['number' => $number]);
    }

    // ==================== Areas comunes (S53) ====================

    public function test_can_create_a_common_area(): void
    {
        $building = $this->building($this->complex);

        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'building_id' => $building->id,
            'name' => 'Piscina',
            'code' => 'PISC-01',
            'area_type' => CommonAreaType::POOL,
            'description' => 'Piscina comunitaria con duchas y salvavidas',
            'location' => 'Rooftop',
            'capacity' => 30,
            'area_m2' => 120.5,
            'is_reservable' => true,
            'status' => CommonAreaStatus::ACTIVE,
            'features' => ['shower' => true, 'lifeguard' => false],
        ]);

        $this->assertDatabaseHas('common_areas', [
            'id' => $area->id,
            'residential_complex_id' => $this->complex->id,
            'building_id' => $building->id,
            'name' => 'Piscina',
            'code' => 'PISC-01',
            'area_type' => 'pool',
            'status' => 'active',
            'is_reservable' => true,
        ]);

        $this->assertSame(CommonAreaType::POOL, $area->area_type);
        $this->assertSame(CommonAreaStatus::ACTIVE, $area->status);
        $this->assertSame($building->id, $area->building->id);
        $this->assertSame($this->complex->id, $area->residentialComplex->id);
        $this->assertSame(['shower' => true, 'lifeguard' => false], $area->features);
    }

    public function test_a_common_area_can_exist_without_a_building(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'building_id' => null,
            'name' => 'Zona BBQ',
            'code' => 'BBQ-01',
            'area_type' => CommonAreaType::BBQ,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('common_areas', [
            'id' => $area->id,
            'building_id' => null,
            'code' => 'BBQ-01',
        ]);

        $this->assertNull($area->building);
    }

    public function test_common_area_code_is_unique_per_complex(): void
    {
        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Gimnasio',
            'code' => 'GYM-01',
            'area_type' => CommonAreaType::GYM,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Otro gimnasio',
            'code' => 'GYM-01',
            'area_type' => CommonAreaType::GYM,
            'status' => CommonAreaStatus::ACTIVE,
        ]);
    }

    public function test_the_same_common_area_code_can_exist_in_another_complex(): void
    {
        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Gimnasio',
            'code' => 'GYM-01',
            'area_type' => CommonAreaType::GYM,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $other = ResidentialComplex::factory()->create();

        $otherArea = CommonArea::create([
            'residential_complex_id' => $other->id,
            'name' => 'Gimnasio',
            'code' => 'GYM-01',
            'area_type' => CommonAreaType::GYM,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('common_areas', [
            'id' => $otherArea->id,
            'residential_complex_id' => $other->id,
            'code' => 'GYM-01',
        ]);
    }

    public function test_a_common_area_cannot_reference_a_building_of_another_complex(): void
    {
        $otherBuilding = $this->building(ResidentialComplex::factory()->create(), 'T-9');

        $this->expectException(QueryException::class);

        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'building_id' => $otherBuilding->id,
            'name' => 'Terraza',
            'code' => 'TER-01',
            'area_type' => CommonAreaType::TERRACE,
            'status' => CommonAreaStatus::ACTIVE,
        ]);
    }

    public function test_the_model_rejects_an_area_type_outside_the_allowed_domain(): void
    {
        $this->expectException(ValueError::class);

        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Area inventada',
            'code' => 'INV-01',
            'area_type' => 'sauna',
            'status' => CommonAreaStatus::ACTIVE,
        ]);
    }

    public function test_the_database_rejects_an_area_type_outside_the_allowed_domain(): void
    {
        $this->expectException(QueryException::class);

        DB::table('common_areas')->insert([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Area inventada',
            'code' => 'INV-01',
            'area_type' => 'sauna',
            'status' => 'active',
        ]);
    }

    public function test_the_database_rejects_a_status_outside_the_allowed_domain(): void
    {
        $this->expectException(QueryException::class);

        DB::table('common_areas')->insert([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Area comun',
            'code' => 'COM-01',
            'area_type' => 'pool',
            'status' => 'deleted',
        ]);
    }

    // ==================== Uso exclusivo (S54) ====================

    public function test_can_create_an_exclusive_use_assignment(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-01',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2027-01-01',
            'document_reference' => 'ACTA-2026-001',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->assertDatabaseHas('common_property_exclusive_use_assignments', [
            'id' => $assignment->id,
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'document_reference' => 'ACTA-2026-001',
            'status' => 'active',
        ]);

        $this->assertSame(ExclusiveUseAssignmentStatus::ACTIVE, $assignment->status);
        $this->assertSame($area->id, $assignment->commonArea->id);
        $this->assertSame($unit->id, $assignment->propertyUnit->id);
    }

    public function test_an_assignment_cannot_reference_a_unit_of_another_complex(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-02',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $otherUnit = $this->unit(ResidentialComplex::factory()->create(), 'A-999');

        $this->expectException(QueryException::class);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $otherUnit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);
    }

    public function test_an_assignment_cannot_reference_a_common_area_of_another_complex(): void
    {
        $otherArea = CommonArea::create([
            'residential_complex_id' => ResidentialComplex::factory()->create()->id,
            'name' => 'Deposito comun ajeno',
            'code' => 'DEP-COM-03',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $this->expectException(QueryException::class);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $otherArea->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);
    }

    public function test_assignment_validity_period_cannot_end_before_it_starts(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-04',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $this->expectException(QueryException::class);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-06-01',
            'valid_until' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);
    }

    public function test_the_database_rejects_an_assignment_status_outside_the_allowed_domain(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-05',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $this->expectException(QueryException::class);

        DB::table('common_property_exclusive_use_assignments')->insert([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => 'cancelled',
        ]);
    }

    public function test_an_exclusive_use_assignment_does_not_create_an_ownership(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-06',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        // RN-S06: el bien comun asignado en uso exclusivo no se registra como
        // propiedad privada: la asignacion se separa de la titularidad legal.
        $this->assertDatabaseCount('property_unit_ownerships', 0);
        $this->assertDatabaseCount('property_units', 1);
        $this->assertDatabaseCount('common_areas', 1);
    }

    public function test_the_database_rejects_a_second_active_assignment_for_the_same_common_area(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-11',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $otherUnit = $this->unit($this->complex, 'A-102');

        $this->expectException(QueryException::class);

        DB::table('common_property_exclusive_use_assignments')->insert([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $otherUnit->id,
            'valid_from' => '2026-02-01',
            'status' => 'active',
        ]);
    }

    public function test_a_new_active_assignment_is_allowed_after_the_previous_one_is_ended(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-12',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);
        $otherUnit = $this->unit($this->complex, 'A-102');

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $assignment->update([
            'status' => ExclusiveUseAssignmentStatus::ENDED,
            'valid_until' => '2026-01-31',
        ]);

        $second = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $otherUnit->id,
            'valid_from' => '2026-02-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->assertSame(ExclusiveUseAssignmentStatus::ENDED, $assignment->fresh()->status);
        $this->assertSame(ExclusiveUseAssignmentStatus::ACTIVE, $second->fresh()->status);
        $this->assertDatabaseCount('common_property_exclusive_use_assignments', 2);
    }

    public function test_two_assignments_of_the_same_common_area_cannot_overlap_in_time(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-18',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);
        $otherUnit = $this->unit($this->complex, 'A-102');

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $assignment->update(['status' => ExclusiveUseAssignmentStatus::ENDED]);

        $this->expectException(QueryException::class);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $otherUnit->id,
            'valid_from' => '2026-06-01',
            'valid_until' => '2026-08-31',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);
    }

    public function test_an_assignment_validity_end_cannot_be_rewritten(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-19',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        $assignment->update(['valid_until' => '2027-06-30']);
    }

    public function test_an_assignment_validity_end_can_be_filled_when_it_is_closed(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-20',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $assignment->update([
            'status' => ExclusiveUseAssignmentStatus::ENDED,
            'valid_until' => '2026-06-30',
        ]);

        $this->assertSame(ExclusiveUseAssignmentStatus::ENDED, $assignment->fresh()->status);
        $this->assertSame('2026-06-30', $assignment->fresh()->valid_until->toDateString());
    }

    public function test_an_exclusive_use_assignment_cannot_be_deleted(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-13',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        $assignment->delete();
    }

    public function test_closing_an_assignment_keeps_the_record(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-14',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $assignment->update(['status' => ExclusiveUseAssignmentStatus::ENDED]);

        $this->assertDatabaseHas('common_property_exclusive_use_assignments', [
            'id' => $assignment->id,
            'status' => 'ended',
        ]);
        $this->assertDatabaseCount('common_property_exclusive_use_assignments', 1);
    }

    public function test_the_exclusive_use_assignment_history_cannot_be_rewritten(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-15',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);
        $otherUnit = $this->unit($this->complex, 'A-102');

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'document_reference' => 'ACTA-2026-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        $assignment->update(['property_unit_id' => $otherUnit->id]);
    }

    public function test_a_closed_exclusive_use_assignment_cannot_be_reopened(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-16',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $assignment->update(['status' => ExclusiveUseAssignmentStatus::ENDED]);

        $this->expectException(QueryException::class);

        $assignment->update(['status' => ExclusiveUseAssignmentStatus::ACTIVE]);
    }

    public function test_the_exclusive_use_assignment_history_cannot_be_truncated(): void
    {
        $this->expectException(QueryException::class);

        DB::statement('TRUNCATE common_property_exclusive_use_assignments');
    }

    public function test_the_non_overlap_of_assignments_is_enforced_by_an_exclusion_constraint(): void
    {
        // Un trigger no serializa las inserciones concurrentes: la garantia
        // tiene que venir del indice, que es lo que hace la restriccion de
        // exclusion.
        $constraint = DB::selectOne(
            "SELECT conname FROM pg_constraint WHERE conrelid = 'common_property_exclusive_use_assignments'::regclass AND contype = 'x'"
        );

        $this->assertNotNull($constraint);
        $this->assertSame('common_property_exclusive_use_assignments_no_overlap', $constraint->conname);
    }

    public function test_the_exclusive_use_assignment_identity_cannot_be_rewritten(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-17',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        DB::table('common_property_exclusive_use_assignments')
            ->where('id', $assignment->id)
            ->update(['created_at' => '2020-01-01 00:00:00']);
    }

    public function test_deleting_a_complex_removes_its_common_areas_when_there_are_no_assignments(): void
    {
        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-08',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $this->unit($this->complex);

        $this->complex->delete();

        $this->assertDatabaseCount('common_areas', 0);
        $this->assertDatabaseCount('common_property_exclusive_use_assignments', 0);
        $this->assertDatabaseCount('property_units', 0);
    }

    public function test_a_complex_with_exclusive_use_assignments_cannot_be_deleted(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-09',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        DB::statement('SAVEPOINT complex_delete_guard');

        try {
            $this->complex->delete();
            $this->fail('El conjunto con historial de asignaciones no debio borrarse.');
        } catch (QueryException) {
            // Restriccion esperada: el historial de asignaciones se conserva.
            DB::statement('ROLLBACK TO SAVEPOINT complex_delete_guard');
        }

        $this->assertDatabaseCount('common_areas', 1);
        $this->assertDatabaseCount('common_property_exclusive_use_assignments', 1);
    }

    public function test_a_common_area_or_unit_with_an_exclusive_use_assignment_cannot_be_deleted(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-10',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $assignment = CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        DB::statement('SAVEPOINT common_area_delete_guard');

        try {
            $area->delete();
            $this->fail('El area comun con historial de asignaciones no debio borrarse.');
        } catch (QueryException) {
            // Restriccion esperada: el historial de asignaciones se conserva.
            DB::statement('ROLLBACK TO SAVEPOINT common_area_delete_guard');
        }

        DB::statement('SAVEPOINT property_unit_delete_guard');

        try {
            $unit->delete();
            $this->fail('La unidad con historial de asignaciones no debio borrarse.');
        } catch (QueryException) {
            // Restriccion esperada: el historial de asignaciones se conserva.
            DB::statement('ROLLBACK TO SAVEPOINT property_unit_delete_guard');
        }

        $this->assertDatabaseCount('common_property_exclusive_use_assignments', 1);
        $this->assertDatabaseHas('common_property_exclusive_use_assignments', [
            'id' => $assignment->id,
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);
    }

    public function test_a_building_with_common_areas_cannot_be_deleted(): void
    {
        $building = $this->building($this->complex);

        CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'building_id' => $building->id,
            'name' => 'Salon social',
            'code' => 'SOC-01',
            'area_type' => CommonAreaType::SOCIAL_HALL,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        $building->delete();
    }

    public function test_an_exclusive_use_assignment_does_not_modify_an_existing_ownership(): void
    {
        $area = CommonArea::create([
            'residential_complex_id' => $this->complex->id,
            'name' => 'Deposito comun',
            'code' => 'DEP-COM-07',
            'area_type' => CommonAreaType::OTHER,
            'status' => CommonAreaStatus::ACTIVE,
        ]);

        $unit = $this->unit($this->complex);

        $before = DB::table('property_unit_ownerships')->count();

        CommonPropertyExclusiveUseAssignment::create([
            'residential_complex_id' => $this->complex->id,
            'common_area_id' => $area->id,
            'property_unit_id' => $unit->id,
            'valid_from' => '2026-01-01',
            'status' => ExclusiveUseAssignmentStatus::ACTIVE,
        ]);

        $this->assertSame($before, DB::table('property_unit_ownerships')->count());
    }
}
