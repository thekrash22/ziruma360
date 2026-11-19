<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Requests\Core\UpdateComplexSettingsRequest;
use App\Models\ResidentialComplex;
use App\Settings\ComplexSettingsCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Politicas propias del conjunto (spec §1.4, RN-C04).
 */
class ComplexSettingsController extends CoreController
{
    public function show(Request $request): JsonResponse
    {
        /** @var ResidentialComplex $complex */
        $complex = $request->attributes->get('residential_complex');

        abort_unless(
            Gate::allows('viewSettings', $complex),
            403,
            'No tienes permiso para consultar las politicas del conjunto.'
        );

        return response()->json([
            'settings' => ComplexSettingsCatalog::effective((array) $complex->settings),
        ]);
    }

    public function update(UpdateComplexSettingsRequest $request): JsonResponse
    {
        /** @var ResidentialComplex $complex */
        $complex = $request->attributes->get('residential_complex');

        // El cambio es un PATCH por grupos: lo que no viene en la peticion
        // conserva su valor, y lo que viene se valida contra el catalogo
        // (el modelo rechaza cualquier clave fuera del catalogo).
        $current = ComplexSettingsCatalog::onlyKnown((array) $complex->settings);

        $complex->update([
            'settings' => array_replace_recursive($current, $request->validated()),
        ]);

        return response()->json([
            'settings' => ComplexSettingsCatalog::effective((array) $complex->fresh()->settings),
        ]);
    }
}
