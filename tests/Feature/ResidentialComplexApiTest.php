<?php

namespace Tests\Feature;

use App\Enums\ResidentialComplexStatus;
use App\Models\ResidentialComplex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentialComplexApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_buildings(): void
    {
        ResidentialComplex::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/buildings');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'name',
                        'code',
                        'address',
                        'city',
                        'status',
                        'status_label',
                    ],
                ],
                'meta',
                'links',
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_buildings_by_status(): void
    {
        ResidentialComplex::factory()->create(['status' => ResidentialComplexStatus::DRAFT]);
        ResidentialComplex::factory()->create(['status' => ResidentialComplexStatus::ACTIVE]);
        ResidentialComplex::factory()->create(['status' => ResidentialComplexStatus::ACTIVE]);

        $response = $this->getJson('/api/v1/buildings?status=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_search_buildings_by_name_or_code(): void
    {
        ResidentialComplex::factory()->create(['name' => 'Torre Norte', 'code' => 'tn-001']);
        ResidentialComplex::factory()->create(['name' => 'Torre Sur', 'code' => 'ts-001']);
        ResidentialComplex::factory()->create(['name' => 'Edificio Este', 'code' => 'ee-001']);

        $response = $this->getJson('/api/v1/buildings?search=Torre');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_create_building(): void
    {
        $data = [
            'name' => 'Conjunto Residencial Las Américas',
            'code' => 'lasamericas-001',
            'address' => 'Calle 100 #45-67',
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country_code' => 'CO',
        ];

        $response = $this->postJson('/api/v1/buildings', $data);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Conjunto Residencial Las Américas',
                'code' => 'lasamericas-001',
                'city' => 'Bogotá',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('residential_complexes', [
            'name' => 'Conjunto Residencial Las Américas',
            'code' => 'lasamericas-001',
        ]);
    }

    public function test_create_building_requires_mandatory_fields(): void
    {
        $response = $this->postJson('/api/v1/buildings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'address', 'city']);
    }

    public function test_create_building_validates_unique_code(): void
    {
        ResidentialComplex::factory()->create(['code' => 'existing-code']);

        $data = [
            'name' => 'New Building',
            'code' => 'existing-code',
            'address' => 'Some Address',
            'city' => 'Some City',
        ];

        $response = $this->postJson('/api/v1/buildings', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_show_building(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'name' => 'Conjunto El Parque',
            'code' => 'parque-001',
        ]);

        $response = $this->getJson("/api/v1/buildings/{$complex->uuid}");

        $response->assertOk()
            ->assertJsonFragment([
                'uuid' => $complex->uuid,
                'name' => 'Conjunto El Parque',
                'code' => 'parque-001',
            ]);
    }

    public function test_can_update_building(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'status' => ResidentialComplexStatus::DRAFT,
        ]);

        $data = [
            'name' => 'Conjunto El Parque Actualizado',
        ];

        $response = $this->putJson("/api/v1/buildings/{$complex->uuid}", $data);

        $response->assertOk()
            ->assertJsonFragment([
                'uuid' => $complex->uuid,
                'name' => 'Conjunto El Parque Actualizado',
            ]);

        $this->assertDatabaseHas('residential_complexes', [
            'uuid' => $complex->uuid,
            'name' => 'Conjunto El Parque Actualizado',
        ]);
    }

    public function test_can_update_building_status(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'status' => ResidentialComplexStatus::DRAFT,
        ]);

        $response = $this->putJson("/api/v1/buildings/{$complex->uuid}", [
            'status' => 'active',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'status' => 'active',
                'status_label' => 'Activo',
            ]);
    }

    public function test_cannot_change_code_when_not_draft(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'status' => ResidentialComplexStatus::ACTIVE,
        ]);

        $response = $this->putJson("/api/v1/buildings/{$complex->uuid}", [
            'code' => 'new-code',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_delete_building(): void
    {
        $complex = ResidentialComplex::factory()->create([
            'status' => ResidentialComplexStatus::DRAFT,
        ]);

        $response = $this->deleteJson("/api/v1/buildings/{$complex->uuid}");

        $response->assertOk()
            ->assertJsonFragment(['message' => 'Conjunto eliminado exitosamente.']);

        $this->assertDatabaseMissing('residential_complexes', [
            'uuid' => $complex->uuid,
        ]);
    }

    public function test_cannot_delete_building_with_active_memberships(): void
    {
        $complex = ResidentialComplex::factory()->create();

        $user = \App\Models\User::factory()->create();
        \App\Models\ResidentialComplexMembership::factory()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
            'status' => \App\Enums\MembershipStatus::ACTIVE,
        ]);

        $response = $this->deleteJson("/api/v1/buildings/{$complex->uuid}");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'No se puede eliminar un conjunto con membresías activas.']);

        $this->assertDatabaseHas('residential_complexes', [
            'uuid' => $complex->uuid,
        ]);
    }

    public function test_returns_404_for_nonexistent_building(): void
    {
        $response = $this->getJson('/api/v1/buildings/nonexistent-uuid');

        $response->assertNotFound();
    }
}
