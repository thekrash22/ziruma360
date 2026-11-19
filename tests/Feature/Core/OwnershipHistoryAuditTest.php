<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\PropertyUnitOwnership;

/**
 * Historial de titularidad y su auditoria (spec §4.3, RN-P05, §6
 * "Personas": "Los cambios de titularidad generan historial y auditoria").
 */
class OwnershipHistoryAuditTest extends CoreTestCase
{
    public function test_a_transfer_closes_the_current_ownership_and_opens_a_new_one(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex);
        $first = $this->makePerson($complex, null, 'Titular Uno');
        $second = $this->makePerson($complex, null, 'Titular Dos');

        $current = $this->giveOwnership($complex, $unit, $first);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/property-units/{$unit->uuid}/ownerships", [
                'person_uuid' => $second->uuid,
                'ownership_type' => 'owner',
                'percentage' => 100,
                'document_reference' => 'Escritura 123',
            ])
            ->assertCreated();

        $current->refresh();

        $this->assertSame('closed', $current->status->value);
        $this->assertNotNull($current->valid_until);

        $ownerships = $unit->ownerships()->orderBy('id')->get();

        $this->assertCount(2, $ownerships);
        $this->assertSame('active', $ownerships->last()->status->value);
        $this->assertSame($second->id, $ownerships->last()->person_id);
    }

    public function test_the_history_keeps_both_ownerships(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex);
        $first = $this->makePerson($complex, null, 'Titular Uno');
        $second = $this->makePerson($complex, null, 'Titular Dos');

        $this->giveOwnership($complex, $unit, $first);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/property-units/{$unit->uuid}/ownerships", [
                'person_uuid' => $second->uuid,
                'ownership_type' => 'owner',
                'percentage' => 100,
                'document_reference' => 'Escritura 123',
            ])
            ->assertCreated();

        $history = $this->actingAs($admin)
            ->getJson("/api/v1/core/property-units/{$unit->uuid}/ownerships")
            ->assertOk()
            ->json('data');

        $this->assertCount(2, $history);
        $this->assertSame($second->uuid, $history[0]['person']['uuid']);
        $this->assertSame('active', $history[0]['status']);
        $this->assertSame($first->uuid, $history[1]['person']['uuid']);
        $this->assertSame('closed', $history[1]['status']);
    }

    public function test_the_ownership_change_is_audited_with_the_actor_and_the_before_and_after_values(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex);
        $first = $this->makePerson($complex, null, 'Titular Uno');
        $second = $this->makePerson($complex, null, 'Titular Dos');

        $current = $this->giveOwnership($complex, $unit, $first);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/property-units/{$unit->uuid}/ownerships", [
                'person_uuid' => $second->uuid,
                'ownership_type' => 'owner',
                'percentage' => 100,
                'document_reference' => 'Escritura 123',
            ])
            ->assertCreated();

        $closure = AuditLog::query()
            ->where('entity_type', PropertyUnitOwnership::class)
            ->where('action', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($closure);
        $this->assertSame($admin->id, (int) $closure->actor_user_id);
        $this->assertSame($complex->id, (int) $closure->residential_complex_id);
        $this->assertSame((string) $current->id, $closure->entity_id);
        $this->assertSame('active', $closure->before_data['status']);
        $this->assertSame('closed', $closure->after_data['status']);
        $this->assertNotNull($closure->occurred_at);
    }

    public function test_the_new_ownership_is_audited_as_created(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex);
        $first = $this->makePerson($complex, null, 'Titular Uno');
        $second = $this->makePerson($complex, null, 'Titular Dos');

        $this->giveOwnership($complex, $unit, $first);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/property-units/{$unit->uuid}/ownerships", [
                'person_uuid' => $second->uuid,
                'ownership_type' => 'owner',
                'percentage' => 100,
                'document_reference' => 'Escritura 123',
            ])
            ->assertCreated();

        $creation = AuditLog::query()
            ->where('entity_type', PropertyUnitOwnership::class)
            ->where('action', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($creation);
        $this->assertSame($admin->id, (int) $creation->actor_user_id);
        $this->assertSame($second->id, (int) $creation->after_data['person_id']);
        $this->assertSame('active', $creation->after_data['status']);
    }

    public function test_an_owner_cannot_transfer_the_ownership(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $unit = $this->makeUnit($complex);
        $first = $this->makePerson($complex, $owner, 'Titular Uno');
        $second = $this->makePerson($complex, null, 'Titular Dos');

        $this->giveOwnership($complex, $unit, $first);

        $this->actingAs($owner)
            ->postJson("/api/v1/core/property-units/{$unit->uuid}/ownerships", [
                'person_uuid' => $second->uuid,
                'ownership_type' => 'owner',
                'percentage' => 100,
                'document_reference' => 'Escritura 123',
            ])
            ->assertForbidden();

        $this->assertSame(1, $unit->ownerships()->count());
    }

    public function test_a_transfer_to_a_person_of_another_complex_is_rejected(): void
    {
        $complex = $this->makeComplex('Torres');
        $otherComplex = $this->makeComplex('Otro Conjunto');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $unit = $this->makeUnit($complex);
        $first = $this->makePerson($complex, null, 'Titular Uno');
        $foreign = $this->makePerson($otherComplex, null, 'Titular Extranjero');

        $this->giveOwnership($complex, $unit, $first);

        $this->actingAs($admin)
            ->postJson("/api/v1/core/property-units/{$unit->uuid}/ownerships", [
                'person_uuid' => $foreign->uuid,
                'ownership_type' => 'owner',
                'percentage' => 100,
                'document_reference' => 'Escritura 123',
            ])
            ->assertStatus(422);

        $this->assertSame(1, $unit->ownerships()->count());
    }

    public function test_an_owner_cannot_read_the_ownership_history(): void
    {
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $unit = $this->makeUnit($complex);
        $person = $this->makePerson($complex, $owner, 'Titular');

        $this->giveOwnership($complex, $unit, $person);

        $this->actingAs($owner)
            ->getJson("/api/v1/core/property-units/{$unit->uuid}/ownerships")
            ->assertForbidden();
    }
}
