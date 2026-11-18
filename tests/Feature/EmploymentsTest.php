<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\MembershipStatus;
use App\Enums\PropertyUnitType;
use App\Models\Employment;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmploymentsTest extends TestCase
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
     * Persona del conjunto de prueba (sin cuenta de acceso).
     */
    protected function person(): Person
    {
        return Person::factory()->for($this->complex)->create();
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

    // ==================== Alta ====================

    public function test_can_create_complex_staff_employment_without_an_employer_unit(): void
    {
        $person = $this->person();

        $employment = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
            'position' => 'Portero',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('employments', [
            'id' => $employment->id,
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => 'complex_staff',
            'status' => 'active',
            'access_authorized' => false,
        ]);

        $this->assertNull($employment->fresh()->employer_property_unit_id);
        $this->assertEquals($person->id, $employment->person->id);
        $this->assertNotNull($employment->uuid);
    }

    public function test_can_create_unit_domestic_employment_with_the_employer_unit(): void
    {
        $person = $this->person();
        $unit = $this->unit();

        $employment = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => EmploymentType::UNIT_DOMESTIC,
        ]);

        $this->assertDatabaseHas('employments', [
            'id' => $employment->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => 'unit_domestic',
        ]);

        $this->assertEquals($unit->id, $employment->employerPropertyUnit->id);
        $this->assertCount(1, $unit->employments);
        $this->assertCount(1, $person->employments);
    }

    public function test_can_create_a_contractor_for_the_complex_and_for_a_unit(): void
    {
        $person = $this->person();
        $unit = $this->unit();

        $forComplex = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::CONTRACTOR,
        ]);

        $forUnit = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => EmploymentType::CONTRACTOR,
        ]);

        $this->assertNull($forComplex->fresh()->employer_property_unit_id);
        $this->assertEquals($unit->id, $forUnit->fresh()->employer_property_unit_id);
    }

    // ==================== Coherencia tipo de empleo / empleador ====================

    public function test_rejects_complex_staff_with_an_employer_unit(): void
    {
        $person = $this->person();
        $unit = $this->unit();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_employer_presence_check');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
        ]);
    }

    public function test_rejects_unit_domestic_without_an_employer_unit(): void
    {
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_employer_presence_check');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::UNIT_DOMESTIC,
        ]);
    }

    public function test_a_rejected_employment_is_not_stored(): void
    {
        $person = $this->person();

        try {
            // La transaccion anidada usa un savepoint: el error de la base
            // no aborta la transaccion de la prueba.
            DB::transaction(function () use ($person) {
                Employment::factory()->create([
                    'residential_complex_id' => $this->complex->id,
                    'person_id' => $person->id,
                    'employment_type' => EmploymentType::UNIT_DOMESTIC,
                ]);
            });

            $this->fail('La base deberia rechazar un empleado domestico sin unidad empleadora.');
        } catch (QueryException) {
            // esperado
        }

        $this->assertDatabaseCount('employments', 0);
    }

    // ==================== CHECKs de dominio ====================

    public function test_rejects_a_period_that_ends_before_it_starts(): void
    {
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_period_check');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'valid_from' => '2026-06-30',
            'valid_until' => '2026-01-01',
        ]);
    }

    public function test_rejects_an_invalid_status(): void
    {
        $person = $this->person();

        // Insercion directa: el cast del enum cortaria antes de llegar a la
        // base, y lo que se quiere comprobar es que el CHECK vive en la base.
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_status_check');

        DB::table('employments')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => 'complex_staff',
            'valid_from' => '2026-01-01',
            'status' => 'pending',
        ]);
    }

    public function test_rejects_an_invalid_employment_type(): void
    {
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_employment_type_check');

        DB::table('employments')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => 'security_guard',
            'valid_from' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    public function test_rejects_a_schedule_that_is_not_an_object(): void
    {
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_schedule_check');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'schedule' => ['08:00-17:00'],
        ]);
    }

    public function test_accepts_a_schedule_object(): void
    {
        $person = $this->person();

        $employment = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'schedule' => [
                'monday' => ['from' => '08:00', 'to' => '17:00'],
            ],
        ]);

        $schedule = $employment->fresh()->schedule;

        $this->assertSame('08:00', $schedule['monday']['from']);
    }

    // ==================== RN-P11: acceso al sistema ====================

    public function test_accepts_an_employment_without_an_access_account(): void
    {
        $person = $this->person();

        $employment = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
        ]);

        // Tener empleo no implica tener cuenta (RN-P11).
        $this->assertNull($person->fresh()->user_id);
        $this->assertFalse($employment->fresh()->access_authorized);
    }

    public function test_rejects_access_authorization_without_an_access_account(): void
    {
        $person = $this->person();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P11');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'access_authorized' => true,
        ]);
    }

    public function test_rejects_access_authorization_without_an_active_membership(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($this->complex)->create([
            'user_id' => $user->id,
        ]);

        ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::SUSPENDED,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P11');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'access_authorized' => true,
        ]);
    }

    public function test_accepts_access_authorization_with_an_account_and_an_active_membership(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($this->complex)->create([
            'user_id' => $user->id,
        ]);

        ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $employment = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'access_authorized' => true,
        ]);

        $this->assertTrue($employment->fresh()->access_authorized);
    }

    public function test_rejects_granting_access_after_the_employment_exists(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($this->complex)->create([
            'user_id' => $user->id,
        ]);

        $employment = Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'access_authorized' => false,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('RN-P11');

        $employment->update(['access_authorized' => true]);
    }

    // ==================== Un empleo activo por tipo y empleador ====================

    public function test_rejects_a_second_active_employment_of_the_same_type_for_the_same_employer(): void
    {
        $person = $this->person();

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('empleo activo');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
        ]);
    }

    public function test_rejects_a_second_active_unit_domestic_employment_for_the_same_unit(): void
    {
        $person = $this->person();
        $unit = $this->unit();

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => EmploymentType::UNIT_DOMESTIC,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('empleo activo');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => EmploymentType::UNIT_DOMESTIC,
        ]);
    }

    public function test_accepts_two_active_employments_of_different_types(): void
    {
        $person = $this->person();

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
        ]);

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::CONTRACTOR,
        ]);

        $this->assertDatabaseCount('employments', 2);
    }

    public function test_accepts_a_new_active_employment_when_the_previous_one_ended(): void
    {
        $person = $this->person();

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
            'status' => EmploymentStatus::ENDED,
        ]);

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employment_type' => EmploymentType::COMPLEX_STAFF,
        ]);

        $this->assertDatabaseCount('employments', 2);
    }

    // ==================== Aislamiento multi-tenant ====================

    public function test_rejects_a_person_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $person = Person::factory()->for($other)->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_person_complex_fk');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
        ]);
    }

    public function test_rejects_an_employer_unit_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $person = $this->person();
        $unit = PropertyUnit::factory()->for($other)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('employments_unit_complex_fk');

        Employment::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'person_id' => $person->id,
            'employer_property_unit_id' => $unit->id,
            'employment_type' => EmploymentType::UNIT_DOMESTIC,
        ]);
    }
}
