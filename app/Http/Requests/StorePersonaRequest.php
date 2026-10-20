<?php

namespace App\Http\Requests;

use App\Enums\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StorePersonaRequest extends FormRequest
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
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'tipo_documento' => ['required', new Enum(TipoDocumento::class)],
            'numero_documento' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $tipo = $this->input('tipo_documento');
                    if ($tipo && $tipo !== 'PASAPORTE' && $tipo !== 'NIT') {
                        // Colombian documents should be numeric for CC, CE, TI, PEP, PPT
                        if (!preg_match('/^[0-9]+$/', $value)) {
                            $fail('El número de documento debe ser numérico para este tipo de documento.');
                        }
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255', 'unique:personas,email'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'telefono_alternativo' => ['nullable', 'string', 'max:20'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:personas,user_id'],
            'notas' => ['nullable', 'string', 'max:1000'],
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
            'nombre.required' => 'El nombre es requerido.',
            'apellido.required' => 'El apellido es requerido.',
            'tipo_documento.required' => 'El tipo de documento es requerido.',
            'numero_documento.required' => 'El número de documento es requerido.',
            'numero_documento.unique' => 'Ya existe una persona con este número de documento.',
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'user_id.unique' => 'Esta cuenta de usuario ya está vinculada a otra persona.',
        ];
    }
}
