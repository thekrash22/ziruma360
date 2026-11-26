<?php

namespace App\Http\Requests;

use App\Enums\ResidentialComplexStatus;
use App\Models\ResidentialComplex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateResidentialComplexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * El conjunto ya viene resuelto y acotado al alcance del actor por el
     * middleware; la escritura exige `complex.edit` sobre ese conjunto. Los
     * datos de gobierno (`settings` y `legal_identifier`) exigen ademas su
     * propio permiso: editar el conjunto no habilita a reescribirlos.
     *
     * El estado del conjunto es un atributo de plataforma (spec S65): un
     * cambio de estado exige `changeStatus`, que solo concede la plataforma.
     */
    public function authorize(): bool
    {
        $complex = $this->attributes->get('residential_complex');

        if (! $complex instanceof ResidentialComplex || ! Gate::allows('update', $complex)) {
            return false;
        }

        if ($this->touchesStatus() && ! $this->mayChangeStatus($complex)) {
            return false;
        }

        if ($this->touchesGovernance()) {
            return Gate::allows('updateGovernance', $complex);
        }

        return true;
    }

    /**
     * El payload propone un estado.
     */
    private function touchesStatus(): bool
    {
        return $this->has('status');
    }

    /**
     * El estado propuesto es un caso valido del enum y el actor puede
     * cambiarlo.
     *
     * Un valor que no es un caso del enum no es un cambio de estado: lo
     * responde la validacion de reglas (422), nunca una excepcion.
     */
    private function mayChangeStatus(ResidentialComplex $complex): bool
    {
        $target = $this->requestedStatus();

        if (! $target instanceof ResidentialComplexStatus) {
            return true;
        }

        return Gate::allows('changeStatus', [$complex, $target]);
    }

    /**
     * Estado propuesto en el payload, si es un caso valido del enum.
     */
    private function requestedStatus(): ?ResidentialComplexStatus
    {
        $value = $this->input('status');

        if (! is_string($value)) {
            return null;
        }

        return ResidentialComplexStatus::tryFrom($value);
    }

    /**
     * El payload escribe datos de gobierno del conjunto.
     */
    private function touchesGovernance(): bool
    {
        return $this->has('settings') || $this->has('legal_identifier');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $complexUuid = $this->route('complex');

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
