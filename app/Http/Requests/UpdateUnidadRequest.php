<?php

namespace App\Http\Requests;

use App\Enums\UnidadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateUnidadRequest extends FormRequest
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
            'residential_complex_id' => ['sometimes', 'integer', 'exists:residential_complexes,id'],
            'numero' => ['sometimes', 'string', 'max:50'],
            'piso' => ['nullable', 'string', 'max:20'],
            'torre' => ['nullable', 'string', 'max:50'],
            'tipo' => ['sometimes', 'string', 'max:50'],
            'area_m2' => ['nullable', 'integer', 'min:0'],
            'habitaciones' => ['nullable', 'integer', 'min:0'],
            'banos' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', new Enum(UnidadStatus::class)],
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
            'residential_complex_id.exists' => 'El conjunto residencial seleccionado no existe.',
        ];
    }
}
