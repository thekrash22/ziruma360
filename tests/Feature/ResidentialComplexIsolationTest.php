<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Enums\SystemRole;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\ComplexContext;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentialComplexIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ComplexContext::clear();
        $this->seed(SystemRoleSeeder::class);
    }

    function createComplexWithMembership(string $email, string $complexName): array
    {
        $complex = ResidentialComplex::factory()->create([
            'name' => $complexName,
            'code' => strtolower($complexName) . '-' . uniqid(),
        ]);

        $user = User::factory()->create(['email' => $email]);
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $adminRole = Role::where('key', SystemRole::COMPLEX_ADMIN->value)->first();
        $membership->roles()->attach($adminRole->id);

        return compact('complex', 'user', 'membership');
    }

    public function test_user_from_complex_a_cannot_access_complex_b_data(): void
    {
        $complexAData = $this->createComplexWithMembership('user-a@test.com', 'Complex A');
        $complexBData = $this->createComplexWithMembership('user-b@test.com', 'Complex B');

        // User A sets complex A as active
        ComplexContext::setCurrentComplexId($complexAData['complex']->id);

        // User A should only see Complex A
        $userComplexes = $complexAData['user']->complexes()->get();
        $this->assertEquals(1, $userComplexes->count());
        $this->assertEquals($complexAData['complex']->id, $userComplexes->first()->id);

        // User A should not be admin of Complex B
        $this->assertFalse($complexAData['user']->isComplexAdmin($complexBData['complex']->id));

        // User B should only see Complex B
        $userBComplexes = $complexBData['user']->complexes()->get();
        $this->assertEquals(1, $userBComplexes->count());
        $this->assertEquals($complexBData['complex']->id, $userBComplexes->first()->id);
    }

    public function test_membership_suspended_user_cannot_access_complex(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'name' => 'Test Complex',
            'code' => 'test-complex-' . uniqid(),
        ]);

        $user = User::factory()->create(['email' => 'suspended@test.com']);
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::SUSPENDED,
        ]);

        $this->assertFalse($user->isMemberOf($complex->id));
        $this->assertFalse($membership->canAccess());
    }

    public function test_membership_revoked_user_cannot_access_complex(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'name' => 'Test Complex',
            'code' => 'test-complex-revoked-' . uniqid(),
        ]);

        $user = User::factory()->create(['email' => 'revoked@test.com']);
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::REVOKED,
        ]);

        $this->assertFalse($user->isMemberOf($complex->id));
        $this->assertFalse($membership->canAccess());
    }

    public function test_active_membership_user_can_access_complex(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'name' => 'Test Complex',
            'code' => 'test-complex-active-' . uniqid(),
        ]);

        $user = User::factory()->create(['email' => 'active@test.com']);
        $membership = ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $this->assertTrue($user->isMemberOf($complex->id));
        $this->assertTrue($membership->canAccess());
        $this->assertTrue($membership->isActive());
    }

    public function test_user_can_be_member_of_multiple_complexes(): void
    {
        $complexA = ResidentialComplex::factory()->create([
            'name' => 'Complex A Multi',
            'code' => 'complex-a-multi-' . uniqid(),
        ]);

        $complexB = ResidentialComplex::factory()->create([
            'name' => 'Complex B Multi',
            'code' => 'complex-b-multi-' . uniqid(),
        ]);

        $user = User::factory()->create(['email' => 'multi@test.com']);

        ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complexA->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complexB->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $this->assertEquals(2, $user->complexes()->count());
        $this->assertTrue($user->isMemberOf($complexA->id));
        $this->assertTrue($user->isMemberOf($complexB->id));
    }
}
