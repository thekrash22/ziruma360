<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\ResidentialComplex;
use App\Settings\ComplexSettingsCatalog;
use Illuminate\Validation\ValidationException;

/**
 * Políticas propias del conjunto (spec §1.4, RN-C04, §6 "Politicas").
 *
 * "Settings invalidos se rechazan y sus cambios se auditan": las claves y los
 * valores fuera del catalogo se rechazan y no se escribe nada; un cambio
 * valido queda auditado con su valor anterior y su valor nuevo.
 */
class ComplexSettingsPolicyTest extends CoreTestCase
{
    public function test_an_unknown_group_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'inventado' => ['lo que sea' => true],
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('inventado', $response->json('errors'));
    }

    public function test_an_unknown_key_in_a_known_group_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['continente' => 'sudamerica'],
            ])
            ->assertStatus(422);

        $this->assertArrayHasKey('regional.continente', $response->json('errors'));
    }

    public function test_an_invalid_value_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'No/Es/Zona'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('regional.timezone');
    }

    public function test_a_rejected_change_does_not_modify_the_settings(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'America/Mexico_City'],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'No/Es/Zona'],
            ])
            ->assertStatus(422);

        $this->assertSame(
            'America/Mexico_City',
            $complex->fresh()->settings['regional']['timezone']
        );
    }

    public function test_a_rejected_change_is_not_audited(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $logsBefore = AuditLog::where('entity_type', ResidentialComplex::class)->count();

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'inventado' => ['lo que sea' => true],
            ])
            ->assertStatus(422);

        $this->assertSame(
            $logsBefore,
            AuditLog::where('entity_type', ResidentialComplex::class)->count()
        );
    }

    public function test_a_valid_change_is_persisted_and_audited(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'America/Mexico_City', 'currency' => 'MXN'],
                'privacy' => ['retention_days' => 365],
            ])
            ->assertOk()
            ->assertJsonPath('settings.regional.timezone', 'America/Mexico_City');

        $this->assertSame('America/Mexico_City', $complex->fresh()->settings['regional']['timezone']);
        $this->assertSame(365, $complex->fresh()->settings['privacy']['retention_days']);

        $log = AuditLog::query()
            ->where('entity_type', ResidentialComplex::class)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($admin->id, (int) $log->actor_user_id);
        $this->assertSame($complex->id, (int) $log->residential_complex_id);
        $this->assertSame('America/Mexico_City', $log->after_data['settings']['regional']['timezone']);
        $this->assertArrayNotHasKey('regional', $log->before_data['settings']);
    }

    public function test_the_settings_are_read_with_the_documented_defaults(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/core/complex/settings')
            ->assertOk();

        $this->assertSame(
            ComplexSettingsCatalog::defaults()['regional']['timezone'],
            $response->json('settings.regional.timezone')
        );
        $this->assertSame(
            ComplexSettingsCatalog::defaults()['privacy']['retention_days'],
            $response->json('settings.privacy.retention_days')
        );
    }

    public function test_a_partial_change_keeps_the_other_groups(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'America/Mexico_City'],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'privacy' => ['retention_days' => 90],
            ])
            ->assertOk();

        $settings = $complex->fresh()->settings;

        $this->assertSame('America/Mexico_City', $settings['regional']['timezone']);
        $this->assertSame(90, $settings['privacy']['retention_days']);
    }

    public function test_a_list_policy_is_replaced_and_not_merged_by_index(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'access' => ['invitable_roles' => ['complex_admin', 'doorman', 'owner']],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'access' => ['invitable_roles' => ['doorman']],
            ])
            ->assertOk();

        $this->assertSame(['doorman'], $complex->fresh()->settings['access']['invitable_roles']);
    }

    public function test_an_empty_list_clears_a_list_policy(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'access' => ['invitable_roles' => ['doorman', 'owner']],
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson('/api/v1/core/complex/settings', [
                'access' => ['invitable_roles' => []],
            ])
            ->assertOk();

        $this->assertSame([], $complex->fresh()->settings['access']['invitable_roles']);
    }

    public function test_an_owner_cannot_change_the_policies_of_the_complex(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $this->actingAs($owner)
            ->patchJson('/api/v1/core/complex/settings', [
                'regional' => ['timezone' => 'America/Mexico_City'],
            ])
            ->assertForbidden();

        $this->assertSame([], $complex->fresh()->settings);
    }

    public function test_the_model_rejects_invalid_settings_outside_the_http_surface(): void
    {
        $complex = $this->makeComplex('Torres');

        $this->expectException(ValidationException::class);

        $complex->update(['settings' => ['regional' => ['timezone' => 'No/Es/Zona']]]);
    }

    public function test_the_model_rejects_a_free_json_settings(): void
    {
        $complex = $this->makeComplex('Torres');

        $this->expectException(ValidationException::class);

        $complex->update(['settings' => ['lo que sea' => true]]);
    }
}
