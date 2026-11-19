<?php

namespace Tests\Feature\Core;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\Person;
use App\Models\PropertyUnit;
use App\Models\PropertyUnitOwnership;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\ComplexContext;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Base de la suite transversal de la seccion 6 del spec.
 *
 * El proyecto es PostgreSQL y la suite corre contra la base de pruebas
 * dedicada (z360_test), nunca contra la de desarrollo.
 */
abstract class CoreTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ComplexContext::clear();

        $this->seed(SystemRoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        ComplexContext::clear();

        parent::tearDown();
    }

    protected function makeComplex(string $name): ResidentialComplex
    {
        return ResidentialComplex::factory()->create([
            'name' => $name,
            'code' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
        ]);
    }

    /**
     * Miembro activo con un rol del catalogo de sistema.
     *
     * @return array{0: User, 1: ResidentialComplexMembership}
     */
    protected function makeMember(ResidentialComplex $complex, SystemRole $role, ?User $user = null): array
    {
        $user ??= User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $roleModel = Role::where('key', $role->value)->first();

        $membership->roles()->attach($roleModel->id);

        return [$user, $membership];
    }

    protected function makePerson(ResidentialComplex $complex, ?User $user = null, ?string $name = null): Person
    {
        return Person::factory()->forComplex($complex)->create([
            'user_id' => $user?->id,
            'full_name' => $name ?? fake()->name(),
        ]);
    }

    protected function makeUnit(ResidentialComplex $complex, ?string $number = null): PropertyUnit
    {
        return PropertyUnit::factory()->create([
            'residential_complex_id' => $complex->id,
            'number' => $number ?? strtoupper(fake()->unique()->bothify('U-####')),
        ]);
    }

    /**
     * Titularidad vigente de una unidad.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function giveOwnership(
        ResidentialComplex $complex,
        PropertyUnit $unit,
        Person $person,
        array $attributes = []
    ): PropertyUnitOwnership {
        return PropertyUnitOwnership::factory()->create(array_merge([
            'residential_complex_id' => $complex->id,
            'property_unit_id' => $unit->id,
            'person_id' => $person->id,
        ], $attributes));
    }
}
