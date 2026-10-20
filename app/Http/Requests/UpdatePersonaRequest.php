<?php

namespace App\Http\Requests;

use App\Enums\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePersonaRequest extends FormRequest
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
        $personaId = $this->route('persona')?->id;

        return [
            'nombre' => ['sometimes', 'string', 'max:100'],
            'apellido' => ['sometimes', 'string', 'max:100'],
            'tipo_documento' => ['sometimes', new Enum(TipoDocumento::class)],
            'numero_documento' => [
                'sometimes',
                'string',
                'max:50',
                function ($attribute, $value, $fail) {
                    $tipo = $this->input('tipo_documento');
                    if ($tipo && $tipo !== 'PASAPORTE' && $tipo !== 'NIT') {
                        if (!preg_match('/^[0-9]+$/', $value)) {
                            $fail('El número de documento debe ser numérico para este tipo de documento.');
                        }
                    }
                },
            ],
            'email' => ['nullable', 'email', 'max:255', 'unique:personas,email,' . $personaId],
            'telefono' => ['nullable', 'string', 'max:20'],
            'telefono_alternativo' => ['nullable', 'string', 'max:20'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'unique:personas,user_id,' . $personaId],
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
            'email.email' => 'El correo electrónico no es válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'user_id.unique' => 'Esta cuenta de usuario ya está vinculada a otra persona.',
        ];
    }
}
