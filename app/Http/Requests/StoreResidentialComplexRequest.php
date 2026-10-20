<?php

namespace App\Http\Requests;

use App\Enums\ResidentialComplexStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreResidentialComplexRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:residential_complexes,code'],
            'legal_identifier' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
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
            'name.required' => 'El nombre del conjunto es requerido.',
            'code.required' => 'El código es requerido.',
            'code.unique' => 'Este código ya está en uso.',
            'address.required' => 'La dirección es requerida.',
            'city.required' => 'La ciudad es requerida.',
        ];
    }
}
