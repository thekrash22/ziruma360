<?php

namespace Tests\Feature\Core;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Enums\ResidentialComplexStatus;
use App\Enums\SystemRole;
use App\Models\MembershipRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bootstrap del sistema por consola (spec S91).
 *
 * `z360:create-complex` es el unico camino al primer conjunto: crea, en una
 * sola transaccion, el conjunto en borrador, el usuario administrador, la
 * membresia activa y la asignacion vigente del rol de sistema
 * `platform_admin` (el unico con `complex.create`).
 *
 * No se hereda de `CoreTestCase` a proposito: esa base siembra los roles del
 * sistema, y aqui hay que comprobar que el comando los siembra cuando
 * faltan.
 */
class ComplexBootstrapCommandTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Alta del conjunto
    |--------------------------------------------------------------------------
    */

    public function test_the_command_creates_the_complex_the_admin_the_membership_and_the_platform_role(): void
    {
        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
            '--admin-name' => 'Administrador Uno',
            '--admin-password' => 'secreto-1234',
        ])
            ->expectsOutputToContain('conjunto-uno')
            ->expectsOutputToContain('admin@ejemplo.test')
            ->assertExitCode(0);

        $complex = ResidentialComplex::where('code', 'conjunto-uno')->first();

        $this->assertNotNull($complex, 'El comando no creo el conjunto.');
        $this->assertSame('Conjunto Uno', $complex->name);
        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->status);
        $this->assertNotNull($complex->uuid);

        $user = User::where('email', 'admin@ejemplo.test')->first();

        $this->assertNotNull($user, 'El comando no creo el usuario administrador.');
        $this->assertSame('Administrador Uno', $user->name);

        $membership = ResidentialComplexMembership::where('residential_complex_id', $complex->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership, 'El comando no creo la membresia.');
        $this->assertSame(MembershipStatus::ACTIVE, $membership->status);
        $this->assertNotNull($membership->joined_at);

        $platformRole = Role::whereNull('residential_complex_id')
            ->where('key', SystemRole::PLATFORM_ADMIN->value)
            ->first();

        $this->assertNotNull($platformRole, 'El comando no sembro el rol de plataforma.');

        $assignment = MembershipRole::where('membership_id', $membership->id)
            ->where('role_id', $platformRole->id)
            ->first();

        $this->assertNotNull($assignment, 'La membresia no tiene el rol de plataforma.');
        $this->assertTrue($assignment->isValid(), 'La asignacion del rol no esta vigente.');
        $this->assertNull($assignment->valid_until);

        // El administrador queda siendo plataforma: puede crear conjuntos.
        $this->assertTrue($user->fresh()->isPlatformAdmin());
    }

    public function test_the_command_seeds_the_system_roles_when_they_are_missing(): void
    {
        $this->assertDatabaseMissing('roles', ['key' => SystemRole::PLATFORM_ADMIN->value]);

        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
            '--admin-password' => 'secreto-1234',
        ])
            ->assertExitCode(0);

        $this->assertDatabaseHas('roles', [
            'key' => SystemRole::PLATFORM_ADMIN->value,
            'residential_complex_id' => null,
            'is_system' => true,
        ]);

        $role = Role::whereNull('residential_complex_id')
            ->where('key', SystemRole::PLATFORM_ADMIN->value)
            ->firstOrFail();

        $this->assertTrue(
            $role->hasPermission(ComplexPermission::COMPLEX_CREATE),
            'El rol de plataforma no tiene el permiso de alta de conjuntos.'
        );
    }

    public function test_the_command_does_not_repeat_the_modules_of_the_complex(): void
    {
        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
            '--admin-password' => 'secreto-1234',
        ])
            ->assertExitCode(0);

        $complex = ResidentialComplex::where('code', 'conjunto-uno')->firstOrFail();

        // Los modulos los siembra el observer del conjunto, no el comando.
        $this->assertDatabaseHas('residential_complex_modules', [
            'residential_complex_id' => $complex->id,
            'module_key' => 'core.structure',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotencia por codigo
    |--------------------------------------------------------------------------
    */

    public function test_the_command_fails_when_the_code_already_exists(): void
    {
        $existing = ResidentialComplex::factory()->create([
            'code' => 'conjunto-uno',
            'name' => 'Conjunto Existente',
        ]);

        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
            '--admin-password' => 'secreto-1234',
        ])
            ->assertExitCode(1);

        $this->assertSame(1, ResidentialComplex::where('code', 'conjunto-uno')->count());
        $this->assertSame('Conjunto Existente', $existing->fresh()->name);
        $this->assertDatabaseMissing('users', ['email' => 'admin@ejemplo.test']);
        $this->assertSame(0, ResidentialComplexMembership::count());
    }

    public function test_the_command_is_idempotent_by_code(): void
    {
        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
            '--admin-password' => 'secreto-1234',
        ])
            ->assertExitCode(0);

        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
            '--admin-password' => 'secreto-1234',
        ])
            ->assertExitCode(1);

        $this->assertSame(1, ResidentialComplex::where('code', 'conjunto-uno')->count());
        $this->assertSame(1, User::where('email', 'admin@ejemplo.test')->count());
        $this->assertSame(1, ResidentialComplexMembership::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Cuenta del administrador
    |--------------------------------------------------------------------------
    */

    public function test_the_command_reuses_an_existing_account(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@ejemplo.test',
            'name' => 'Nombre Original',
        ]);

        // La contrasena solo es obligatoria si la cuenta no existe todavia.
        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
        ])
            ->assertExitCode(0);

        $this->assertSame(1, User::where('email', 'admin@ejemplo.test')->count());

        $complex = ResidentialComplex::where('code', 'conjunto-uno')->firstOrFail();

        $membership = ResidentialComplexMembership::where('residential_complex_id', $complex->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($membership, 'La membresia no apunta a la cuenta existente.');
        $this->assertSame('Nombre Original', $user->fresh()->name);
        $this->assertTrue($user->fresh()->isPlatformAdmin());
    }

    public function test_the_command_fails_without_a_password_for_a_new_account(): void
    {
        $this->artisan('z360:create-complex', [
            '--name' => 'Conjunto Uno',
            '--code' => 'conjunto-uno',
            '--admin-email' => 'admin@ejemplo.test',
        ])
            ->assertExitCode(1);

        $this->assertDatabaseMissing('residential_complexes', ['code' => 'conjunto-uno']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@ejemplo.test']);
    }

    public function test_the_command_fails_without_the_mandatory_options(): void
    {
        $this->artisan('z360:create-complex')
            ->assertExitCode(1);

        $this->assertSame(0, ResidentialComplex::count());
    }
}
