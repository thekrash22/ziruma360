<?php

namespace App\Http\Requests\Core;

use App\Enums\OwnershipType;
use App\Models\PropertyUnit;
use App\Models\ResidentialComplex;
use App\Settings\ComplexSettingsCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Traspaso de titularidad (spec 4.3, RN-P05).
 *
 * En la matriz de permisos el propietario no gestiona la titularidad: solo
 * la solicita, asi que la Policy solo deja pasar al administrador del
 * conjunto.
 */
class StoreOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->attributes->get('property_unit');

        return $unit instanceof PropertyUnit && Gate::allows('transfer', $unit);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'person_uuid' => ['required', 'string', 'uuid'],
            'ownership_type' => ['required', Rule::enum(OwnershipType::class)],
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valid_from' => ['nullable', 'required_with:valid_until', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'document_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * La politica del conjunto puede exigir la referencia documental para
     * registrar la titularidad (grupo "titularidad", D-04).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $complex = $this->attributes->get('residential_complex');

            if (! $complex instanceof ResidentialComplex) {
                return;
            }

            $settings = ComplexSettingsCatalog::effective((array) $complex->settings);
            $level = $settings['ownership']['verification_level'] ?? 'reference';

            if ($level !== 'none' && ! $this->filled('document_reference')) {
                $validator->errors()->add(
                    'document_reference',
                    'La politica del conjunto exige la referencia documental de la titularidad.'
                );
            }
        });
    }
}
