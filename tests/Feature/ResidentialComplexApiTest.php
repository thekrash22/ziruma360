<?php

namespace Tests\Feature;

use App\Enums\ComplexPermission;
use App\Enums\MembershipStatus;
use App\Enums\ResidentialComplexStatus;
use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use App\Settings\ComplexSettingsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Core\CoreTestCase;

/**
 * Superficie `/api/v1/buildings` (conjuntos residenciales).
 *
 * La API de conjuntos exige sesion y `auth`, resuelve el alcance del actor
 * en el servidor contra sus membresias activas y aísla los conjuntos que no
 * estan en su alcance (404). El alta exige `complex.create` (plataforma) y
 * la baja archiva: nunca borra en cascada.
 */
class ResidentialComplexApiTest extends CoreTestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- guests

    public function test_guest_cannot_list_buildings(): void
    {
        $this->getJson('/api/v1/buildings')->assertUnauthorized();
    }

    public function test_guest_cannot_create_a_building(): void
    {
        $this->postJson('/api/v1/buildings', $this->payload())->assertUnauthorized();
    }

    public function test_guest_cannot_show_a_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');

        $this->getJson("/api/v1/buildings/{$complex->uuid}")->assertUnauthorized();
    }

    public function test_guest_cannot_update_a_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');

        $this->patchJson("/api/v1/buildings/{$complex->uuid}", ['name' => 'Otro nombre'])
            ->assertUnauthorized();
    }

    public function test_guest_cannot_archive_a_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');

        $this->deleteJson("/api/v1/buildings/{$complex->uuid}")->assertUnauthorized();
    }

    // ------------------------------------------------------------- alcance

    public function test_actor_without_active_membership_cannot_list_buildings(): void
    {
        $this->makeComplex('Torres del Norte');

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/buildings')->assertForbidden();
    }

    public function test_listing_only_returns_the_complexes_of_the_actor(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::RESIDENT);

        $response = $this->actingAs($user)->getJson('/api/v1/buildings')->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.uuid', $mine->uuid);
        $this->assertNotContains($other->uuid, array_column($response->json('data'), 'uuid'));
    }

    public function test_platform_admin_sees_the_whole_catalog(): void
    {
        $first = $this->makeComplex('Conjunto Uno');
        $second = $this->makeComplex('Conjunto Dos');

        [$user] = $this->makeMember($first, SystemRole::PLATFORM_ADMIN);

        $response = $this->actingAs($user)->getJson('/api/v1/buildings')->assertOk();

        $uuids = array_column($response->json('data'), 'uuid');
        $this->assertContains($first->uuid, $uuids);
        $this->assertContains($second->uuid, $uuids);
    }

    public function test_member_of_another_complex_cannot_see_it(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::RESIDENT);

        $this->actingAs($user)->getJson("/api/v1/buildings/{$other->uuid}")->assertNotFound();
    }

    public function test_member_of_another_complex_cannot_update_it(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$other->uuid}", ['name' => 'Secuestrado'])
            ->assertNotFound();

        $this->assertSame('Conjunto Ajeno', $other->fresh()->name);
    }

    public function test_member_of_another_complex_cannot_archive_it(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user] = $this->makeMember($mine, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)->deleteJson("/api/v1/buildings/{$other->uuid}")->assertNotFound();

        $this->assertNotSame(ResidentialComplexStatus::ARCHIVED, $other->fresh()->status);
    }

    public function test_returns_404_for_nonexistent_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->getJson('/api/v1/buildings/2c1f4b0e-0000-4000-8000-000000000000')
            ->assertNotFound();
    }

    public function test_returns_404_for_a_malformed_identifier(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)->getJson('/api/v1/buildings/no-es-un-uuid')->assertNotFound();
    }

    // -------------------------------------------------------------- listado

    public function test_can_filter_buildings_by_status(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ACTIVE]);

        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/buildings?status=active')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
    }

    public function test_can_search_buildings_by_name_or_code(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/buildings?search=torres')
            ->assertOk();

        $response->assertJsonCount(1, 'data');

        $this->actingAs($user)->getJson('/api/v1/buildings?search='.$complex->code)
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_listing_rejects_an_unknown_order_column(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($user)
            ->getJson('/api/v1/buildings?order_by=secret_column')
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_by');
    }

    public function test_listing_rejects_an_invalid_order_direction(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($user)
            ->getJson('/api/v1/buildings?order_dir=sideways')
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_dir');
    }

    public function test_listing_rejects_an_out_of_range_per_page(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($user)
            ->getJson('/api/v1/buildings?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($user)
            ->getJson('/api/v1/buildings?per_page=1000')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    // ----------------------------------------------------------------- alta

    public function test_complex_admin_cannot_create_a_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload())
            ->assertForbidden();
    }

    public function test_platform_admin_can_create_a_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload())
            ->assertCreated();

        $response->assertJsonPath('data.status', ResidentialComplexStatus::DRAFT->value);

        $this->assertDatabaseHas('residential_complexes', ['name' => 'Conjunto Nuevo']);
    }

    public function test_create_building_requires_mandatory_fields(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($user)
            ->postJson('/api/v1/buildings', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'code', 'address', 'city']);
    }

    public function test_create_building_validates_unique_code(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload(['code' => $complex->code]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');
    }

    public function test_update_building_validates_unique_code(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $other = $this->makeComplex('Torres del Sur');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['code' => $other->code])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertNotSame($other->code, $complex->fresh()->code);
    }

    // --------------------------------------------------------------- edicion

    public function test_complex_admin_can_update_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertOk()
            ->assertJsonPath('data.city', 'Medellin');

        $this->assertSame('Medellin', $complex->fresh()->city);
    }

    public function test_put_updates_the_building_like_patch_does(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->putJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Cali'])
            ->assertOk()
            ->assertJsonPath('data.city', 'Cali');

        $this->assertSame('Cali', $complex->fresh()->city);
    }

    public function test_member_without_edit_permission_cannot_update_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertForbidden();

        $this->assertSame($complex->city, $complex->fresh()->city);
    }

    public function test_member_with_an_expired_role_assignment_cannot_update_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        // La asignacion del rol vencio: ya no concede nada.
        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::COMPLEX_ADMIN->value)->firstOrFail()->id,
            ['valid_until' => now()->subDay()]
        );

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertForbidden();

        $this->assertSame($complex->city, $complex->fresh()->city);
    }

    public function test_member_with_an_expired_role_assignment_cannot_archive_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::COMPLEX_ADMIN->value)->firstOrFail()->id,
            ['valid_until' => now()->subDay()]
        );

        $this->actingAs($user)
            ->deleteJson("/api/v1/buildings/{$complex->uuid}")
            ->assertForbidden();

        $this->assertNotSame(ResidentialComplexStatus::ARCHIVED, $complex->fresh()->status);
    }

    public function test_member_with_a_pending_role_assignment_cannot_update_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::COMPLEX_ADMIN->value)->firstOrFail()->id,
            ['valid_from' => now()->addDay()]
        );

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertForbidden();

        $this->assertSame($complex->city, $complex->fresh()->city);
    }

    public function test_member_with_a_role_of_another_complex_cannot_update_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $other = $this->makeComplex('Torres del Sur');

        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        // Rol del otro conjunto con permiso de edicion: no alcanza al
        // conjunto de la membresia.
        $otherRole = Role::factory()->complexLevel($other)->create();
        $otherRole->grantPermission(ComplexPermission::COMPLEX_EDIT);
        $membership->roles()->attach($otherRole->id);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertForbidden();

        $this->assertSame($complex->city, $complex->fresh()->city);
    }

    public function test_member_with_a_role_of_another_complex_cannot_read_the_governance_data(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $other = $this->makeComplex('Torres del Sur');

        $user = User::factory()->create();
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $otherRole = Role::factory()->complexLevel($other)->create();
        $otherRole->grantPermission(ComplexPermission::COMPLEX_MANAGE_SETTINGS);
        $membership->roles()->attach($otherRole->id);

        $this->actingAs($user)
            ->getJson("/api/v1/buildings/{$complex->uuid}")
            ->assertOk()
            ->assertJsonMissingPath('data.settings')
            ->assertJsonMissingPath('data.legal_identifier');
    }

    public function test_cannot_change_code_when_not_draft(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ACTIVE]);
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['code' => 'otro-codigo'])
            ->assertStatus(422);

        $this->assertSame($complex->code, $complex->fresh()->code);
    }

    public function test_archived_complex_is_not_writable(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ARCHIVED]);
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertStatus(422);
    }

    public function test_suspended_complex_is_not_writable(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::SUSPENDED]);
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['city' => 'Medellin'])
            ->assertStatus(422);
    }

    // ----------------------------------------------------------------- baja

    public function test_delete_archives_the_building_instead_of_deleting_it(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->deleteJson("/api/v1/buildings/{$complex->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', ResidentialComplexStatus::ARCHIVED->value);

        $this->assertDatabaseHas('residential_complexes', ['uuid' => $complex->uuid]);
        $this->assertSame(ResidentialComplexStatus::ARCHIVED, $complex->fresh()->status);
    }

    public function test_delete_does_not_cascade_to_the_data_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $person = $this->makePerson($complex);
        $unit = $this->makeUnit($complex);

        $this->actingAs($user)->deleteJson("/api/v1/buildings/{$complex->uuid}")->assertOk();

        $this->assertDatabaseHas('people', ['id' => $person->id]);
        $this->assertDatabaseHas('property_units', ['id' => $unit->id]);
    }

    public function test_member_without_edit_permission_cannot_archive_the_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($user)
            ->deleteJson("/api/v1/buildings/{$complex->uuid}")
            ->assertForbidden();

        $this->assertNotSame(ResidentialComplexStatus::ARCHIVED, $complex->fresh()->status);
    }

    // ---------------------------------------------------------- proyeccion

    public function test_settings_and_legal_identifier_are_hidden_without_the_permission(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['legal_identifier' => '900123456-7']);
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/buildings/{$complex->uuid}")
            ->assertOk();

        $response->assertJsonMissingPath('data.legal_identifier');
        $response->assertJsonMissingPath('data.settings');
        $response->assertJsonPath('data.name', 'Torres del Norte');
    }

    public function test_settings_and_legal_identifier_are_visible_with_the_permission(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['legal_identifier' => '900123456-7']);
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/buildings/{$complex->uuid}")
            ->assertOk();

        $response->assertJsonPath('data.legal_identifier', '900123456-7');
        $response->assertJsonStructure(['data' => ['settings']]);
    }

    public function test_listing_hides_the_governance_data_for_members_without_the_permission(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['legal_identifier' => '900123456-7']);
        [$user] = $this->makeMember($complex, SystemRole::RESIDENT);

        $response = $this->actingAs($user)->getJson('/api/v1/buildings')->assertOk();

        $response->assertJsonMissingPath('data.0.legal_identifier');
        $response->assertJsonMissingPath('data.0.settings');
    }

    // ------------------------------------------------------------- Plateau

    public function test_a_suspended_membership_does_not_grant_access(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $membership->update(['status' => MembershipStatus::SUSPENDED]);

        $this->actingAs($user)->getJson('/api/v1/buildings')->assertForbidden();
    }

    public function test_a_revoked_platform_membership_does_not_grant_the_catalog(): void
    {
        $complex = $this->makeComplex('Conjunto Uno');
        $other = $this->makeComplex('Conjunto Dos');

        [$user, $membership] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);
        $membership->update(['status' => MembershipStatus::REVOKED]);

        $this->actingAs($user)->getJson('/api/v1/buildings')->assertForbidden();

        $this->assertDatabaseHas('residential_complexes', ['uuid' => $other->uuid]);
    }

    public function test_a_suspended_platform_membership_cannot_create_a_building(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user, $membership] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);
        $membership->update(['status' => MembershipStatus::SUSPENDED]);

        $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseMissing('residential_complexes', ['name' => 'Conjunto Nuevo']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    // ------------------------------------------- vigencia del rol de plataforma

    public function test_platform_admin_with_an_expired_role_assignment_does_not_see_the_whole_catalog(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user, $membership] = $this->makeMember($mine, SystemRole::PLATFORM_ADMIN);

        // La asignacion del rol de plataforma vencio: ya no concede el
        // catalogo completo.
        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::PLATFORM_ADMIN->value)->firstOrFail()->id,
            ['valid_until' => now()->subDay()]
        );

        $response = $this->actingAs($user)->getJson('/api/v1/buildings')->assertOk();

        $uuids = array_column($response->json('data'), 'uuid');
        $this->assertContains($mine->uuid, $uuids);
        $this->assertNotContains($other->uuid, $uuids);
    }

    public function test_platform_admin_with_an_expired_role_assignment_cannot_create_a_building(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');

        [$user, $membership] = $this->makeMember($mine, SystemRole::PLATFORM_ADMIN);

        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::PLATFORM_ADMIN->value)->firstOrFail()->id,
            ['valid_until' => now()->subDay()]
        );

        $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload())
            ->assertForbidden();
    }

    public function test_platform_admin_with_a_pending_role_assignment_cannot_create_a_building(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');

        [$user, $membership] = $this->makeMember($mine, SystemRole::PLATFORM_ADMIN);

        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::PLATFORM_ADMIN->value)->firstOrFail()->id,
            ['valid_from' => now()->addDay()]
        );

        $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload())
            ->assertForbidden();
    }

    public function test_platform_admin_with_an_expired_role_assignment_cannot_archive_another_complex(): void
    {
        $mine = $this->makeComplex('Conjunto Propio');
        $other = $this->makeComplex('Conjunto Ajeno');

        [$user, $membership] = $this->makeMember($mine, SystemRole::PLATFORM_ADMIN);

        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::PLATFORM_ADMIN->value)->firstOrFail()->id,
            ['valid_until' => now()->subDay()]
        );

        $this->actingAs($user)
            ->deleteJson("/api/v1/buildings/{$other->uuid}")
            ->assertNotFound();

        $this->assertNotSame(ResidentialComplexStatus::ARCHIVED, $other->fresh()->status);
    }

    // ------------------------------------------------- escritura de gobierno

    public function test_member_with_only_edit_permission_cannot_write_the_settings(): void
    {
        $complex = $this->makeComplex('Torres del Norte');

        [$user, $membership] = $this->makeMemberWithCustomRole($complex, [
            ComplexPermission::COMPLEX_VIEW,
            ComplexPermission::COMPLEX_EDIT,
        ]);

        $original = $complex->settings;

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", [
                'settings' => ['regional' => ['timezone' => 'America/Bogota']],
            ])
            ->assertForbidden();

        $this->assertSame($original, $complex->fresh()->settings);
    }

    public function test_member_with_only_edit_permission_cannot_write_the_legal_identifier(): void
    {
        $complex = $this->makeComplex('Torres del Norte');

        [$user, $membership] = $this->makeMemberWithCustomRole($complex, [
            ComplexPermission::COMPLEX_VIEW,
            ComplexPermission::COMPLEX_EDIT,
        ]);

        $original = $complex->legal_identifier;

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['legal_identifier' => 'NIT-EDITADO'])
            ->assertForbidden();

        $this->assertSame($original, $complex->fresh()->legal_identifier);
    }

    public function test_complex_admin_can_write_the_governance_data(): void
    {
        $complex = $this->makeComplex('Torres del Norte');

        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", [
                'legal_identifier' => 'NIT-900',
                'settings' => ['regional' => ['timezone' => 'America/Bogota']],
            ])
            ->assertOk();

        $fresh = $complex->fresh();
        $this->assertSame('NIT-900', $fresh->legal_identifier);
        $this->assertSame(
            ['regional' => ['timezone' => 'America/Bogota']],
            $fresh->settings
        );
    }

    // ------------------------------------------- estados y privilegios (T20)

    public function test_complex_admin_cannot_change_the_status_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::SUSPENDED->value])
            ->assertForbidden();

        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_complex_admin_can_still_update_the_other_attributes(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['name' => 'Torres Renombradas'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Torres Renombradas');

        $this->assertSame('Torres Renombradas', $complex->fresh()->name);
    }

    public function test_platform_admin_can_suspend_the_complex(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ACTIVE]);
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::SUSPENDED->value])
            ->assertOk()
            ->assertJsonPath('data.status', ResidentialComplexStatus::SUSPENDED->value);

        $this->assertSame(ResidentialComplexStatus::SUSPENDED, $complex->fresh()->status);
    }

    public function test_platform_admin_can_reactivate_a_suspended_complex(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::SUSPENDED]);
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->prepareActivatableComplex($complex);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertOk()
            ->assertJsonPath('data.status', ResidentialComplexStatus::ACTIVE->value);

        $this->assertSame(ResidentialComplexStatus::ACTIVE, $complex->fresh()->status);
    }

    public function test_an_archived_complex_cannot_be_reactivated(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$platform] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/buildings/{$complex->uuid}")
            ->assertOk();

        $this->assertSame(ResidentialComplexStatus::ARCHIVED, $complex->fresh()->status);

        // Aun con todas las precondiciones cumplidas, `archived` es terminal.
        $this->prepareActivatableComplex($complex);

        $response = $this->actingAs($platform)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertStatus(422);

        $this->assertStringContainsString('archived', (string) $response->json('message'));
        $this->assertStringContainsString('active', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::ARCHIVED, $complex->fresh()->status);
    }

    public function test_the_status_cannot_go_back_to_draft(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ACTIVE]);
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::DRAFT->value])
            ->assertStatus(422);

        $this->assertStringContainsString('active', (string) $response->json('message'));
        $this->assertStringContainsString('draft', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::ACTIVE, $complex->fresh()->status);
    }

    public function test_an_invalid_status_value_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => 'banana'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_activating_requires_an_administrator(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        // Estructura y politicas listas: lo que falta es el administrador.
        $this->makeUnit($complex);
        $complex->update(['settings' => ComplexSettingsCatalog::defaults()]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertStatus(422);

        $this->assertStringContainsString('administrador', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_activating_requires_a_physical_structure(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        // Administrador y politicas listos: lo que falta es la estructura.
        $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $complex->update(['settings' => ComplexSettingsCatalog::defaults()]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertStatus(422);

        $this->assertStringContainsString('estructura', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_activating_requires_the_minimum_policies(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        // Administrador y estructura listos: lo que faltan son las politicas.
        $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $this->makeUnit($complex);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertStatus(422);

        $this->assertStringContainsString('políticas', (string) $response->json('message'));
        $this->assertStringContainsString('regional', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_activating_requires_a_current_administrator_assignment(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        // La asignacion del rol del administrador vencio: ya no administra.
        [, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $membership->roles()->updateExistingPivot(
            Role::where('key', SystemRole::COMPLEX_ADMIN->value)->firstOrFail()->id,
            ['valid_until' => now()->subDay()]
        );

        $this->makeUnit($complex);
        $complex->update(['settings' => ComplexSettingsCatalog::defaults()]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertStatus(422);

        $this->assertStringContainsString('administrador', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_a_suspended_complex_cannot_be_edited(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::SUSPENDED]);
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['name' => 'Torres Renombradas'])
            ->assertStatus(422);

        $this->assertSame(
            'No se puede editar un conjunto suspendido o archivado.',
            $response->json('message')
        );
        $this->assertSame('Torres del Norte', $complex->fresh()->name);
    }

    public function test_the_complex_admin_can_still_archive_the_complex(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->deleteJson("/api/v1/buildings/{$complex->uuid}")
            ->assertOk()
            ->assertJsonPath('data.status', ResidentialComplexStatus::ARCHIVED->value);

        $this->assertSame(ResidentialComplexStatus::ARCHIVED, $complex->fresh()->status);
    }

    public function test_a_suspended_complex_rejects_a_no_op_status_write(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::SUSPENDED]);
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        // Un conjunto no escribible no admite ninguna escritura, ni siquiera
        // repetir su propio estado.
        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::SUSPENDED->value])
            ->assertStatus(422);

        $this->assertSame(
            'No se puede editar un conjunto suspendido o archivado.',
            $response->json('message')
        );
        $this->assertSame(ResidentialComplexStatus::SUSPENDED, $complex->fresh()->status);
    }

    public function test_a_reactivation_cannot_carry_other_attributes(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::SUSPENDED]);
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->prepareActivatableComplex($complex);

        // Reactivar es la unica escritura admitida sobre un conjunto
        // suspendido: no puede arrastrar otros atributos.
        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", [
                'status' => ResidentialComplexStatus::ACTIVE->value,
                'name' => 'Torres Renombradas',
            ])
            ->assertStatus(422);

        $fresh = $complex->fresh();

        $this->assertSame('Torres del Norte', $fresh->name);
        $this->assertSame(ResidentialComplexStatus::SUSPENDED, $fresh->status);
    }

    public function test_a_status_change_is_audited(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ACTIVE]);
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::SUSPENDED->value])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'residential_complex_id' => $complex->id,
            'entity_type' => ResidentialComplex::class,
            'action' => 'updated',
        ]);
    }

    public function test_the_listing_still_filters_by_status(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $complex->update(['status' => ResidentialComplexStatus::ACTIVE]);
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/buildings?status=active')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($complex->uuid, $response->json('data.0.uuid'));
    }

    public function test_a_status_change_on_a_complex_of_another_complex_is_404(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        $other = $this->makeComplex('Torres del Sur');
        [$user] = $this->makeMember($other, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::SUSPENDED->value])
            ->assertNotFound();

        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    public function test_a_complex_is_always_created_in_draft(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $before = ResidentialComplex::count();

        // El alta no acepta un estado: el conjunto nace en borrador y se
        // activa despues de configurarlo (spec §2, paso 1).
        $this->actingAs($user)
            ->postJson('/api/v1/buildings', $this->payload([
                'status' => ResidentialComplexStatus::ACTIVE->value,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame($before, ResidentialComplex::count());
    }

    public function test_activating_requires_a_non_empty_policy_group(): void
    {
        $complex = $this->makeComplex('Torres del Norte');
        [$user] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $this->makeUnit($complex);

        // Un grupo presente pero vacio no define ninguna politica.
        $settings = ComplexSettingsCatalog::defaults();
        $settings['regional'] = [];

        $complex->update(['settings' => $settings]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/buildings/{$complex->uuid}", ['status' => ResidentialComplexStatus::ACTIVE->value])
            ->assertStatus(422);

        $this->assertStringContainsString('regional', (string) $response->json('message'));
        $this->assertSame(ResidentialComplexStatus::DRAFT, $complex->fresh()->status);
    }

    /**
     * Miembro activo con un rol propio que lleva solo los permisos indicados.
     *
     * @param  array<int, ComplexPermission>  $permissions
     * @return array{0: User, 1: ResidentialComplexMembership}
     */
    private function makeMemberWithCustomRole(ResidentialComplex $complex, array $permissions): array
    {
        $user = User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $role = Role::factory()->create();
        foreach ($permissions as $permission) {
            $role->grantPermission($permission);
        }
        $membership->roles()->attach($role->id);

        return [$user, $membership];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Conjunto Nuevo',
            'code' => 'conjunto-nuevo-'.uniqid(),
            'address' => 'Calle 1 # 2-3',
            'city' => 'Bogota',
        ], $overrides);
    }

    /**
     * Deja el conjunto con las tres precondiciones de activacion (ACE-C03):
     * administrador vigente, estructura fisica y politicas minimas.
     */
    private function prepareActivatableComplex(ResidentialComplex $complex): void
    {
        $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $this->makeUnit($complex);
        $complex->update(['settings' => ComplexSettingsCatalog::defaults()]);
    }
}
