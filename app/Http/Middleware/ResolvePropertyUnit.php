<?php

namespace App\Http\Middleware;

use App\Models\PropertyUnit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la unidad dentro del conjunto activo verificado.
 *
 * Un identificador que no pertenece al conjunto no se resuelve: la
 * respuesta es 404, sin filtrar si el recurso existe en otro conjunto
 * (ACE-C01).
 */
class ResolvePropertyUnit
{
    public function handle(Request $request, Closure $next): Response
    {
        $complexId = (int) $request->attributes->get('complex_id');
        $value = $request->route('propertyUnit');

        if (! is_string($value) || ! Str::isUuid($value)) {
            abort(404, 'Unidad no encontrada.');
        }

        $unit = PropertyUnit::query()
            ->forComplex($complexId)
            ->where('uuid', $value)
            ->first();

        abort_if($unit === null, 404, 'Unidad no encontrada.');

        $request->attributes->set('property_unit', $unit);

        return $next($request);
    }
}
