<?php

namespace Tests\Feature;

use App\Models\Persona;
use App\Models\PersonaUnidad;
use App\Models\ResidentialComplex;
use App\Models\ResidentialComplexMembership;
use App\Models\Unidad;
use App\Models\User;
use App\Services\ComplexContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * El legacy sigue activo hasta T17, asi que sus modelos deben apuntar a las
 * tablas que las migraciones legacy crearon en PostgreSQL. El plural derivado
 * por Eloquent no coincide con el plural en espanol de "unidad" ("unidads"),
 * por eso el contrato se fija de forma explicita.
 */
class LegacySchemaContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // El middleware legacy deja el conjunto activo en un estatico de
        // ComplexContext: sin limpiarlo filtra los tests siguientes por un
        // conjunto que ya no existe.
        ComplexContext::clear();

        parent::tearDown();
    }

    #[DataProvider('legacyModels')]
    public function test_the_legacy_model_resolves_the_migrated_table(string $model, string $table): void
    {
        $this->assertSame(
            $table,
            (new $model)->getTable(),
            "El modelo {$model} debe resolver la tabla migrada {$table}."
        );

        $this->assertTrue(
            Schema::hasTable($table),
            "La tabla {$table} debe existir en la base de datos."
        );
    }

    public static function legacyModels(): array
    {
        return [
            'unidades' => [Unidad::class, 'unidades'],
            'personas' => [Persona::class, 'personas'],
            'persona_unidad' => [PersonaUnidad::class, 'persona_unidad'],
        ];
    }

    public function test_the_legacy_units_listing_query_runs(): void
    {
        $this->assertCount(0, Unidad::query()->orderBy('created_at', 'desc')->limit(15)->get());
    }

    public function test_the_legacy_units_page_answers(): void
    {
        $user = User::factory()->create();
        $complex = ResidentialComplex::factory()->create();
        ResidentialComplexMembership::factory()->active()->create([
            'user_id' => $user->id,
            'residential_complex_id' => $complex->id,
        ]);

        $response = $this->actingAs($user)->get('/units');

        $response->assertOk();
        $response->assertSee('Units', false);
    }
}
