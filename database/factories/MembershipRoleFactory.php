<?php

namespace Database\Factories;

use App\Models\MembershipRole;
use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipRole>
 */
class MembershipRoleFactory extends Factory
{
    protected $model = MembershipRole::class;

    public function definition(): array
    {
        return [
            'membership_id' => ResidentialComplexMembership::factory(),
            'role_id' => Role::factory(),
            'valid_from' => null,
            'valid_until' => null,
        ];
    }

    public function forMembership(ResidentialComplexMembership $membership): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_id' => $membership->id,
        ]);
    }

    public function forRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $role->id,
        ]);
    }

    public function validFromNow(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now(),
            'valid_until' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->subDays(10),
            'valid_until' => now()->subDays(1),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->addDays(1),
            'valid_until' => null,
        ]);
    }

    public function withDateRange(\DateTimeInterface $from, \DateTimeInterface $until): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => $from,
            'valid_until' => $until,
        ]);
    }
}
