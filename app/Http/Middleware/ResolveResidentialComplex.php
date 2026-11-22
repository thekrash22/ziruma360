<?php

namespace App\Http\Middleware;

use App\Models\ResidentialComplex;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve el conjunto residencial de la ruta dentro del alcance del actor.
 *
 * El alcance se resuelve en el servidor contra las membresias activas del
 * actor (o el catalogo completo para el administrador de plataforma). Un
 * conjunto fuera del alcance no se resuelve: la respuesta es 404, sin
 * filtrar si el recurso existe en otro conjunto (ACE-C01).
 */
class ResolveResidentialComplex
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401, 'No autenticado.');

        $value = $request->route('complex');

        if (! is_string($value) || ! Str::isUuid($value)) {
            abort(404, 'Conjunto no encontrado.');
        }

        $query = ResidentialComplex::query()->where('uuid', $value);

        if (! $user->isPlatformAdmin()) {
            $query->whereIn(
                'id',
                $user->complexes()->distinct()->pluck('residential_complexes.id')
            );
        }

        $complex = $query->first();

        abort_if($complex === null, 404, 'Conjunto no encontrado.');

        $request->attributes->set('residential_complex', $complex);

        return $next($request);
    }
}
