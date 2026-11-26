<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\ResidentialComplex;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Read surface of the audit trail (specs S100, S101 and S104 c/d).
 *
 * The surface is `GET /api/v1/core/audit-logs` and it lists the trail of the
 * active complex: paginated, in descending order by `occurred_at`, with
 * filters by action, entity and date range.
 *
 * Rules that are checked here:
 * - The door: `complex.view_audit` (platform and complex admin). Without it
 *   the answer is 403, never an empty list.
 * - The scope: only the trail of the active complex. A record of another
 *   complex is neither listed nor found by id (404).
 * - The filters and the pagination really narrow the result.
 * - The surface is read only: there is no route to change or delete a trail.
 */
class AuditLogSurfaceTest extends CoreTestCase
{
    // ------------------------------------------------------------- the door

    public function test_the_admin_lists_the_audit_trail_of_the_active_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['action' => 'created', 'entity_type' => 'App\Models\Person']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs')
            ->assertOk();

        // The trail of the complex itself is part of the trail: the complex
        // is one of the seven audited domains.
        $this->assertSame(
            [(string) $complex->id],
            $this->entityIdsOf($response->json('data'), 'App\Models\ResidentialComplex')
        );

        $this->assertCount(1, $this->entityIdsOf($response->json('data'), 'App\Models\Person'));
    }

    public function test_the_platform_admin_can_read_the_audit_trail(): void
    {
        $complex = $this->makeComplex('Torres');

        [$platform] = $this->makeMember($complex, SystemRole::PLATFORM_ADMIN);

        $this->makeAuditLog($complex);

        $response = $this->actingAs($platform)
            ->getJson('/api/v1/core/audit-logs')
            ->assertOk();

        $this->assertCount(1, $this->entityIdsOf($response->json('data'), 'App\Models\Person'));
    }

    public function test_a_member_without_the_permission_is_forbidden(): void
    {
        $complex = $this->makeComplex('Torres');

        [$resident] = $this->makeMember($complex, SystemRole::RESIDENT);

        $this->makeAuditLog($complex);

        $this->actingAs($resident)
            ->getJson('/api/v1/core/audit-logs')
            ->assertForbidden();
    }

    public function test_an_actor_without_an_active_complex_is_forbidden(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->getJson('/api/v1/core/audit-logs')
            ->assertForbidden();
    }

    // ---------------------------------------------------------- the scope

    public function test_the_trail_of_another_complex_is_not_listed(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['entity_id' => '1']);
        $this->makeAuditLog($other, ['entity_id' => '2']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs')
            ->assertOk();

        // Neither the trail of the other complex nor its own creation.
        $this->assertSame(['1'], $this->entityIdsOf($response->json('data'), 'App\Models\Person'));
        $this->assertSame(
            [(string) $complex->id],
            $this->entityIdsOf($response->json('data'), 'App\Models\ResidentialComplex')
        );
    }

    public function test_the_trail_of_another_complex_is_not_found_by_id(): void
    {
        $complex = $this->makeComplex('Torres');
        $other = $this->makeComplex('Otro conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $foreign = $this->makeAuditLog($other);

        $this->actingAs($admin)
            ->getJson("/api/v1/core/audit-logs/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_a_record_of_the_active_complex_is_found_by_id(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $log = $this->makeAuditLog($complex);

        $this->actingAs($admin)
            ->getJson("/api/v1/core/audit-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $log->id);
    }

    // ----------------------------------------------------------- the row

    public function test_the_row_exposes_the_actor_the_action_the_entity_and_the_diff(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, [
            'actor_user_id' => $admin->id,
            'action' => 'updated',
            'entity_type' => 'App\Models\Person',
            'entity_id' => '42',
            'before_data' => ['full_name' => 'Ana'],
            'after_data' => ['full_name' => 'Ana Ruiz'],
            'correlation_id' => 'corr-1',
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?entity_type=Person')
            ->assertOk();

        $row = $response->json('data.0');

        $this->assertSame('updated', $row['action']);
        $this->assertSame('App\Models\Person', $row['entity_type']);
        $this->assertSame('42', $row['entity_id']);
        $this->assertSame(['full_name' => 'Ana'], $row['before_data']);
        $this->assertSame(['full_name' => 'Ana Ruiz'], $row['after_data']);
        $this->assertSame('corr-1', $row['correlation_id']);
        $this->assertSame($admin->id, $row['actor']['id']);
        $this->assertNotEmpty($row['occurred_at']);
    }

    // -------------------------------------------------------- the filters

    public function test_the_action_filter_narrows_the_trail(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['action' => 'created']);
        $this->makeAuditLog($complex, ['action' => 'deleted']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?action=deleted')
            ->assertOk();

        $this->assertSame(['deleted'], array_column($response->json('data'), 'action'));
    }

    public function test_an_unknown_action_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?action=exploded')
            ->assertStatus(422);
    }

    public function test_the_entity_filters_narrow_the_trail(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['entity_type' => 'App\Models\Person', 'entity_id' => '1']);
        $this->makeAuditLog($complex, ['entity_type' => 'App\Models\PropertyUnit', 'entity_id' => '2']);

        $byType = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?entity_type=Person')
            ->assertOk();

        $this->assertSame(['App\Models\Person'], array_unique(array_column($byType->json('data'), 'entity_type')));

        $byId = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?entity_type=App%5CModels%5CPropertyUnit&entity_id=2')
            ->assertOk();

        $this->assertSame(['2'], array_column($byId->json('data'), 'entity_id'));
    }

    public function test_the_date_range_filter_narrows_the_trail(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['occurred_at' => Carbon::parse('2026-01-10 09:00:00')]);
        $this->makeAuditLog($complex, ['occurred_at' => Carbon::parse('2026-02-15 09:00:00')]);
        $this->makeAuditLog($complex, ['occurred_at' => Carbon::parse('2026-03-20 09:00:00')]);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?from=2026-02-01&to=2026-02-28')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(
            '2026-02-15T09:00:00+00:00',
            Carbon::parse($response->json('data.0.occurred_at'))->toIso8601String()
        );
    }

    public function test_a_date_range_that_ends_before_it_starts_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?from=2026-03-01&to=2026-02-01')
            ->assertStatus(422);
    }

    // ------------------------------------------------------ the pagination

    public function test_the_trail_is_paginated_in_descending_order(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->makeAuditLog($complex, ['occurred_at' => Carbon::parse('2026-01-01 10:00:00'), 'entity_id' => 'old']);
        $this->makeAuditLog($complex, ['occurred_at' => Carbon::parse('2026-01-03 10:00:00'), 'entity_id' => 'new']);
        $this->makeAuditLog($complex, ['occurred_at' => Carbon::parse('2026-01-02 10:00:00'), 'entity_id' => 'middle']);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?entity_type=Person')
            ->assertOk();

        $this->assertSame(['new', 'middle', 'old'], array_column($response->json('data'), 'entity_id'));

        $page = $this->actingAs($admin)
            ->getJson('/api/v1/core/audit-logs?entity_type=Person&per_page=2&page=2')
            ->assertOk();

        $this->assertSame(['old'], array_column($page->json('data'), 'entity_id'));
        $this->assertSame(3, $page->json('meta.total'));
        $this->assertSame(2, $page->json('meta.per_page'));
    }

    // ------------------------------------------------------- read only

    public function test_the_surface_is_read_only(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $log = $this->makeAuditLog($complex);

        $this->actingAs($admin)
            ->postJson('/api/v1/core/audit-logs')
            ->assertStatus(405);

        $this->actingAs($admin)
            ->patchJson("/api/v1/core/audit-logs/{$log->id}")
            ->assertStatus(405);

        $this->actingAs($admin)
            ->deleteJson("/api/v1/core/audit-logs/{$log->id}")
            ->assertStatus(405);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    /**
     * The entity ids of the rows of one entity type.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<string>
     */
    protected function entityIdsOf(array $rows, string $entityType): array
    {
        return array_values(array_map(
            static fn (array $row): string => (string) $row['entity_id'],
            array_filter($rows, static fn (array $row): bool => $row['entity_type'] === $entityType)
        ));
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
