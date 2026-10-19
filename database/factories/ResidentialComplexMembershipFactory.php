<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentialComplexMembership>
 */
class ResidentialComplexMembershipFactory extends Factory
{
    protected $model = ResidentialComplexMembership::class;

    public function definition(): array
    {
        return [
            'residential_complex_id' => ResidentialComplex::factory(),
            'user_id' => User::factory(),
            'status' => MembershipStatus::INVITED,
            'invited_by' => null,
            'invited_at' => now(),
            'joined_at' => null,
            'last_active_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::SUSPENDED,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MembershipStatus::REVOKED,
        ]);
    }
}
