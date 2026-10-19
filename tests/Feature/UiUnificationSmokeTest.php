<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Smoke tests for the Ziruma360 TailAdmin UI unification.
 *
 * Inertia does not server-render the Vue component body — it serves an
 * `app` shell with a `data-page` payload that the client hydrates. So we
 * assert on the Inertia component name and props rather than on the
 * component template body itself.
 *
 * This file is the single source of truth for the unification described
 * in odd/tasks/ziruma360-tailadmin-unification.md.
 */
class UiUnificationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_serves_the_new_tailadmin_welcome_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('Ziruma360', $html);
        $this->assertStringNotContainsString('Laravel has wonderful documentation', $html);
        $this->assertStringNotContainsString('Laracasts offers thousands', $html);
    }

    public function test_login_page_serves_auth_via_login_component(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->has('canResetPassword')
                ->where('canResetPassword', true)
                ->has('errors'));
    }

    public function test_register_page_serves_auth_via_register_component(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Register')
                ->has('errors'));
    }

    public function test_forgot_password_page_serves_auth_via_forgot_password_component(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/ForgotPassword'));
    }

    public function test_dashboard_serves_dashboard_through_app_layout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->has('auth.user', fn (AssertableInertia $p) => $p
                    ->where('id', $user->id)
                    ->where('email', $user->email)
                    ->etc()));
    }

    public function test_profile_serves_edit_through_app_layout_not_legacy_breeze(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->has('mustVerifyEmail')
                ->has('status'));
    }

    public function test_legacy_breeze_components_are_gone_from_compiled_bundle(): void
    {
        // Verify the dead components no longer ship. This protects the
        // unification from accidental regressions and confirms that the
        // AuthenticatedLayout / GuestLayout / NavLink purge in T2-T3
        // actually took effect.
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath, 'Vite build manifest missing');

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertNotEmpty($manifest);

        $compiled = '';
        foreach ($manifest as $entry) {
            if (! is_array($entry) || empty($entry['file'])) {
                continue;
            }
            $path = public_path('build/' . $entry['file']);
            if (str_ends_with($path, '.js') && is_file($path)) {
                $compiled .= file_get_contents($path);
            }
        }

        $this->assertStringNotContainsString('AuthenticatedLayout', $compiled);
        $this->assertStringNotContainsString('GuestLayout', $compiled);
        $this->assertStringNotContainsString('NavLink', $compiled);

        // The TailAdmin theme CSS bundle is emitted.
        $cssFiles = array_filter(
            array_map(fn ($e) => $e['file'] ?? '', $manifest),
            fn ($f) => str_ends_with($f, '.css'),
        );

        $this->assertNotEmpty($cssFiles, 'No CSS bundle in manifest');
    }
}
