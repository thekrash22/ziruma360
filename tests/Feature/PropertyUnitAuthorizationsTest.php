<?php

namespace Tests\Feature;

use App\Enums\AuthorizationStatus;
use App\Enums\AuthorizationType;
use App\Enums\LeaseStatus;
use App\Enums\PropertyUnitType;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitAuthorization;
use App\Models\ResidentialComplex;
use App\Models\UnitLease;
use App\Models\User;
use App\Services\ComplexContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyUnitAuthorizationsTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected ?PropertyUnit $testUnit = null;

    protected ?Person $testPerson = null;

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
        return $this->testPerson ??= Person::factory()->for($this->complex)->create();
    }

    /**
     * Unidad del conjunto de prueba.
     */
    protected function unit(): PropertyUnit
    {
        return $this->testUnit ??= PropertyUnit::factory()->for($this->complex)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);
    }

    /**
     * Autorizacion sobre una unidad y una persona del conjunto de prueba.
     */
    protected function authorization(array $attributes = []): PropertyUnitAuthorization
    {
        $unit = $attributes['property_unit_id'] ?? $this->unit();
        $person = $attributes['person_id'] ?? $this->person();

        return PropertyUnitAuthorization::factory()->create(array_merge([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit instanceof PropertyUnit ? $unit->id : $unit,
            'person_id' => $person instanceof Person ? $person->id : $person,
        ], $attributes));
    }

    // ==================== Alta ====================

    public function test_can_create_an_authorization_for_a_person_on_a_unit(): void
    {
        $unit = $this->unit();
        $person = $this->person();

        $authorization = $this->authorization([
            'authorization_type' => AuthorizationType::MANAGER,
            'scope' => ['units.manage', 'employees.manage'],
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
        ]);

        $this->assertDatabaseHas('property_unit_authorizations', [
            'id' => $authorization->id,
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'authorization_type' => 'manager',
            'status' => 'active',
        ]);

        $this->assertEquals(['units.manage', 'employees.manage'], $authorization->fresh()->scope);
        $this->assertNotNull($authorization->uuid);
        $this->assertEquals($unit->id, $authorization->propertyUnit->id);
        $this->assertEquals($person->id, $authorization->person->id);
        $this->assertCount(1, $unit->authorizations);
        $this->assertCount(1, $person->authorizations);
    }

    public function test_can_create_each_authorization_type(): void
    {
        foreach ([AuthorizationType::MANAGER, AuthorizationType::HOST, AuthorizationType::DELEGATE] as $type) {
            $authorization = $this->authorization([
                'authorization_type' => $type,
            ]);

            $this->assertSame($type, $authorization->fresh()->authorization_type);
        }

        $this->assertDatabaseCount('property_unit_authorizations', 3);
    }

    public function test_an_authorization_can_grant_no_permissions(): void
    {
        $authorization = $this->authorization([
            'scope' => [],
        ]);

        $this->assertSame([], $authorization->fresh()->scope);
    }

    // ==================== Aislamiento multi-tenant ====================

    public function test_rejects_a_person_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $foreignPerson = Person::factory()->for($other)->create();
        $unit = $this->unit();

        $this->expectException(QueryException::class);

        $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $foreignPerson->id,
        ]);
    }

    public function test_rejects_a_unit_from_another_complex(): void
    {
        $other = ResidentialComplex::factory()->create();
        $foreignUnit = PropertyUnit::factory()->for($other)->create([
            'unit_type' => PropertyUnitType::APARTMENT,
        ]);
        $person = $this->person();

        $this->expectException(QueryException::class);

        $this->authorization([
            'property_unit_id' => $foreignUnit->id,
            'person_id' => $person->id,
        ]);
    }

    // ==================== CHECKs de dominio ====================

    public function test_rejects_a_period_that_ends_before_it_starts(): void
    {
        $this->expectException(QueryException::class);

        $this->authorization([
            'valid_from' => '2026-06-01',
            'valid_until' => '2026-05-31',
        ]);
    }

    public function test_rejects_an_unknown_authorization_type(): void
    {
        $this->expectException(QueryException::class);

        DB::table('property_unit_authorizations')->insert([
            'uuid' => '11111111-1111-4111-8111-111111111111',
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'authorization_type' => 'supervisor',
            'scope' => json_encode([]),
            'valid_from' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    public function test_rejects_an_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        DB::table('property_unit_authorizations')->insert([
            'uuid' => '22222222-2222-4222-8222-222222222222',
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'authorization_type' => 'manager',
            'scope' => json_encode([]),
            'valid_from' => '2026-01-01',
            'status' => 'pending',
        ]);
    }

    // ==================== Scope ====================

    public function test_rejects_a_scope_that_is_not_a_list(): void
    {
        $this->expectException(QueryException::class);

        DB::table('property_unit_authorizations')->insert([
            'uuid' => '33333333-3333-4333-8333-333333333333',
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'authorization_type' => 'manager',
            'scope' => json_encode(['units.manage' => true]),
            'valid_from' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    public function test_rejects_a_scope_with_a_permission_that_is_not_a_non_empty_string(): void
    {
        $this->expectException(QueryException::class);

        DB::table('property_unit_authorizations')->insert([
            'uuid' => '44444444-4444-4444-8444-444444444444',
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'authorization_type' => 'manager',
            'scope' => json_encode(['units.manage', 42]),
            'valid_from' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    // ==================== RN-P16: revocacion ====================

    public function test_revoking_keeps_the_row_and_its_history(): void
    {
        $authorization = $this->authorization([
            'authorization_type' => AuthorizationType::DELEGATE,
            'scope' => ['visitors.invite'],
            'valid_from' => '2026-01-01',
        ]);

        $revoker = User::factory()->create();
        $authorization->revoke($revoker);

        $authorization->refresh();

        $this->assertSame(AuthorizationStatus::REVOKED, $authorization->status);
        $this->assertNotNull($authorization->revoked_at);
        $this->assertEquals($revoker->id, $authorization->revoked_by);
        $this->assertSame(['visitors.invite'], $authorization->scope);
        $this->assertDatabaseHas('property_unit_authorizations', [
            'id' => $authorization->id,
            'status' => 'revoked',
        ]);
    }

    public function test_a_revoked_authorization_cannot_be_reactivated(): void
    {
        $authorization = $this->authorization();
        $authorization->revoke();

        $this->expectException(QueryException::class);

        DB::table('property_unit_authorizations')
            ->where('id', $authorization->id)
            ->update(['status' => 'active']);
    }

    public function test_a_revoked_authorization_cannot_lose_its_revocation_mark(): void
    {
        $authorization = $this->authorization();
        $authorization->revoke();

        $this->expectException(QueryException::class);

        DB::table('property_unit_authorizations')
            ->where('id', $authorization->id)
            ->update(['revoked_at' => null]);
    }

    public function test_revocation_is_recorded_even_when_the_caller_does_not_set_the_moment(): void
    {
        $authorization = $this->authorization();

        DB::table('property_unit_authorizations')
            ->where('id', $authorization->id)
            ->update(['status' => 'revoked']);

        $this->assertNotNull($authorization->fresh()->revoked_at);
    }

    // ==================== RN-P15: vigencia al ejecutar ====================

    public function test_only_authorizations_effective_at_the_moment_are_in_force(): void
    {
        $person = $this->person();
        $unit = $this->unit();

        $current = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->addDay()->toDateString(),
        ]);

        $future = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'valid_from' => now()->addMonth()->toDateString(),
        ]);

        $expired = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'valid_from' => now()->subMonths(2)->toDateString(),
            'valid_until' => now()->subMonth()->toDateString(),
        ]);

        $revoked = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);
        $revoked->revoke();

        // La vista de la base resuelve la vigencia en el momento de la
        // operacion (RN-P15).
        $effective = DB::table('effective_property_unit_authorizations')
            ->pluck('id')
            ->all();

        $this->assertContains($current->id, $effective);
        $this->assertNotContains($future->id, $effective);
        $this->assertNotContains($expired->id, $effective);
        $this->assertNotContains($revoked->id, $effective);

        // El ambito del modelo dice lo mismo.
        $this->assertSame(
            [$current->id],
            PropertyUnitAuthorization::query()->effective()->pluck('id')->all()
        );

        $this->assertTrue($current->isEffectiveAt());
        $this->assertFalse($future->isEffectiveAt());
        $this->assertFalse($expired->isEffectiveAt());
        $this->assertFalse($revoked->isEffectiveAt());

        // La vigencia tambien se puede evaluar en un momento concreto.
        $this->assertTrue($future->isEffectiveAt(Carbon::now()->addMonths(2)));
    }

    public function test_an_authorization_does_not_grant_permissions_before_its_exact_start(): void
    {
        $authorization = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => '2030-06-15 14:30:00',
        ]);

        // La base guarda el instante exacto del inicio, no la medianoche del
        // dia: el periodo es un instante, no un dia.
        $stored = DB::table('property_unit_authorizations')
            ->where('id', $authorization->id)
            ->value('valid_from');

        $this->assertSame('2030-06-15 14:30:00', $stored);

        // Antes del inicio exacto no concede permisos.
        $this->assertFalse($authorization->isEffectiveAt(Carbon::parse('2030-06-15 10:00:00')));
        $this->assertTrue($authorization->isEffectiveAt(Carbon::parse('2030-06-15 14:30:00')));
        $this->assertTrue($authorization->isEffectiveAt(Carbon::parse('2030-06-15 15:00:00')));
    }

    public function test_an_authorization_keeps_permissions_until_its_exact_end(): void
    {
        $authorization = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => '2030-06-15 10:00:00',
            'valid_until' => '2030-06-15 14:30:00',
        ]);

        // El fin exacto es el limite: sigue vigente hasta ese instante.
        $this->assertTrue($authorization->isEffectiveAt(Carbon::parse('2030-06-15 13:00:00')));
        $this->assertTrue($authorization->isEffectiveAt(Carbon::parse('2030-06-15 14:30:00')));
        $this->assertFalse($authorization->isEffectiveAt(Carbon::parse('2030-06-15 15:00:00')));
    }

    public function test_an_authorization_is_effective_at_its_exact_start_and_end_instants(): void
    {
        // El instante evaluado, con resolucion de segundo, que es la que
        // almacena la columna `timestamp(0)`.
        $moment = Carbon::parse('2030-06-15 14:30:00');

        // Empieza exactamente en el instante evaluado.
        $starting = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $moment->toDateTimeString(),
        ]);

        // Termina exactamente en el instante evaluado.
        $ending = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $moment->copy()->subHour()->toDateTimeString(),
            'valid_until' => $moment->toDateTimeString(),
        ]);

        // Los dos limites son inclusivos: en el instante exacto, la
        // autorizacion todavia concede permisos.
        $this->assertTrue($starting->isEffectiveAt($moment));
        $this->assertTrue($ending->isEffectiveAt($moment));

        $this->assertContains($starting->id, PropertyUnitAuthorization::effective($moment)->pluck('id')->all());
        $this->assertContains($ending->id, PropertyUnitAuthorization::effective($moment)->pluck('id')->all());

        // Y son estrictos un segundo antes y un segundo despues.
        $this->assertFalse($starting->isEffectiveAt($moment->copy()->subSecond()));
        $this->assertFalse($ending->isEffectiveAt($moment->copy()->addSecond()));

        $this->assertNotContains($starting->id, PropertyUnitAuthorization::effective($moment->copy()->subSecond())->pluck('id')->all());
        $this->assertNotContains($ending->id, PropertyUnitAuthorization::effective($moment->copy()->addSecond())->pluck('id')->all());
    }

    public function test_the_view_and_the_model_agree_on_the_exact_timestamp_boundaries(): void
    {
        // El instante de referencia es la hora de inicio de la transaccion:
        // en PostgreSQL `now()` es ese instante, asi que la vista y el modelo
        // se evaluan en el mismo momento y se pueden comparar.
        $now = Carbon::parse(DB::selectOne('select now() as at')->at);

        // La columna es `timestamp(0)`: el segundo exacto es la unidad en la
        // que los limites se pueden expresar.
        $second = $now->copy()->startOfSecond();

        // Cubre el instante actual.
        $current = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $second->copy()->subMinute()->toDateTimeString(),
            'valid_until' => $second->copy()->addMinute()->toDateTimeString(),
        ]);

        // Empieza en el segundo exacto de la referencia.
        $starting = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $second->toDateTimeString(),
        ]);

        // Termina en el segundo siguiente a la referencia.
        $ending = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $second->copy()->subMinute()->toDateTimeString(),
            'valid_until' => $second->copy()->addSecond()->toDateTimeString(),
        ]);

        // Ya termino, dentro del mismo dia.
        $ended = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $second->copy()->subMinutes(10)->toDateTimeString(),
            'valid_until' => $second->copy()->subSecond()->toDateTimeString(),
        ]);

        // Todavia no empieza, dentro del mismo dia.
        $future = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => $second->copy()->addSecond()->toDateTimeString(),
        ]);

        // La vista de la base y el ambito `effective` coinciden.
        $viewIds = DB::table('effective_property_unit_authorizations')->pluck('id')->all();
        $scopeIds = PropertyUnitAuthorization::effective($now)->pluck('id')->all();

        sort($viewIds);
        sort($scopeIds);

        $this->assertSame($scopeIds, $viewIds);

        // Y los limites se resuelven con instantes: la autorizacion que cubre
        // el instante esta vigente, la que empieza en el segundo exacto
        // tambien, la que termina en el segundo siguiente tambien, y la
        // terminada y la futura no.
        $this->assertContains($current->id, $viewIds);
        $this->assertContains($starting->id, $viewIds);
        $this->assertContains($ending->id, $viewIds);
        $this->assertNotContains($ended->id, $viewIds);
        $this->assertNotContains($future->id, $viewIds);

        $this->assertTrue($current->isEffectiveAt($now));
        $this->assertTrue($starting->isEffectiveAt($now));
        $this->assertTrue($ending->isEffectiveAt($now));
        $this->assertFalse($ended->isEffectiveAt($now));
        $this->assertFalse($future->isEffectiveAt($now));
    }

    // ==================== RN-P10: revocacion por arrendamiento ====================

    public function test_ending_a_lease_revokes_its_dependent_authorizations(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
        ]);

        $dependent = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $tenant->id,
            'lease_id' => $lease->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $independent = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $tenant->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $lease->update(['status' => LeaseStatus::ENDED]);

        $dependent->refresh();
        $independent->refresh();

        $this->assertSame(AuthorizationStatus::REVOKED, $dependent->status);
        $this->assertNotNull($dependent->revoked_at);
        $this->assertSame(AuthorizationStatus::ACTIVE, $independent->status);

        // La revocacion conserva el historial: la fila sigue existiendo.
        $this->assertDatabaseHas('property_unit_authorizations', [
            'id' => $dependent->id,
            'status' => 'revoked',
        ]);
    }

    // ==================== RN-P10: el arrendamiento de la autorizacion tiene que estar vigente ====================

    public function test_rejects_an_authorization_over_an_ended_lease(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'status' => LeaseStatus::ENDED,
        ]);

        try {
            // La transaccion anidada usa un savepoint: el error de la base no
            // aborta la transaccion de la prueba.
            DB::transaction(function () use ($unit, $tenant, $lease) {
                $this->authorization([
                    'property_unit_id' => $unit->id,
                    'person_id' => $tenant->id,
                    'lease_id' => $lease->id,
                    'valid_from' => now()->subDay()->toDateString(),
                ]);
            });

            $this->fail('Una autorizacion sobre un arrendamiento terminado deberia rechazarse.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('arrendamiento', $exception->getMessage());
        }
        $this->assertDatabaseCount('property_unit_authorizations', 0);
    }

    public function test_rejects_an_authorization_over_a_lease_that_already_expired(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'starts_on' => now()->subMonths(2)->toDateString(),
            'ends_on' => now()->subDay()->toDateString(),
        ]);

        try {
            DB::transaction(function () use ($unit, $tenant, $lease) {
                $this->authorization([
                    'property_unit_id' => $unit->id,
                    'person_id' => $tenant->id,
                    'lease_id' => $lease->id,
                    'valid_from' => now()->subDay()->toDateString(),
                ]);
            });

            $this->fail('Una autorizacion sobre un arrendamiento ya vencido deberia rechazarse.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('arrendamiento', $exception->getMessage());
        }

        $this->assertDatabaseCount('property_unit_authorizations', 0);
    }

    public function test_an_authorization_stops_granting_permissions_when_its_lease_expires(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ]);

        $dependent = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $tenant->id,
            'lease_id' => $lease->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($dependent->isEffectiveAt());
        $this->assertSame(1, PropertyUnitAuthorization::effective()->count());

        // El arrendamiento vence por fecha sin que nadie cierre su estado:
        // la autorizacion deja de conceder permisos aunque siga `active`.
        $lease->update(['ends_on' => now()->subDay()->toDateString()]);

        $dependent->refresh();

        $this->assertSame(AuthorizationStatus::ACTIVE, $dependent->status);
        $this->assertFalse($dependent->isEffectiveAt());
        $this->assertSame(0, PropertyUnitAuthorization::effective()->count());
        $this->assertSame(0, DB::table('effective_property_unit_authorizations')->count());
    }

    public function test_an_authorization_stays_effective_during_the_last_day_of_its_lease(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->toDateString(),
        ]);

        $dependent = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $tenant->id,
            'lease_id' => $lease->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        // El ultimo dia del arrendamiento sigue siendo un dia vigente: la
        // vista, el ambito `effective` y `isEffectiveAt` tienen que coincidir.
        $this->assertTrue($dependent->isEffectiveAt());
        $this->assertSame(1, PropertyUnitAuthorization::effective()->count());
        $this->assertSame(1, DB::table('effective_property_unit_authorizations')->count());
    }

    public function test_the_effectiveness_check_sees_a_revocation_done_after_loading(): void
    {
        $authorization = $this->authorization([
            'property_unit_id' => $this->unit()->id,
            'person_id' => $this->person()->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($authorization->isEffectiveAt());

        // La revocacion ocurre en otra transaccion: la instancia en memoria
        // sigue diciendo `active`.
        DB::table('property_unit_authorizations')
            ->where('id', $authorization->id)
            ->update(['status' => 'revoked', 'revoked_at' => now()]);

        $this->assertFalse($authorization->isEffectiveAt());
    }

    public function test_the_effectiveness_check_sees_a_lease_that_stopped_being_current(): void
    {
        $unit = $this->unit();
        $tenant = $this->person();

        $lease = UnitLease::factory()->create([
            'residential_complex_id' => $this->complex->id,
            'property_unit_id' => $unit->id,
            'tenant_person_id' => $tenant->id,
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ]);

        $authorization = $this->authorization([
            'property_unit_id' => $unit->id,
            'person_id' => $tenant->id,
            'lease_id' => $lease->id,
            'valid_from' => now()->subDay()->toDateString(),
        ]);

        // La relacion queda cacheada en la instancia.
        $authorization->load('lease');

        $this->assertTrue($authorization->isEffectiveAt());

        // El arrendamiento vence por fecha: la instance cacheada ya no vale.
        DB::table('unit_leases')
            ->where('id', $lease->id)
            ->update(['ends_on' => now()->subDay()->toDateString()]);

        $this->assertFalse($authorization->isEffectiveAt());
    }

    // ==================== Coherencia de la cuenta ====================

    public function test_rejects_an_account_that_does_not_belong_to_the_authorized_person(): void
    {
        $person = $this->person();
        $otherUser = User::factory()->create();

        $this->expectException(QueryException::class);

        $this->authorization([
            'person_id' => $person->id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_accepts_the_account_linked_to_the_authorized_person(): void
    {
        $user = User::factory()->create();
        $person = Person::factory()->for($this->complex)->create([
            'user_id' => $user->id,
        ]);

        $authorization = $this->authorization([
            'person_id' => $person->id,
            'user_id' => $user->id,
        ]);

        $this->assertEquals($user->id, $authorization->fresh()->user_id);
    }

    public function test_a_person_without_an_account_can_hold_an_authorization(): void
    {
        $person = $this->person();

        $this->assertNull($person->user_id);

        $authorization = $this->authorization([
            'person_id' => $person->id,
            'user_id' => null,
        ]);

        $this->assertNull($authorization->fresh()->user_id);
        $this->assertTrue($authorization->isEffectiveAt());
    }
}
