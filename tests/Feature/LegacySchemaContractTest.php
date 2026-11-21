<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Core\CoreTestCase;

/**
 * Contrato vigente tras el retiro del modelo legacy (spec S40, T17 unidad C).
 *
 * El legacy (`unidades`, `personas`, `persona_unidad`) se retiro del esquema
 * y del codigo: las tres tablas ya no existen, sus modelos y el enum
 * `UnidadStatus` ya no existen, y las rutas `/units` y `/people` se sirven
 * sobre el dominio nuevo (`property_units`, `people`) con los permisos del
 * catalogo (S35/S38).
 *
 * Las afirmaciones son sobre la interfaz publica: codigo de estado, props de
 * Inertia y el estado del esquema, nunca sobre el estado interno.
 */
class LegacySchemaContractTest extends CoreTestCase
{
    /*
    |--------------------------------------------------------------------------
    | El esquema legacy ya no existe
    |--------------------------------------------------------------------------
    */

    #[DataProvider('legacyTables')]
    public function test_the_legacy_table_no_longer_exists_in_the_schema(string $table): void
    {
        $row = DB::selectOne(
            'select exists (
                select 1
                from information_schema.tables
                where table_schema = current_schema()
                  and table_name = ?
            ) as present',
            [$table]
        );

        $this->assertFalse(
            (bool) $row->present,
            "La tabla legacy {$table} ya no debe existir en el esquema."
        );
    }

    public static function legacyTables(): array
    {
        return [
            'unidades' => ['unidades'],
            'personas' => ['personas'],
            'persona_unidad' => ['persona_unidad'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | El codigo legacy ya no existe
    |--------------------------------------------------------------------------
    */

    #[DataProvider('legacyClasses')]
    public function test_the_legacy_class_no_longer_exists(string $class): void
    {
        $this->assertFalse(
            class_exists($class),
            "La clase legacy {$class} ya no debe existir."
        );
    }

    #[DataProvider('legacyClasses')]
    public function test_the_legacy_enum_no_longer_exists(string $class): void
    {
        $this->assertFalse(
            enum_exists($class),
            "El enum legacy {$class} ya no debe existir."
        );
    }

    public static function legacyClasses(): array
    {
        // Se referencian por nombre y no con `::class` a proposito: estas clases
        // no deben existir, asi que importarlas seria una contradiccion.
        return [
            'Unidad' => ['App\Models\Unidad'],
            'Persona' => ['App\Models\Persona'],
            'PersonaUnidad' => ['App\Models\PersonaUnidad'],
            'UnidadStatus' => ['App\Enums\UnidadStatus'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Las rutas web sirven el dominio nuevo
    |--------------------------------------------------------------------------
    */

    public function test_the_units_page_answers_with_the_new_domain_for_an_actor_with_permissions(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->get('/units');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Units/Index')
        );
    }

    public function test_the_people_page_answers_with_the_new_domain_for_an_actor_with_permissions(): void
    {
        $complex = $this->makeComplex('Torres');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)->get('/people');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('People/Index')
        );
    }

    public function test_the_units_page_is_forbidden_for_an_actor_without_permissions(): void
    {
        $complex = $this->makeComplex('Torres');
        // Desde la matriz de §1.3 (S69) todos los roles del catalogo de
        // sistema tienen permiso de unidades: el actor sin permisos es un
        // miembro con un rol propio sin permisos sembrados.
        [$user, $role] = $this->makeMemberWithoutUnitPermissions($complex);

        // El fixture tiene que ser genuinamente sin permisos: si una siembra
        // futura le concediera alguno, el test debe fallar de forma clara.
        $this->assertSame(0, $role->permissions()->count());

        $this->actingAs($user)
            ->get('/units')
            ->assertForbidden();
    }

    public function test_the_people_page_is_forbidden_for_an_actor_without_permissions(): void
    {
        $complex = $this->makeComplex('Torres');
        // El anfitrion no tiene permiso de personas (ni completo ni minimo).
        [$host] = $this->makeMember($complex, SystemRole::HOST);

        $this->actingAs($host)
            ->get('/people')
            ->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Miembro activo con un rol propio sin permisos sembrados.
     *
     * @return array{0: User, 1: Role}
     */
    private function makeMemberWithoutUnitPermissions(ResidentialComplex $complex): array
    {
        $user = User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::factory()->complexLevel($complex)->create();

        $membership->roles()->attach($role->id);

        return [$user, $role];
    }
}
