<?php

namespace Tests\Feature\Core;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Models\Person;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Atomicidad del documento de identidad (spec §4.2, RN-P02, T17).
 *
 * El documento de identidad es el PAR (`document_type`, `document_number`) y
 * es atomico: una persona tiene un documento completo (tipo y numero) o no
 * tiene documento. Tres capas lo garantizan:
 *
 * - el motor: el CHECK `people_identity_document_atomic` rechaza el INSERT
 *   con solo uno de los dos campos;
 * - la aplicacion: la validacion responde 422 (no un error de base de
 *   datos) en la API y en la web, en el alta y en la actualizacion;
 * - el dominio: RN-P02 sigue funcionando con el par completo, de modo que un
 *   alta con el mismo (tipo, numero) en el mismo conjunto reutiliza la ficha
 *   existente en vez de crear una segunda fila.
 *
 * Decision de dominio que este archivo deja fijada: el MISMO numero con
 * distinto tipo de documento es una identidad distinta (un `CC/12345678` y
 * un `CE/12345678` son dos personas), por eso el indice unico es parcial
 * POR PAR y no solo por numero, y por eso el CHECK exige el par atomico y no
 * la unicidad del numero suelto.
 */
class PersonIdentityAtomicityTest extends CoreTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Motor
    |--------------------------------------------------------------------------
    */

    public function test_the_atomicity_constraint_exists_in_the_engine(): void
    {
        $constraints = DB::select(
            "SELECT conname, pg_get_constraintdef(oid) AS definition
             FROM pg_constraint
             WHERE conrelid = 'people'::regclass
               AND conname = 'people_identity_document_atomic'"
        );

        $this->assertCount(1, $constraints, 'El CHECK de atomicidad debe existir en el motor.');
        $this->assertStringContainsString('document_number IS NULL', $constraints[0]->definition);
        $this->assertStringContainsString('document_type IS NULL', $constraints[0]->definition);
    }

    public function test_the_database_rejects_a_person_with_only_a_document_number(): void
    {
        $complex = $this->makeComplex('Torres');

        $exception = $this->captureDatabaseFailure(function () use ($complex): void {
            DB::table('people')->insert([
                'uuid' => (string) Str::uuid(),
                'residential_complex_id' => $complex->id,
                'full_name' => 'Ana Torres',
                'document_number' => '12345678',
                'status' => 'active',
            ]);
        });

        $this->assertStringContainsString(
            'people_identity_document_atomic',
            $exception->getMessage(),
            'El motor debe rechazar el documento a medias.'
        );
    }

    public function test_the_database_rejects_a_person_with_only_a_document_type(): void
    {
        $complex = $this->makeComplex('Torres');

        $exception = $this->captureDatabaseFailure(function () use ($complex): void {
            DB::table('people')->insert([
                'uuid' => (string) Str::uuid(),
                'residential_complex_id' => $complex->id,
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'status' => 'active',
            ]);
        });

        $this->assertStringContainsString(
            'people_identity_document_atomic',
            $exception->getMessage(),
            'El motor debe rechazar el documento a medias.'
        );
    }

    public function test_the_database_accepts_a_person_with_the_complete_document_pair(): void
    {
        $complex = $this->makeComplex('Torres');

        DB::table('people')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complex->id,
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('people', [
            'residential_complex_id' => $complex->id,
            'document_type' => 'CC',
            'document_number' => '12345678',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Aplicacion: alta
    |--------------------------------------------------------------------------
    */

    public function test_the_api_rejects_an_alta_with_only_a_document_number(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres',
                'document_number' => '12345678',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_type');

        $this->assertSame(
            $before,
            Person::withoutGlobalScope('complex')->count(),
            'El rechazo no debe crear ninguna persona.'
        );
    }

    public function test_the_api_rejects_an_alta_with_only_a_document_type(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_number');

        $this->assertSame(
            $before,
            Person::withoutGlobalScope('complex')->count(),
            'El rechazo no debe crear ninguna persona.'
        );
    }

    public function test_the_web_rejects_an_alta_with_only_a_document_number(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($actor)
            ->post('/people', [
                'full_name' => 'Ana Torres',
                'document_number' => '12345678',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_type');

        $this->assertSame(
            $before,
            Person::withoutGlobalScope('complex')->count(),
            'El rechazo no debe crear ninguna persona.'
        );
    }

    public function test_the_web_rejects_an_alta_with_only_a_document_type(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $before = Person::withoutGlobalScope('complex')->count();

        $this->actingAs($actor)
            ->post('/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_number');

        $this->assertSame(
            $before,
            Person::withoutGlobalScope('complex')->count(),
            'El rechazo no debe crear ninguna persona.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Aplicacion: actualizacion
    |--------------------------------------------------------------------------
    */

    public function test_the_api_rejects_an_update_that_would_leave_the_document_half_written(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
        ]);

        $this->actingAs($actor)
            ->putJson("/api/v1/people/{$person->uuid}", [
                'document_number' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_type');

        $fresh = $person->fresh();

        $this->assertSame('CC', $fresh->document_type?->value, 'El documento no debe quedar a medias.');
        $this->assertSame('12345678', $fresh->document_number);
    }

    public function test_the_api_rejects_an_update_that_would_leave_the_document_half_written_without_a_type(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => null,
            'document_number' => null,
        ]);

        $this->actingAs($actor)
            ->putJson("/api/v1/people/{$person->uuid}", [
                'document_number' => '12345678',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_type');

        $this->assertNull($person->fresh()->document_number);
    }

    public function test_the_web_rejects_an_update_that_would_leave_the_document_half_written(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
        ]);

        $this->actingAs($actor)
            ->put("/people/{$person->uuid}", [
                'full_name' => 'Ana Torres',
                'document_number' => null,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('document_type');

        $fresh = $person->fresh();

        $this->assertSame('CC', $fresh->document_type?->value, 'El documento no debe quedar a medias.');
        $this->assertSame('12345678', $fresh->document_number);
    }

    public function test_the_document_can_be_cleared_completely(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_EDIT,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $person = Person::factory()->forComplex($complex)->create([
            'full_name' => 'Ana Torres',
            'document_type' => 'CC',
            'document_number' => '12345678',
        ]);

        $this->actingAs($actor)
            ->putJson("/api/v1/people/{$person->uuid}", [
                'document_type' => null,
                'document_number' => null,
            ])
            ->assertOk();

        $fresh = $person->fresh();

        $this->assertNull($fresh->document_type);
        $this->assertNull($fresh->document_number);
    }

    /*
    |--------------------------------------------------------------------------
    | Reutilizacion por documento (RN-P02)
    |--------------------------------------------------------------------------
    */

    public function test_an_alta_with_the_same_document_reuses_the_existing_person(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.document_number', '12345678');

        $first = Person::withoutGlobalScope('complex')->sole();

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres otra vez',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.uuid', $first->uuid);

        $this->assertSame(
            1,
            Person::withoutGlobalScope('complex')->count(),
            'El alta con el mismo documento no debe crear una segunda fila.'
        );
    }

    public function test_the_web_alta_with_the_same_document_reuses_the_existing_person(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $this->actingAs($actor)
            ->post('/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertRedirect('/people');

        $first = Person::withoutGlobalScope('complex')->sole();

        $this->actingAs($actor)
            ->post('/people', [
                'full_name' => 'Ana Torres otra vez',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertRedirect("/people/{$first->uuid}");

        $this->assertSame(
            1,
            Person::withoutGlobalScope('complex')->count(),
            'El alta con el mismo documento no debe crear una segunda fila.'
        );
    }

    public function test_the_same_number_with_a_different_document_type_is_a_different_identity(): void
    {
        $complex = $this->makeComplex('Torres');
        [$actor] = $this->makeActor($complex, [
            ComplexPermission::PERSON_VIEW,
            ComplexPermission::PERSON_CREATE,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
        ]);

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CC',
                'document_number' => '12345678',
            ])
            ->assertStatus(201);

        $this->actingAs($actor)
            ->postJson('/api/v1/people', [
                'full_name' => 'Ana Torres',
                'document_type' => 'CE',
                'document_number' => '12345678',
            ])
            ->assertStatus(201);

        $this->assertSame(
            2,
            Person::withoutGlobalScope('complex')->count(),
            'El mismo numero con distinto tipo es una identidad distinta.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Ejecuta una escritura que el motor debe rechazar y devuelve el error.
     *
     * La escritura va dentro de una transaccion (un savepoint sobre la del
     * test) para que el fallo del motor no deje abortada la transaccion
     * externa y las aserciones posteriores puedan ejecutarse.
     */
    protected function captureDatabaseFailure(callable $write): QueryException
    {
        try {
            DB::transaction(function () use ($write): void {
                $write();
            });
        } catch (QueryException $exception) {
            return $exception;
        }

        $this->fail('El motor deberia haber rechazado la escritura.');
    }

    /**
     * Miembro activo con un rol propio del conjunto que tiene exactamente los
     * permisos indicados.
     *
     * @param  list<ComplexPermission>  $permissions
     * @return array{0: User, 1: ResidentialComplexMembership}
     */
    protected function makeActor(ResidentialComplex $complex, array $permissions): array
    {
        $user = User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::create([
            'residential_complex_id' => $complex->id,
            'key' => 'prueba-'.uniqid(),
            'name' => 'Rol de prueba '.uniqid(),
            'is_system' => false,
            'is_editable' => true,
        ]);

        foreach ($permissions as $permission) {
            $role->grantPermission($permission);
        }

        $membership->roles()->attach($role->id);

        return [$user, $membership];
    }
}
