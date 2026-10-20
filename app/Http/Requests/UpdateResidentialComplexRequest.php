<?php

namespace App\Http\Requests;

use App\Enums\ResidentialComplexStatus;
use App\Models\ResidentialComplex;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateResidentialComplexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $complexUuid = $this->route('building');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique('residential_complexes', 'code')->ignore($complexUuid, 'uuid'),
            ],
            'legal_identifier' => ['nullable', 'string', 'max:50'],
            'address' => ['sometimes', 'string', 'max:500'],
            'city' => ['sometimes', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country_code' => ['sometimes', 'string', 'size:2', 'uppercase'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'currency_code' => ['sometimes', 'string', 'size:3', 'uppercase'],
            'status' => ['sometimes', new Enum(ResidentialComplexStatus::class)],
            'settings' => ['sometimes', 'array'],
            'settings.*' => ['nullable'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Este código ya está en uso.',
        ];
    }
}
