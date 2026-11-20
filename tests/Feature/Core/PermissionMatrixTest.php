<?php

namespace Tests\Feature\Core;

use App\Enums\OccupancyStatus;
use App\Enums\OccupancyType;
use App\Enums\OwnershipType;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\UnitOccupancy;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Matriz de permisos de §1.3 del spec (specs S69-S72).
 *
 * La matriz era fiction: seis celdas no tenian respaldo en el seeding y
 * `platform_admin` tenia permisos de mas. Este archivo la vuelve verificable.
 *
 * - El contrato (S71): para cada rol de sistema, el conjunto **exacto** de
 *   permisos sembrados se compara contra la matriz de §1.3; ni un permiso de
 *   mas ni de menos, para que cualquier deriva futura rompa la suite en vez
 *   de quedar escondida en el seeding.
 * - La superficie (S72): las celdas que ya tienen superficie HTTP se
 *   comprueban ademas de forma funcional (estructura fisica, unidades y
 *   alcance por inmueble) y se comprueba la no-regresion de la plataforma.
 *
 * Las celdas de ocupantes (`occupancy.manage`) y de empleados de la unidad
 * (`employment.manage_unit`) no tienen todavia superficie HTTP: quedan
 * declaradas por el contrato de la matriz hasta que exista (work unit T23).
 */
class PermissionMatrixTest extends CoreTestCase
{
    /**
     * El conjunto sembrado de cada rol de sistema es exactamente el de la
     * matriz de §1.3.
     */
    #[DataProvider('matrixProvider')]
    public function test_the_seeded_permissions_of_the_role_are_exactly_the_ones_of_the_matrix(
        string $roleKey,
        array $expectedPermissions
    ): void {
        $role = Role::where('key', $roleKey)->firstOrFail();

        $seeded = $role->permissions()->pluck('permission_key')->all();

        $this->assertSame(
            $this->sorted($expectedPermissions),
            $this->sorted($seeded),
            "El conjunto de permisos sembrado de `{$roleKey}` no coincide con la matriz de §1.3."
        );
    }

    /**
     * La matriz de §1.3 del spec, traducida a claves del catalogo.
     *
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    public static function matrixProvider(): array
    {
        return [
            // Plataforma: crea y suspende conjuntos, consulta la auditoria.
            // No administra ni la configuracion ni los miembros del conjunto.
            'platform_admin' => [SystemRole::PLATFORM_ADMIN->value, [
                'complex.create',
                'complex.view',
                'complex.view_audit',
                'building.view',
                'building.create',
                'building.edit',
                'building.delete',
                'floor.view',
                'floor.create',
                'floor.edit',
                'floor.delete',
                'unit.view',
                'unit.create',
                'unit.edit',
                'unit.delete',
                'person.view',
                'person.view_document',
                'person.create',
                'person.edit',
                'person.delete',
                'ownership.view',
                'ownership.manage',
                'occupancy.view',
                'occupancy.manage',
                'employment.view',
                'employment.manage_complex',
                'employment.manage_unit',
                'stay.view',
                'stay.create',
                'stay.manage',
                'stay.approve',
                'stay.checkin',
                'access.register',
                'access.view',
                'parking.manage',
                'parking.assign',
            ]],

            // Admin del conjunto: todo el conjunto, incluida su configuracion.
            'complex_admin' => [SystemRole::COMPLEX_ADMIN->value, [
                'complex.view',
                'complex.edit',
                'complex.manage_members',
                'complex.manage_settings',
                'complex.view_audit',
                'building.view',
                'building.create',
                'building.edit',
                'building.delete',
                'floor.view',
                'floor.create',
                'floor.edit',
                'floor.delete',
                'unit.view',
                'unit.create',
                'unit.edit',
                'unit.delete',
                'person.view',
                'person.view_document',
                'person.create',
                'person.edit',
                'person.delete',
                'ownership.view',
                'ownership.manage',
                'occupancy.view',
                'occupancy.manage',
                'employment.view',
                'employment.manage_complex',
                'employment.manage_unit',
                'stay.view',
                'stay.approve',
                'access.register',
                'access.view',
                'parking.manage',
                'parking.assign',
            ]],

            // Porteria: valida y registra accesos, con datos minimos de
            // personas, y consulta la estructura fisica de su conjunto.
            'doorman' => [SystemRole::DOORMAN->value, [
                'complex.view',
                'person.view_minimal',
                'access.register',
                'access.view',
                'stay.checkin',
                'building.view',
                'floor.view',
                'unit.view',
            ]],

            // Propietario: sus inmuebles, con datos limitados.
            'owner' => [SystemRole::OWNER->value, [
                'complex.view',
                'building.view',
                'floor.view',
                'unit.view',
                'unit.manage',
                'person.view',
                'occupancy.view',
                'occupancy.manage',
                'employment.view',
                'employment.manage_unit',
                'stay.view',
                'stay.create',
                'stay.manage',
                'access.view',
            ]],

            // Copropietario: sus inmuebles, segun sus derechos.
            'co_owner' => [SystemRole::CO_OWNER->value, [
                'complex.view',
                'building.view',
                'floor.view',
                'unit.view',
                'unit.manage',
                'person.view',
                'occupancy.view',
                'access.view',
            ]],

            // Residente: su unidad y su nucleo.
            'resident' => [SystemRole::RESIDENT->value, [
                'complex.view',
                'building.view',
                'floor.view',
                'unit.view',
                'person.view',
                'occupancy.view',
                'occupancy.manage',
                'employment.view',
                'employment.manage_unit',
                'access.view',
            ]],

            // Arrendatario: su unidad, segun contrato y delegacion.
            'tenant' => [SystemRole::TENANT->value, [
                'complex.view',
                'building.view',
                'floor.view',
                'unit.view',
                'person.view',
                'occupancy.view',
                'occupancy.manage',
                'employment.view',
                'employment.manage_unit',
                'stay.view',
                'stay.create',
                'stay.manage',
                'access.view',
            ]],

            // Anfitrion: inmuebles autorizados, en el modulo de rentas cortas.
            'host' => [SystemRole::HOST->value, [
                'complex.view',
                'unit.view',
                'stay.view',
                'stay.create',
                'stay.manage',
                'stay.checkin',
                'access.view',
            ]],
        ];
    }

    // ------------------------------------------------------- superficie (S72)

    /**
     * "Consultar estructura fisica: P" (porteria).
     *
     * La celda tiene respaldo: el portero consulta la estructura de su
     * conjunto (200 en vez de 403). El alcance es el de la matriz, "P":
     * solo los recursos que le alcanzan, no las unidades de los demas.
     */
    public function test_the_doorman_consults_the_physical_structure_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$doorman] = $this->makeMember($complex, SystemRole::DOORMAN);

        $assigned = $this->makeUnit($complex, 'A-101');
        $unassigned = $this->makeUnit($complex, 'A-102');

        // El portero que reside en el conjunto tiene una unidad asignada.
        UnitOccupancy::factory()
            ->forComplex($complex)
            ->create([
                'property_unit_id' => $assigned->id,
                'person_id' => $this->makePerson($complex, $doorman, 'Portero')->id,
                'occupancy_type' => OccupancyType::OTHER_RESIDENT,
                'status' => OccupancyStatus::ACTIVE,
            ]);

        $response = $this->actingAs($doorman)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($assigned->uuid));
        $this->assertFalse($uuids->contains($unassigned->uuid));
    }

    /**
     * "Segun sus derechos": el copropietario administra sus inmuebles.
     *
     * La unidad con titularidad vigente le alcanza y la edita; la unidad de
     * otro propietario no le alcanza y se rechaza con 403.
     */
    public function test_the_co_owner_manages_their_own_unit_and_not_the_ones_of_others(): void
    {
        $complex = $this->makeComplex('Torres');

        [$coOwner] = $this->makeMember($complex, SystemRole::CO_OWNER);
        [$otherOwner] = $this->makeMember($complex, SystemRole::OWNER);

        $ownUnit = $this->makeUnit($complex, 'A-101');
        $otherUnit = $this->makeUnit($complex, 'A-102');

        $this->giveOwnership($complex, $ownUnit, $this->makePerson($complex, $coOwner, 'Copropietario'), [
            'ownership_type' => OwnershipType::CO_OWNER,
        ]);
        $this->giveOwnership($complex, $otherUnit, $this->makePerson($complex, $otherOwner, 'Titular'));

        $this->actingAs($coOwner)
            ->patchJson("/api/v1/core/property-units/{$ownUnit->uuid}", ['notes' => 'Reparacion aprovada'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Reparacion aprovada');

        $this->assertSame('Reparacion aprovada', $ownUnit->fresh()->notes);

        $this->actingAs($coOwner)
            ->patchJson("/api/v1/core/property-units/{$otherUnit->uuid}", ['notes' => 'invasion'])
            ->assertForbidden();

        $this->assertNull($otherUnit->fresh()->notes);
    }

    /**
     * No-regresion de la plataforma (S70).
     *
     * Quitarle del seeding `complex.edit`, `complex.manage_settings` y
     * `complex.manage_members` no le quita poder operativo: su alcance
     * global lo dan las policies (`isPlatformAdmin()`), no el permiso
     * sembrado. La plataforma sigue administrando conjuntos de terceros y
     * sigue creando conjuntos (`complex.create` si se conserva).
     */
    public function test_the_platform_admin_keeps_its_operational_power_without_the_seeded_permissions(): void
    {
        $own = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$platform] = $this->makeMember($own, SystemRole::PLATFORM_ADMIN);

        // La plataforma administra un conjunto del que no es miembro.
        $this->actingAs($platform)
            ->patchJson("/api/v1/buildings/{$other->uuid}", ['legal_identifier' => 'NIT-PLATAFORMA'])
            ->assertOk();

        $this->assertSame('NIT-PLATAFORMA', $other->fresh()->legal_identifier);

        // Y sigue creando conjuntos.
        $this->actingAs($platform)
            ->postJson('/api/v1/buildings', [
                'name' => 'Conjunto Nuevo',
                'code' => 'conjunto-nuevo-'.uniqid(),
                'address' => 'Calle 1 # 2-3',
                'city' => 'Bogota',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('residential_complexes', ['name' => 'Conjunto Nuevo']);
    }

    /**
     * No-regresion de la plataforma (S70) por la puerta canonica de las
     * politicas.
     *
     * S70 promete que el alcance global de la plataforma no cambia: su
     * poder viene de `isPlatformAdmin()`, no del permiso sembrado. La
     * escritura de las politicas por la puerta canonica del conjunto
     * (`/api/v1/core/complex/settings`) debe seguir disponible para la
     * plataforma, igual que para el administrador del conjunto.
     */
    public function test_the_platform_admin_keeps_the_canonical_door_of_the_complex_policies(): void
    {
        $complex = $this->makeComplex('Torres');

        [$platform] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($platform)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'America/Bogota'],
            ])
            ->assertOk();

        $this->assertSame(
            'America/Bogota',
            $complex->fresh()->settings['regional']['timezone']
        );
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function sorted(array $keys): array
    {
        sort($keys, SORT_STRING);

        return array_values($keys);
    }
}
