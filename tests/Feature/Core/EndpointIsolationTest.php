<?php

namespace Tests\Feature\Core;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\User;
use App\Services\ActiveComplexResolver;

/**
 * Aislamiento a nivel de endpoint (spec 6 "Aislamiento", ACE-C01, S9).
 *
 * El conjunto activo se resuelve en el servidor contra las membresias
 * activas del actor y un recurso de otro conjunto no se resuelve.
 */
class EndpointIsolationTest extends CoreTestCase
{
    public function test_a_member_of_complex_a_cannot_read_a_unit_of_complex_b_by_id(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unitB = $this->makeUnit($complexB);

        $this->actingAs($userA)
            ->getJson("/api/v1/core/property-units/{$unitB->uuid}")
            ->assertNotFound();
    }

    public function test_a_member_of_complex_a_cannot_update_a_unit_of_complex_b_by_id(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unitB = $this->makeUnit($complexB);

        $this->actingAs($userA)
            ->patchJson("/api/v1/core/property-units/{$unitB->uuid}", ['notes' => 'invasion'])
            ->assertNotFound();

        $this->assertNull($unitB->fresh()->notes);
    }

    public function test_a_member_of_complex_a_cannot_read_a_person_of_complex_b_by_id(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $personB = $this->makePerson($complexB);

        $this->actingAs($userA)
            ->getJson("/api/v1/core/people/{$personB->uuid}")
            ->assertNotFound();
    }

    public function test_a_requested_complex_that_is_not_the_users_is_rejected(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($userA)
            ->getJson('/api/v1/core/property-units', [
                ActiveComplexResolver::HEADER => $complexB->id,
            ])
            ->assertForbidden();
    }

    public function test_a_requested_complex_that_is_not_a_valid_identifier_is_rejected(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($userA)
            ->getJson('/api/v1/core/property-units', [
                ActiveComplexResolver::HEADER => 'no-es-un-id',
            ])
            ->assertForbidden();
    }

    public function test_a_user_without_an_active_membership_has_no_access(): void
    {
        $this->makeComplex('Complejo A');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();
    }

    public function test_a_suspended_membership_loses_access_immediately(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA, $membership] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $membership->update(['status' => MembershipStatus::SUSPENDED]);

        $this->actingAs($userA)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();
    }

    public function test_a_revoked_membership_loses_access_immediately(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA, $membership] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $membership->update(['status' => MembershipStatus::REVOKED]);

        $this->actingAs($userA)
            ->getJson('/api/v1/core/property-units')
            ->assertForbidden();
    }

    public function test_a_user_with_memberships_in_two_complexes_must_indicate_which_one(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);
        $this->makeMember($complexB, SystemRole::COMPLEX_ADMIN, $userA);

        $this->actingAs($userA)
            ->getJson('/api/v1/core/property-units')
            ->assertStatus(409);
    }

    public function test_the_active_complex_is_resolved_from_the_membership(): void
    {
        $complexA = $this->makeComplex('Complejo A');

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complexA);

        $this->actingAs($userA)
            ->getJson('/api/v1/core/property-units')
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $unit->uuid);
    }

    public function test_an_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/core/property-units')->assertUnauthorized();
    }
}
