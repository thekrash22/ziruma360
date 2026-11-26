<?php

namespace Tests\Feature\Web;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\ResidentialComplex;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Core\CoreTestCase;

/**
 * The audit trail screen (specs S102, S103 and S104 e).
 *
 * The screen is `/auditoria`: it lists the trail of the active complex with
 * the filters of the API and shows the diff of every record.
 *
 * The assertions are on the public interface: the status code and the
 * Inertia props, never on the internal state.
 */
class AuditTrailPageTest extends CoreTestCase
{
    public function test_the_admin_sees_the_trail_of_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, [
            'actor_user_id' => $admin->id,
            'entity_type' => 'App\Models\Person',
            'entity_id' => '42',
        ]);

        $response = $this->actingAs($admin)->get('/auditoria');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auditoria/Index')
            ->where('logs.data', function (Collection $rows) use ($admin): bool {
                $person = $rows->firstWhere('entity_type', 'App\Models\Person');

                return $person !== null
                    && $person['action'] === 'created'
                    && $person['entity_id'] === '42'
                    && $person['actor']['id'] === $admin->id
                    && $person['after_data']['full_name'] === 'Ana';
            })
            ->has('actions', 3)
        );
    }

    public function test_a_member_without_the_permission_is_forbidden(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->makeAuditLog($complex);

        $this->actingAs($resident)
            ->get('/auditoria')
            ->assertForbidden();
    }

    public function test_an_actor_without_an_active_complex_is_forbidden(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get('/auditoria')
            ->assertForbidden();
    }

    public function test_the_screen_does_not_list_the_trail_of_another_complex(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['entity_type' => 'App\Models\Person', 'entity_id' => '1']);
        $this->makeAuditLog($other, ['entity_type' => 'App\Models\Person', 'entity_id' => '2']);

        $response = $this->actingAs($admin)->get('/auditoria');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->where('logs.data', fn (Collection $rows): bool => count(array_filter(
                $rows->all(),
                fn (array $row): bool => $row['entity_type'] === 'App\Models\Person'
            )) === 1)
        );
    }

    public function test_the_filters_narrow_the_trail(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, [
            'action' => 'created',
            'entity_type' => 'App\Models\Person',
            'occurred_at' => Carbon::parse('2026-01-05 10:00:00'),
        ]);
        $this->makeAuditLog($complex, [
            'action' => 'deleted',
            'entity_type' => 'App\Models\PropertyUnit',
            'occurred_at' => Carbon::parse('2026-02-05 10:00:00'),
        ]);

        $response = $this->actingAs($admin)->get('/auditoria?action=deleted');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'deleted')
            ->where('filters.action', 'deleted')
        );
    }

    public function test_the_server_publishes_the_permission_that_gates_the_audit_menu_entry(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);
        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        // The administrator receives the permission: the menu shows the
        // entry and the screen answers.
        $this->actingAs($admin)->get('/auditoria')->assertInertia(
            fn (AssertableInertia $page) => $page->where(
                'permissions',
                fn (Collection $permissions): bool => $permissions->contains('complex.view_audit')
            )
        );

        // The resident does not receive it: the menu hides the entry and
        // the screen is forbidden.
        $this->actingAs($resident)->get('/auditoria')->assertForbidden();

        $this->actingAs($resident)->get('/units')->assertInertia(
            fn (AssertableInertia $page) => $page->where(
                'permissions',
                fn (Collection $permissions): bool => ! $permissions->contains('complex.view_audit')
            )
        );
    }

    public function test_the_sidebar_gates_the_audit_entry_with_the_audit_permission(): void
    {
        // The menu is a Vue component and the project has no runner of
        // JavaScript tests: the wiring of the entry is pinned here, and the
        // decision itself (the published permissions) is pinned by the
        // test above.
        $sidebar = file_get_contents(resource_path('js/Components/Sidebar.vue'));

        $this->assertIsString($sidebar);
        $this->assertStringContainsString("route: 'auditoria.index'", $sidebar);
        $this->assertStringContainsString("permission: 'complex.view_audit'", $sidebar);
        $this->assertStringContainsString('permissions.value.includes(item.permission)', $sidebar);
    }

    /**
     * A record of the trail, written directly.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAuditLog(ResidentialComplex $complex, array $attributes = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'residential_complex_id' => $complex->id,
            'actor_user_id' => null,
            'action' => 'created',
            'entity_type' => 'App\Models\Person',
            'entity_id' => '1',
            'before_data' => null,
            'after_data' => ['full_name' => 'Ana'],
            'occurred_at' => Carbon::parse('2026-01-01 10:00:00'),
            'correlation_id' => null,
        ], $attributes));
    }
}
