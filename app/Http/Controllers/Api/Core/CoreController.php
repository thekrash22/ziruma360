<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Base de la superficie /api/v1/core.
 *
 * El conjunto activo ya viene verificado por el middleware
 * ResolveActiveComplex; los recursos se resuelven dentro de ese conjunto,
 * de modo que un recurso de otro conjunto no se resuelve (404) y no se
 * filtra su existencia (ACE-C01).
 */
abstract class CoreController extends Controller
{
    protected function complexId(Request $request): int
    {
        return (int) $request->attributes->get('complex_id');
    }

    protected function resolvePerson(Request $request, string $value): Person
    {
        if (! Str::isUuid($value)) {
            abort(404, 'Persona no encontrada.');
        }

        $person = Person::query()
            ->forComplex($this->complexId($request))
            ->where('uuid', $value)
            ->first();

        abort_if($person === null, 404, 'Persona no encontrada.');

        return $person;
    }
}
