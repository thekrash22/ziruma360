<?php

namespace Tests\Feature;

use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Models\Person;
use App\Models\ResidentialComplex;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeopleTest extends TestCase
{
    use RefreshDatabase;

    protected ResidentialComplex $complex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->complex = ResidentialComplex::factory()->create();
    }

    // ==================== Creación ====================

    public function test_can_create_person_with_auto_generated_uuid(): void
    {
        $person = Person::factory()->for($this->complex)->create([
            'full_name' => 'Ana Gómez',
        ]);

        $this->assertNotNull($person->uuid);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $person->uuid
        );

        $this->assertDatabaseHas('people', [
            'uuid' => $person->uuid,
            'full_name' => 'Ana Gómez',
        ]);
    }

    public function test_person_belongs_to_complex(): void
    {
        $person = Person::factory()->for($this->complex)->create();

        $this->assertEquals($this->complex->id, $person->residential_complex_id);
        $this->assertEquals($this->complex->id, $person->residentialComplex->id);
    }

    public function test_person_can_be_linked_to_a_user(): void
    {
        $user = User::factory()->create();

        $person = Person::factory()->for($this->complex)->create([
            'user_id' => $user->id,
        ]);

        $this->assertTrue($person->user->is($user));
    }

    public function test_person_user_is_optional(): void
    {
        $person = Person::factory()->for($this->complex)->create([
            'user_id' => null,
        ]);

        $this->assertNull($person->user);
        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'user_id' => null,
        ]);
    }

    // ==================== Unicidad del documento (RN-P02) ====================

    public function test_document_is_unique_per_complex(): void
    {
        Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => '1010101010',
        ]);

        $this->expectException(QueryException::class);

        Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => '1010101010',
        ]);
    }

    public function test_same_document_is_allowed_in_another_complex(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();

        Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => '2020202020',
        ]);

        $person = Person::factory()->for($otherComplex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => '2020202020',
        ]);

        $this->assertNotNull($person->id);
        $this->assertEquals($otherComplex->id, $person->residential_complex_id);
    }

    public function test_null_document_number_is_allowed_multiple_times(): void
    {
        Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => null,
        ]);

        $person = Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => null,
        ]);

        $this->assertNotNull($person->id);
    }

    public function test_null_document_type_is_allowed_multiple_times(): void
    {
        Person::factory()->for($this->complex)->create([
            'document_type' => null,
            'document_number' => '3030303030',
        ]);

        $person = Person::factory()->for($this->complex)->create([
            'document_type' => null,
            'document_number' => '3030303030',
        ]);

        $this->assertNotNull($person->id);
    }

    // ==================== Enums ====================

    public function test_document_type_and_status_are_stored_as_enums(): void
    {
        $person = Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::PASAPORTE,
            'status' => PersonStatus::INACTIVE,
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'document_type' => 'PASAPORTE',
            'status' => 'inactive',
        ]);

        $this->assertInstanceOf(TipoDocumento::class, $person->document_type);
        $this->assertInstanceOf(PersonStatus::class, $person->status);
        $this->assertSame(TipoDocumento::PASAPORTE, $person->document_type);
        $this->assertSame(PersonStatus::INACTIVE, $person->status);
    }

    // ==================== Deduplicación (RN-P02) ====================

    public function test_find_by_document_returns_the_existing_person(): void
    {
        $person = Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CE,
            'document_number' => 'CE-4040',
        ]);

        $found = Person::findByDocument($this->complex, TipoDocumento::CE, 'CE-4040');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($person));
    }

    public function test_find_by_document_returns_null_when_the_document_does_not_exist(): void
    {
        Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CE,
            'document_number' => 'CE-4040',
        ]);

        $this->assertNull(Person::findByDocument($this->complex, TipoDocumento::CE, 'CE-9999'));
    }

    public function test_for_document_scope_filters_by_type_and_number(): void
    {
        $person = Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CE,
            'document_number' => 'CE-5050',
        ]);

        Person::factory()->for($this->complex)->create([
            'document_type' => TipoDocumento::CC,
            'document_number' => 'CE-5050',
        ]);

        $results = Person::forComplex($this->complex)
            ->forDocument(TipoDocumento::CE, 'CE-5050')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($person));
    }

    // ==================== Aislamiento multi-tenant ====================

    public function test_person_is_not_visible_from_another_complex(): void
    {
        $otherComplex = ResidentialComplex::factory()->create();

        $person = Person::factory()->for($this->complex)->create();

        $this->assertNull(
            Person::forComplex($otherComplex)->find($person->id)
        );

        $this->assertNotNull(
            Person::forComplex($this->complex)->find($person->id)
        );
    }
}
