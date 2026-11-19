<?php

namespace App\Http\Requests\Core;

use App\Models\ResidentialComplex;
use App\Settings\ComplexSettingsCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de las politicas del conjunto (spec 1.4, RN-C04).
 *
 * El cuerpo de la peticion son las politicas: no hay JSON libre, cada clave
 * tiene tipo y restriccion en el catalogo.
 */
class UpdateComplexSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complex = $this->attributes->get('residential_complex');

        return $complex instanceof ResidentialComplex
            && Gate::allows('updateSettings', $complex);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ComplexSettingsCatalog::rules();
    }

    /**
     * El catalogo rechaza las claves y los grupos desconocidos.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $payload = Arr::except($this->all(), ['_token', '_method']);

            if ($payload === []) {
                $validator->errors()->add('settings', 'Indica al menos un grupo de politicas.');

                return;
            }

            try {
                ComplexSettingsCatalog::validate($payload);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $key => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($key, $message);
                    }
                }
            }
        });
    }
}
