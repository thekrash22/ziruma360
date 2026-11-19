<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;

/**
 * Privacidad de porteria (spec §6 "Privacidad").
 *
 * "El rol de porteria no accede a datos personales fuera de su funcion": ve
 * la proyeccion minima (con quien tiene que validar un acceso) y nada de
 * los datos personales.
 */
class DoormanPrivacyTest extends CoreTestCase
{
    public function test_the_doorman_only_sees_the_minimal_projection(): void
    {
        $complex = $this->makeComplex('Torres');

        [$doorman] = $this->makeMember($complex, SystemRole::DOORMAN);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $response = $this->actingAs($doorman)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertOk();

        $payload = $response->json('data');

        $this->assertSame('Ana Torres', $payload['full_name']);
        $this->assertArrayHasKey('uuid', $payload);

        $this->assertArrayNotHasKey('document_number', $payload);
        $this->assertArrayNotHasKey('document_type', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('phone', $payload);
        $this->assertArrayNotHasKey('birth_date', $payload);
        $this->assertArrayNotHasKey('data_consent_at', $payload);
    }

    public function test_the_doorman_response_does_not_leak_the_personal_data(): void
    {
        $complex = $this->makeComplex('Torres');

        [$doorman] = $this->makeMember($complex, SystemRole::DOORMAN);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $response = $this->actingAs($doorman)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertOk();

        $response->assertDontSee($person->document_number);
        $response->assertDontSee((string) $person->email);
        $response->assertDontSee((string) $person->phone);
    }

    public function test_the_doorman_list_only_exposes_the_minimal_projection(): void
    {
        $complex = $this->makeComplex('Torres');

        [$doorman] = $this->makeMember($complex, SystemRole::DOORMAN);

        $this->makePerson($complex, null, 'Ana Torres');
        $this->makePerson($complex, null, 'Luis Paz');

        $response = $this->actingAs($doorman)
            ->getJson('/api/v1/core/people')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $entry) {
            $this->assertArrayHasKey('full_name', $entry);
            $this->assertArrayNotHasKey('document_number', $entry);
            $this->assertArrayNotHasKey('email', $entry);
        }
    }

    public function test_the_complex_admin_sees_the_full_projection(): void
    {
        $complex = $this->makeComplex('Torres');

        [$admin] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertOk();

        $payload = $response->json('data');

        $this->assertArrayHasKey('document_number', $payload);
        $this->assertSame($person->document_number, $payload['document_number']);
    }

    public function test_the_document_is_masked_without_the_explicit_permission(): void
    {
        // SEG-P01: el documento se muestra enmascarado salvo permiso
        // explicito. `person.view` no alcanza para verlo en claro.
        $complex = $this->makeComplex('Torres');

        [$owner] = $this->makeMember($complex, SystemRole::OWNER);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertOk();

        $document = (string) $person->document_number;
        $masked = (string) $response->json('data.document_number');

        $this->assertNotSame($document, $masked);
        $this->assertSame(str_repeat('*', max(strlen($document) - 4, 0)).substr($document, -4), $masked);
    }

    public function test_a_role_without_person_permissions_cannot_list_people(): void
    {
        $complex = $this->makeComplex('Torres');

        [$host] = $this->makeMember($complex, SystemRole::HOST);

        $this->actingAs($host)
            ->getJson('/api/v1/core/people')
            ->assertForbidden();
    }

    public function test_a_role_without_person_permissions_cannot_read_a_person(): void
    {
        $complex = $this->makeComplex('Torres');

        [$host] = $this->makeMember($complex, SystemRole::HOST);

        $person = $this->makePerson($complex, null, 'Ana Torres');

        $this->actingAs($host)
            ->getJson("/api/v1/core/people/{$person->uuid}")
            ->assertForbidden();
    }

    public function test_the_doorman_cannot_read_the_ownership_history(): void
    {
        $complex = $this->makeComplex('Torres');

        [$doorman] = $this->makeMember($complex, SystemRole::DOORMAN);

        $unit = $this->makeUnit($complex);
        $this->giveOwnership($complex, $unit, $this->makePerson($complex, null, 'Titular'));

        $this->actingAs($doorman)
            ->getJson("/api/v1/core/property-units/{$unit->uuid}/ownerships")
            ->assertForbidden();
    }
}
