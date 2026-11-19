<?php

namespace Tests\Feature\Core;

use App\Enums\AuthorizationType;
use App\Enums\OccupancyStatus;
use App\Enums\SystemRole;
use App\Models\PropertyUnitAuthorization;
use App\Models\UnitOccupancy;
use Illuminate\Support\Carbon;

/**
 * Alcance de propiedad (spec 6 "Alcance": "Propietario solo gestiona sus
 * inmuebles, no los de otros"; SEG-C02).
 */
class OwnerScopeTest extends CoreTestCase
{
    public function test_an_owner_sees_only_the_units_they_own(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);
        [$otherOwner] = $this->makeMember($complex, SystemRole::OWNER);

        $ownUnit = $this->makeUnit($complex, 'A-101');
        $otherUnit = $this->makeUnit($complex, 'A-102');

        $this->giveOwnership($complex, $ownUnit, $this->makePerson($complex, $owner, 'Titular Uno'));
        $this->giveOwnership($complex, $otherUnit, $this->makePerson($complex, $otherOwner, 'Titular Dos'));

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($ownUnit->uuid));
        $this->assertFalse($uuids->contains($otherUnit->uuid));
    }

    public function test_an_owner_can_update_the_notes_of_their_own_unit(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $unit = $this->makeUnit($complex, 'A-101');
        $this->giveOwnership($complex, $unit, $this->makePerson($complex, $owner, 'Titular'));

        $this->actingAs($owner)
            ->patchJson("/api/v1/core/property-units/{$unit->uuid}", ['notes' => 'Pendiente de pintura'])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Pendiente de pintura');

        $this->assertSame('Pendiente de pintura', $unit->fresh()->notes);
    }

    public function test_an_owner_cannot_update_a_unit_owned_by_someone_else(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);
        [$otherOwner] = $this->makeMember($complex, SystemRole::OWNER);

        $otherUnit = $this->makeUnit($complex, 'A-102');
        $this->giveOwnership($complex, $otherUnit, $this->makePerson($complex, $otherOwner, 'Titular Dos'));

        $this->actingAs($owner)
            ->patchJson("/api/v1/core/property-units/{$otherUnit->uuid}", ['notes' => 'invasion'])
            ->assertForbidden();

        $this->assertNull($otherUnit->fresh()->notes);
    }

    public function test_an_owner_cannot_modify_the_fields_reserved_to_the_admin(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $unit = $this->makeUnit($complex, 'A-101');
        $this->giveOwnership($complex, $unit, $this->makePerson($complex, $owner, 'Titular'));

        $this->actingAs($owner)
            ->patchJson("/api/v1/core/property-units/{$unit->uuid}", ['status' => 'inactive'])
            ->assertStatus(422);

        $this->assertSame('active', $unit->fresh()->status->value);
    }

    public function test_the_complex_admin_manages_every_unit_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $firstUnit = $this->makeUnit($complex, 'A-101');
        $secondUnit = $this->makeUnit($complex, 'A-102');

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $uuids = collect($response->json('data'))->pluck('uuid');

        $this->assertTrue($uuids->contains($firstUnit->uuid));
        $this->assertTrue($uuids->contains($secondUnit->uuid));
    }

    public function test_an_owner_with_an_effective_authorization_manages_that_unit(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);
        [$otherOwner] = $this->makeMember($complex, SystemRole::OWNER);

        $otherUnit = $this->makeUnit($complex, 'A-102');
        $otherPerson = $this->makePerson($complex, $otherOwner, 'Titular Dos');
        $this->giveOwnership($complex, $otherUnit, $otherPerson);

        // La autorizacion delega la gestion en una persona con cuenta propia
        // (T15: la cuenta y la persona autorizada son la misma).
        $delegate = $this->makeMember($complex, SystemRole::OWNER)[0];
        $delegatePerson = $this->makePerson($complex, $delegate, 'Delegado');

        PropertyUnitAuthorization::factory()
            ->forComplex($complex)
            ->forUnit($otherUnit)
            ->forPerson($delegatePerson)
            ->create([
                'user_id' => $delegate->id,
                'authorization_type' => AuthorizationType::MANAGER,
                'scope' => ['unit.manage'],
            ]);

        $this->actingAs($delegate)
            ->patchJson("/api/v1/core/property-units/{$otherUnit->uuid}", ['notes' => 'Con autorizacion'])
            ->assertOk();

        $this->assertSame('Con autorizacion', $otherUnit->fresh()->notes);
    }

    public function test_an_owner_with_a_revoked_authorization_does_not_manage_that_unit(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);
        [$otherOwner] = $this->makeMember($complex, SystemRole::OWNER);

        $otherUnit = $this->makeUnit($complex, 'A-102');
        $otherPerson = $this->makePerson($complex, $otherOwner, 'Titular Dos');
        $this->giveOwnership($complex, $otherUnit, $otherPerson);

        $delegate = $this->makeMember($complex, SystemRole::OWNER)[0];
        $delegatePerson = $this->makePerson($complex, $delegate, 'Delegado');

        PropertyUnitAuthorization::factory()
            ->forComplex($complex)
            ->forUnit($otherUnit)
            ->forPerson($delegatePerson)
            ->revoked()
            ->create([
                'user_id' => $delegate->id,
                'authorization_type' => AuthorizationType::MANAGER,
                'scope' => ['unit.manage'],
            ]);

        $this->actingAs($delegate)
            ->patchJson("/api/v1/core/property-units/{$otherUnit->uuid}", ['notes' => 'Sin autorizacion'])
            ->assertForbidden();

        $this->assertNull($otherUnit->fresh()->notes);
    }

    public function test_an_ownership_that_ends_today_is_still_within_the_scope(): void
    {
        // Momento congelado a mediodia: el ultimo dia de una titularidad
        // vigente cuenta, sea cual sea la hora del reloj.
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $unit = $this->makeUnit($complex, 'A-101');
        $this->giveOwnership($complex, $unit, $this->makePerson($complex, $owner, 'Titular'), [
            'valid_from' => now()->subDay()->toDateString(),
            'valid_until' => now()->toDateString(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $this->assertTrue(collect($response->json('data'))->pluck('uuid')->contains($unit->uuid));
    }

    public function test_an_ownership_that_ended_yesterday_is_not_within_the_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $unit = $this->makeUnit($complex, 'A-101');
        $this->giveOwnership($complex, $unit, $this->makePerson($complex, $owner, 'Titular'), [
            'valid_from' => now()->subDays(2)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $this->assertFalse(collect($response->json('data'))->pluck('uuid')->contains($unit->uuid));
    }

    public function test_an_occupancy_that_starts_tomorrow_is_not_within_the_scope(): void
    {
        // Una ocupacion vigente a partir de manana no es una ocupacion
        // vigente hoy: no puede dar alcance sobre la unidad.
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00', 'UTC'));

        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $unit = $this->makeUnit($complex, 'A-101');

        UnitOccupancy::factory()
            ->forComplex($complex)
            ->create([
                'property_unit_id' => $unit->id,
                'person_id' => $this->makePerson($complex, $resident, 'Residente')->id,
                'valid_from' => now()->addDay()->toDateString(),
                'status' => OccupancyStatus::ACTIVE,
            ]);

        $response = $this->actingAs($resident)
            ->getJson('/api/v1/core/property-units')
            ->assertOk();

        $this->assertFalse(collect($response->json('data'))->pluck('uuid')->contains($unit->uuid));
    }

    public function test_a_resident_without_the_manage_permission_cannot_update_the_unit(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $unit = $this->makeUnit($complex, 'A-101');
        $this->giveOwnership($complex, $unit, $this->makePerson($complex, $resident, 'Residente'));

        $this->actingAs($resident)
            ->patchJson("/api/v1/core/property-units/{$unit->uuid}", ['notes' => 'no permitido'])
            ->assertForbidden();
    }
}
