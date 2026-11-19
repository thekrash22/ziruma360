<?php

namespace App\Http\Middleware;

use App\Models\ResidentialComplex;
use App\Services\ActiveComplexResolver;
use App\Services\ComplexContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Contexto de conjunto verificado en el servidor (spec S9, ACE-C01).
 *
 * El conjunto se resuelve contra las membresías activas del actor y el
 * resultado se publica en la peticion: los controladores y las policies
 * trabajan con un conjunto verificado, nunca con uno elegido por el cliente.
 */
class ResolveActiveComplex
{
    public function __construct(private readonly ActiveComplexResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $complexId = $this->resolver->resolve($request);

        // El contexto activo pasa a ser el conjunto verificado: el ambito
        // global de `BelongsToComplex` deja de depender de lo que el
        // cliente haya pedido.
        ComplexContext::setCurrentComplexId($complexId);

        $request->attributes->set('complex_id', $complexId);
        $request->attributes->set('residential_complex', ResidentialComplex::find($complexId));

        return $next($request);
    }
}
