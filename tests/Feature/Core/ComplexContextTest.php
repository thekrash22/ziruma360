<?php

namespace Tests\Feature\Core;

use App\Enums\SystemRole;
use App\Http\Middleware\ResolveActiveComplex;
use App\Models\Building;
use App\Models\PropertyUnit;
use App\Services\ActiveComplexResolver;
use App\Services\ComplexContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Aislamiento del contexto de conjunto activo (spec S58-S60).
 *
 * El conjunto activo pertenece a la peticion y a la sesion del usuario: no
 * puede quedar disponible en la peticion siguiente, porque en un worker
 * persistente eso deja al ambito global filtrando por el conjunto de otra
 * persona.
 */
class ComplexContextTest extends CoreTestCase
{
    public function test_active_complex_is_not_inherited_by_the_next_request(): void
    {
        $complex = $this->makeComplex('Conjunto Alfa');

        ComplexContext::setCurrentComplexId($complex->id);

        $this->assertSame($complex->id, ComplexContext::getCurrentComplexId());
        $this->assertSame($complex->id, session('active_residential_complex_id'));

        // La peticion siguiente arranca con su propia sesion.
        $this->flushSession();

        $this->assertNull(ComplexContext::getCurrentComplexId());
    }

    public function test_global_scope_does_not_filter_by_the_complex_of_a_previous_request(): void
    {
        $complexA = $this->makeComplex('Conjunto Alfa');
        $complexB = $this->makeComplex('Conjunto Bravo');
        $this->makeUnit($complexA);
        $this->makeUnit($complexB);
        [$user] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);

        $this->handleComplexRequest($user);

        // Sin contexto activo, el ambito global no puede seguir filtrando por
        // el conjunto de la peticion anterior: se ven las unidades de ambos.
        $this->flushSession();

        $this->assertSame(2, PropertyUnit::count());
    }

    public function test_resolve_active_complex_publishes_the_verified_complex_in_the_session(): void
    {
        $complex = $this->makeComplex('Conjunto Alfa');
        [$user] = $this->makeMember($complex, SystemRole::COMPLEX_ADMIN);

        $request = $this->handleComplexRequest($user);

        $this->assertSame($complex->id, ComplexContext::getCurrentComplexId());
        $this->assertSame($complex->id, session('active_residential_complex_id'));
        $this->assertSame($complex->id, $request->attributes->get('complex_id'));
    }

    public function test_a_second_user_does_not_inherit_the_complex_of_the_first_request(): void
    {
        $complexA = $this->makeComplex('Conjunto Alfa');
        $complexB = $this->makeComplex('Conjunto Bravo');

        Building::factory()->for($complexA, 'residentialComplex')->create(['name' => 'TORRE ALFA']);
        Building::factory()->for($complexB, 'residentialComplex')->create(['name' => 'TORRE BRAVO']);

        [$userA] = $this->makeMember($complexA, SystemRole::COMPLEX_ADMIN);
        [$userB] = $this->makeMember($complexB, SystemRole::COMPLEX_ADMIN);

        // Primera peticion: la API resuelve y publica el conjunto del actor.
        $this->actingAs($userA)->getJson('/api/v1/core/property-units')->assertOk();

        // Segunda peticion: otra persona, con su propia sesion y su propia cookie.
        $this->flushSession();

        $response = $this->actingAs($userB)->get('/buildings');

        $response->assertOk();
        $response->assertSee('TORRE BRAVO');
        $response->assertDontSee('TORRE ALFA');
    }

    /**
     * Ejecuta el middleware de contexto como lo haria una peticion real.
     */
    private function handleComplexRequest(Authenticatable $user): Request
    {
        $request = Request::create('/api/v1/core/property-units', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new ResolveActiveComplex(new ActiveComplexResolver);
        $middleware->handle($request, fn () => new Response);

        return $request;
    }
}
