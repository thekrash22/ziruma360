<?php

namespace Tests\Feature\Core;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\Person;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Superficie de membresias del conjunto (specs S73-S78).
 *
 * La superficie es `/api/v1/core/members` y gobierna las membresias del
 * conjunto activo: las lista, invita, acepta la invitacion, suspende, revoca
 * y asigna roles. El permiso de escritura es `complex.manage_members`; la
 * aceptacion de la propia invitacion la hace el invitado.
 *
 * Reglas que se comprueban aqui:
 * - Aislamiento: una membresia de otro conjunto no se ve ni se modifica (404).
 * - Matriz de permisos: con y sin `complex.manage_members`.
 * - Ciclo de vida: invited -> active -> suspended -> revoked, con 422 en las
 *   transiciones que no existen.
 * - Perdida de acceso inmediato al suspender o revocar.
 * - Invitacion: reutiliza la persona por documento (RN-P02), no exige duplicar
 *   la persona al vincularla a la cuenta (ACE-P03) y rechaza los casos que no
 *   pueden darse.
 * - Roles: reemplazan el conjunto de roles y su vigencia manda (un
 *   `valid_until` vencido no concede permisos).
 */
class MembershipSurfaceTest extends CoreTestCase
{
    private const DOCUMENT_TYPE = 'CC';

    private const DOCUMENT_NUMBER = '1010101010';

    private const INVITEE_EMAIL = 'invitado@example.com';

    // ------------------------------------------------------------ listado

    public function test_a_member_without_the_permission_cannot_list_the_memberships(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($resident)
            ->getJson('/api/v1/core/members')
            ->assertForbidden();
    }

    public function test_the_admin_lists_the_memberships_of_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/v1/core/members')
            ->assertOk()
            ->assertJsonPath('data.0.id', $membership->id)
            ->assertJsonPath('data.0.status', MembershipStatus::ACTIVE->value)
            ->assertJsonPath('data.0.user.id', $admin->id);
    }

    public function test_the_listing_exposes_the_invitation_trace_and_the_roles_with_their_validity(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $membership->update(['invited_by' => $admin->id, 'invited_at' => now(), 'joined_at' => now()]);

        $ownerRole = Role::where('key', SystemRole::OWNER->value)->firstOrFail();
        $membership->roles()->attach($ownerRole->id, [
            'valid_from' => '2026-01-01 00:00:00',
            'valid_until' => '2027-01-01 00:00:00',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/core/members')
            ->assertOk()
            ->assertJsonPath('data.0.invited_by.id', $admin->id)
            ->assertJsonPath('data.0.invited_at', $membership->fresh()->invited_at->toIso8601String())
            ->assertJsonPath('data.0.joined_at', $membership->fresh()->joined_at->toIso8601String())
            ->assertJsonPath('data.0.roles.0.key', SystemRole::OWNER->value)
            ->assertJsonPath('data.0.roles.0.valid_from', '2026-01-01T00:00:00+00:00')
            ->assertJsonPath('data.0.roles.0.valid_until', '2027-01-01T00:00:00+00:00');
    }

    // ---------------------------------------------------------- aislamiento

    public function test_a_membership_of_another_complex_is_not_found(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$adminA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);
        [, $membershipB] = $this->makeMember($complexB, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($adminA)
            ->postJson("/api/v1/core/members/{$membershipB->id}/suspend")
            ->assertNotFound();

        $this->assertSame(MembershipStatus::ACTIVE, $membershipB->fresh()->status);
    }

    public function test_the_roles_of_a_membership_of_another_complex_are_not_modified(): void
    {
        $complexA = $this->makeComplex('Complejo A');
        $complexB = $this->makeComplex('Complejo B');

        [$adminA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);
        [, $membershipB] = $this->makeMember($complexB, SystemRole::COMPLEX_ADMIN);

        $ownerRole = Role::where('key', SystemRole::OWNER->value)->firstOrFail();

        $this->actingAs($adminA)
            ->putJson("/api/v1/core/members/{$membershipB->id}/roles", [
                'roles' => [['role_key' => SystemRole::OWNER->value]],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('membership_roles', [
            'membership_id' => $membershipB->id,
            'role_id' => $ownerRole->id,
        ]);
    }

    // ----------------------------------------------------------- invitacion

    public function test_the_invitation_creates_an_invited_membership(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $person = $this->makePersonWithDocument($complex);
        $account = User::factory()->create(['email' => self::INVITEE_EMAIL]);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', MembershipStatus::INVITED->value)
            ->assertJsonPath('data.invited_by.id', $admin->id)
            ->assertJsonPath('data.user.id', $account->id);

        $membership = ResidentialComplexMembership::where('user_id', $account->id)->firstOrFail();

        $this->assertSame($complex->id, $membership->residential_complex_id);
        $this->assertSame(MembershipStatus::INVITED, $membership->status);
        $this->assertSame($admin->id, $membership->invited_by);
        $this->assertNotNull($membership->invited_at);
        $this->assertNull($membership->joined_at);
        $this->assertNotNull($person->fresh()->user_id);
    }

    public function test_the_invitation_reuses_the_person_of_the_document_without_duplicating_it(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $person = $this->makePersonWithDocument($complex);
        User::factory()->create(['email' => self::INVITEE_EMAIL]);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertCreated();

        $this->assertSame(1, Person::withoutGlobalScope('complex')
            ->where('residential_complex_id', $complex->id)
            ->count());
        $this->assertNotNull($person->fresh()->user_id);
    }

    public function test_the_invitation_is_rejected_when_the_person_does_not_exist(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        User::factory()->create(['email' => self::INVITEE_EMAIL]);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No existe ninguna persona con ese documento en el conjunto.');
    }

    public function test_the_invitation_is_rejected_when_the_account_does_not_exist(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $this->makePersonWithDocument($complex);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => 'nadie@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No existe ninguna cuenta de usuario con ese correo.');
    }

    public function test_the_invitation_is_rejected_when_the_person_is_already_linked_to_another_account(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $otherAccount = User::factory()->create(['email' => 'otra@example.com']);
        $person = $this->makePersonWithDocument($complex);
        $person->update(['user_id' => $otherAccount->id]);
        User::factory()->create(['email' => self::INVITEE_EMAIL]);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'La persona ya está vinculada a otra cuenta de usuario.');
    }

    public function test_the_invitation_is_rejected_when_the_membership_already_exists(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $account = User::factory()->create(['email' => self::INVITEE_EMAIL]);
        $this->makePersonWithDocument($complex);

        ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => $account->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta cuenta ya tiene una membresía en el conjunto.');
    }

    public function test_the_invitation_is_atomic_when_the_membership_cannot_be_created(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $person = $this->makePersonWithDocument($complex);
        $account = User::factory()->create(['email' => self::INVITEE_EMAIL]);

        // El alta de la membresia falla: la vinculacion de la persona tiene
        // que revertirse con ella (S81).
        $this->onCreatingMembership(function (): void {
            throw new RuntimeException('No se pudo crear la membresía.');
        });

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertStatus(500);

        // La persona conserva el `user_id` que tenia: en este caso, ninguno.
        $this->assertNull($person->fresh()->user_id);

        $this->assertSame(0, ResidentialComplexMembership::query()
            ->where('residential_complex_id', $complex->id)
            ->where('user_id', $account->id)
            ->count());
    }

    public function test_the_invitation_keeps_the_previous_account_of_the_person_when_the_membership_fails(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $account = User::factory()->create(['email' => self::INVITEE_EMAIL]);
        $person = $this->makePersonWithDocument($complex);
        $person->update(['user_id' => $account->id]);

        $this->onCreatingMembership(function (): void {
            throw new RuntimeException('No se pudo crear la membresía.');
        });

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertStatus(500);

        // La persona conserva la cuenta que tenia.
        $this->assertSame($account->id, $person->fresh()->user_id);

        $this->assertSame(0, ResidentialComplexMembership::query()
            ->where('residential_complex_id', $complex->id)
            ->where('user_id', $account->id)
            ->count());
    }

    public function test_the_invitation_of_a_duplicated_membership_is_rejected_with_422(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $person = $this->makePersonWithDocument($complex);
        $account = User::factory()->create(['email' => self::INVITEE_EMAIL]);

        // La carrera: entre la comprobacion y el insert entra otra membresia
        // del mismo par, y el UNIQUE(residential_complex_id, user_id) rechaza
        // el insert. La respuesta es 422, nunca 500 (S81).
        $this->onCreatingMembership(function (ResidentialComplexMembership $membership): void {
            DB::table('residential_complex_memberships')->insert([
                'residential_complex_id' => $membership->residential_complex_id,
                'user_id' => $membership->user_id,
                'status' => MembershipStatus::INVITED->value,
                'invited_by' => $membership->invited_by,
                'invited_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Esta cuenta ya tiene una membresía en el conjunto.');

        // La persona no queda vinculada sin membresia y no queda una segunda
        // membresia del mismo par.
        $this->assertNull($person->fresh()->user_id);

        $this->assertSame(0, ResidentialComplexMembership::query()
            ->where('residential_complex_id', $complex->id)
            ->where('user_id', $account->id)
            ->count());
    }

    public function test_a_member_without_the_permission_cannot_invite(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);
        $this->makePersonWithDocument($complex);
        User::factory()->create(['email' => self::INVITEE_EMAIL]);

        $this->actingAs($resident)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------ ciclo de vida

    public function test_the_invited_member_accepts_the_invitation(): void
    {
        $complex = $this->makeComplex('Torres');

        [$invitee, $membership] = $this->makeInvitedMembership($complex);

        $this->actingAs($invitee)
            ->postJson("/api/v1/core/members/{$membership->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::ACTIVE->value);

        $membership = $membership->fresh();

        $this->assertSame(MembershipStatus::ACTIVE, $membership->status);
        $this->assertNotNull($membership->joined_at);
    }

    public function test_the_admin_activates_an_invitation(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeInvitedMembership($complex);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::ACTIVE->value);

        $this->assertNotNull($membership->fresh()->joined_at);
    }

    public function test_a_third_party_cannot_activate_the_invitation_of_another_member(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);
        [, $membership] = $this->makeInvitedMembership($complex);

        // La aceptacion la hace el propio invitado o un administrador con
        // `complex.manage_members`: un tercero sin ese permiso no puede.
        $this->actingAs($resident)
            ->postJson("/api/v1/core/members/{$membership->id}/activate")
            ->assertForbidden();

        $this->assertSame(MembershipStatus::INVITED, $membership->fresh()->status);
    }

    public function test_activating_a_membership_that_is_not_invited_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/activate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La membresía solo puede activarse cuando está en estado «invited».');
    }

    public function test_the_admin_suspends_a_membership(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::SUSPENDED->value);

        $this->assertSame(MembershipStatus::SUSPENDED, $membership->fresh()->status);
    }

    public function test_suspending_a_membership_that_is_not_active_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeInvitedMembership($complex);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/suspend")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Solo una membresía activa puede suspenderse.');
    }

    public function test_the_admin_revokes_a_membership(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::REVOKED->value);

        $this->assertSame(MembershipStatus::REVOKED, $membership->fresh()->status);
    }

    public function test_revoking_a_revoked_membership_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/revoke")
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/revoke")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La membresía ya está revocada: la revocación es definitiva.');
    }

    public function test_a_member_without_the_permission_cannot_suspend_or_revoke(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($resident)
            ->postJson("/api/v1/core/members/{$membership->id}/suspend")
            ->assertForbidden();

        $this->actingAs($resident)
            ->postJson("/api/v1/core/members/{$membership->id}/revoke")
            ->assertForbidden();

        $this->assertSame(MembershipStatus::ACTIVE, $membership->fresh()->status);
    }

    public function test_the_full_lifecycle_of_a_membership(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        $this->makePersonWithDocument($complex);
        $account = User::factory()->create(['email' => self::INVITEE_EMAIL]);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/members', [
                'document_type' => self::DOCUMENT_TYPE,
                'document_number' => self::DOCUMENT_NUMBER,
                'email' => self::INVITEE_EMAIL,
            ])
            ->assertCreated();

        $membership = ResidentialComplexMembership::where('user_id', $account->id)->firstOrFail();

        // El invitado acepta su propia invitacion.
        $this->actingAs($account)
            ->postJson("/api/v1/core/members/{$membership->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::ACTIVE->value);

        // El administrador suspende y luego revoca.
        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::SUSPENDED->value);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', MembershipStatus::REVOKED->value);

        $this->assertSame(MembershipStatus::REVOKED, $membership->fresh()->status);
    }

    // ------------------------------------------------- perdida de acceso

    public function test_a_suspended_member_loses_the_access_immediately(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$otherAdmin, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        // Antes de la suspension, el otro administrador tenía acceso.
        $this->actingAs($otherAdmin)
            ->getJson('/api/v1/core/members')
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/suspend")
            ->assertOk();

        $this->actingAs($otherAdmin)
            ->getJson('/api/v1/core/members')
            ->assertForbidden();
    }

    public function test_a_revoked_member_loses_the_access_immediately(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$otherAdmin, $membership] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($otherAdmin)
            ->getJson('/api/v1/core/members')
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/v1/core/members/{$membership->id}/revoke")
            ->assertOk();

        $this->actingAs($otherAdmin)
            ->getJson('/api/v1/core/members')
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- roles

    public function test_the_roles_of_a_membership_are_replaced(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $residentRole = Role::where('key', SystemRole::RESIDENT->value)->firstOrFail();
        $ownerRole = Role::where('key', SystemRole::OWNER->value)->firstOrFail();

        $this->actingAs($admin)
            ->putJson("/api/v1/core/members/{$membership->id}/roles", [
                'roles' => [[
                    'role_key' => SystemRole::OWNER->value,
                    'valid_from' => '2026-01-01',
                    'valid_until' => '2027-01-01',
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('data.roles.0.key', SystemRole::OWNER->value);

        $this->assertDatabaseMissing('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $residentRole->id,
        ]);

        $this->assertDatabaseHas('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $ownerRole->id,
        ]);
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $residentRole = Role::where('key', SystemRole::RESIDENT->value)->firstOrFail();

        $this->actingAs($admin)
            ->putJson("/api/v1/core/members/{$membership->id}/roles", [
                'roles' => [['role_key' => 'rol_que_no_existe']],
            ])
            ->assertStatus(422);

        // El rechazo no deja la membresia a medias: el conjunto de roles
        // sigue siendo el que tenia (S76 reemplaza el conjunto, y un rol
        // desconocido se rechaza sin persistir nada).
        $this->assertDatabaseHas('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $residentRole->id,
        ]);

        $this->assertSame(1, DB::table('membership_roles')
            ->where('membership_id', $membership->id)
            ->count());
    }

    public function test_a_role_of_another_complex_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $ownerRole = Role::where('key', SystemRole::OWNER->value)->firstOrFail();
        $residentRole = Role::where('key', SystemRole::RESIDENT->value)->firstOrFail();

        $foreignRole = Role::factory()->complexLevel($other)->create(['key' => 'rol_ajeno']);

        $this->actingAs($admin)
            ->putJson("/api/v1/core/members/{$membership->id}/roles", [
                'roles' => [['role_id' => $foreignRole->id]],
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $foreignRole->id,
        ]);

        // El rechazo tampoco cambia el conjunto de roles que tenia.
        $this->assertDatabaseHas('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $residentRole->id,
        ]);
    }

    public function test_a_member_without_the_permission_cannot_assign_roles(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);
        [, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $ownerRole = Role::where('key', SystemRole::OWNER->value)->firstOrFail();

        $this->actingAs($resident)
            ->putJson("/api/v1/core/members/{$membership->id}/roles", [
                'roles' => [['role_key' => SystemRole::OWNER->value]],
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('membership_roles', [
            'membership_id' => $membership->id,
            'role_id' => $ownerRole->id,
        ]);
    }

    public function test_an_expired_role_assignment_does_not_grant_permissions(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$member, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        // Se asigna el rol de administrador con una vigencia ya vencida.
        $this->actingAs($admin)
            ->putJson("/api/v1/core/members/{$membership->id}/roles", [
                'roles' => [[
                    'role_key' => SystemRole::COMPLEX_ADMIN->value,
                    'valid_until' => Carbon::now()->subDay()->toDateString(),
                ]],
            ])
            ->assertOk();

        // El rol vencido no concede el permiso de administrar miembros.
        $this->actingAs($member)
            ->getJson('/api/v1/core/members')
            ->assertForbidden();
    }

    public function test_a_role_assignment_in_force_grants_the_permissions(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$member, $membership] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->actingAs($admin)
            ->putJson("/api/v1/core/members/{$membership->id}/roles", [
                'roles' => [[
                    'role_key' => SystemRole::COMPLEX_ADMIN->value,
                    'valid_until' => Carbon::now()->addDay()->toDateString(),
                ]],
            ])
            ->assertOk();

        $this->actingAs($member)
            ->getJson('/api/v1/core/members')
            ->assertOk();
    }

    // ------------------------------------------------------------- helpers

    /**
     * Persona del conjunto con un documento conocido y sin cuenta.
     */
    private function makePersonWithDocument($complex, string $number = self::DOCUMENT_NUMBER): Person
    {
        return Person::factory()
            ->forComplex($complex)
            ->withDocument(self::DOCUMENT_TYPE, $number)
            ->create(['user_id' => null]);
    }

    /**
     * Escucha el alta de una membresia, para simular el fallo del insert.
     *
     * La escucha se retira al terminar la prueba, para no afectar a las
     * demas.
     */
    private function onCreatingMembership(callable $listener): void
    {
        $event = 'eloquent.creating: '.ResidentialComplexMembership::class;

        Event::listen($event, $listener);

        $this->beforeApplicationDestroyed(fn () => Event::forget($event));
    }

    /**
     * Membresia en estado «invited» de una cuenta nueva.
     *
     * @return array{0: User, 1: ResidentialComplexMembership}
     */
    private function makeInvitedMembership($complex): array
    {
        $user = User::factory()->create();

        $membership = ResidentialComplexMembership::factory()->create([
            'residential_complex_id' => $complex->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::INVITED,
            'invited_at' => now(),
        ]);

        return [$user, $membership];
    }
}
