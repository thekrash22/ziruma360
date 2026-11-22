<?php

namespace App\Actions\Complex;

use App\Models\ResidentialComplex;
use App\Models\User;
use App\Services\ComplexContext;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Fija el conjunto activo del actor (spec S93).
 *
 * La eleccion del conjunto activo es explicita: el actor elige el conjunto
 * con el que va a trabajar y esa eleccion vive en la sesion
 * (`ComplexContext`). Solo se puede elegir un conjunto del que el actor es
 * **miembro activo**: un conjunto ajeno o sin membresia activa no se
 * resuelve (404), sin filtrar su existencia (ACE-C01).
 *
 * La logica vive aqui y no en el controlador porque la usan dos superficies:
 * la API (`POST /api/v1/complexes/{complex}/select`) y la web.
 */
class SelectActiveComplex
{
    /**
     * Fija el conjunto activo a partir del conjunto ya resuelto.
     *
     * @throws NotFoundHttpException si el actor no es miembro activo.
     */
    public function execute(User $user, ResidentialComplex $complex): ResidentialComplex
    {
        if (! $user->isMemberOf((int) $complex->id)) {
            throw new NotFoundHttpException('Conjunto no encontrado.');
        }

        ComplexContext::setCurrentComplexId((int) $complex->id);

        // La eleccion ya esta hecha: el aviso de "hay que elegir un
        // conjunto" (spec S93) deja de ser un callejon sin salida.
        if (app()->bound('session')) {
            Session::forget('complex_selection_required');
        }

        return $complex;
    }

    /**
     * Fija el conjunto activo a partir de su uuid.
     *
     * Un uuid que no existe y un conjunto del que el actor no es miembro
     * activo responden igual: 404, sin filtrar la existencia del conjunto.
     *
     * @throws NotFoundHttpException si el conjunto no existe o el actor no
     *                               es miembro activo.
     */
    public function select(User $user, string $uuid): ResidentialComplex
    {
        if (! Str::isUuid($uuid)) {
            throw new NotFoundHttpException('Conjunto no encontrado.');
        }

        $complex = ResidentialComplex::query()->where('uuid', $uuid)->first();

        if (! $complex instanceof ResidentialComplex) {
            throw new NotFoundHttpException('Conjunto no encontrado.');
        }

        return $this->execute($user, $complex);
    }
}
