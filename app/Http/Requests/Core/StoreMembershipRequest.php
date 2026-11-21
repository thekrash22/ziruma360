<?php

namespace App\Http\Requests\Core;

use App\Enums\TipoDocumento;
use App\Models\Person;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Invitacion de una persona del conjunto a la superficie de membresias
 * (spec S74).
 *
 * El conjunto activo lo resuelve el servidor (S9): el cliente nunca lo
 * envia. La escritura la exige `complex.manage_members` dentro del conjunto.
 *
 * Las reglas de negocio de la invitacion tambien se comprueban aqui, para
 * que un rechazo llegue como 422 con un mensaje que dice que falta y sin
 * tocar la base de datos:
 *
 * - la persona se localiza por su documento DENTRO del conjunto (RN-P02: si
 *   ya existe, se reutiliza la existente y no se duplica);
 * - la cuenta se localiza por su correo;
 * - una persona ya vinculada a otra cuenta no se puede invitar de nuevo;
 * - una membresia que ya existe para el par (conjunto, usuario) no se
 *   duplica.
 */
class StoreMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('invite', [
            ResidentialComplexMembership::class,
            $this->complexId(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', Rule::enum(TipoDocumento::class)],
            'document_number' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email', 'max:255'],
        ];
    }

    /**
     * Las reglas de negocio de la invitacion (S74).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $person = $this->person();

            if ($person === null) {
                $validator->errors()->add(
                    'document_number',
                    'No existe ninguna persona con ese documento en el conjunto.'
                );

                return;
            }

            $account = $this->account();

            if ($account === null) {
                $validator->errors()->add(
                    'email',
                    'No existe ninguna cuenta de usuario con ese correo.'
                );

                return;
            }

            if ($person->user_id !== null && (int) $person->user_id !== (int) $account->id) {
                $validator->errors()->add(
                    'email',
                    'La persona ya está vinculada a otra cuenta de usuario.'
                );

                return;
            }

            if ($this->membershipExists($account)) {
                $validator->errors()->add(
                    'email',
                    'Esta cuenta ya tiene una membresía en el conjunto.'
                );
            }
        });
    }

    /**
     * La persona del conjunto con ese documento, si existe (RN-P02).
     */
    public function person(): ?Person
    {
        $documentType = $this->input('document_type');
        $documentNumber = $this->input('document_number');

        if (! is_string($documentType) || ! is_string($documentNumber)) {
            return null;
        }

        return Person::findByDocument($this->complexId(), $documentType, $documentNumber);
    }

    /**
     * La cuenta con ese correo, si existe.
     */
    public function account(): ?User
    {
        $email = $this->input('email');

        if (! is_string($email)) {
            return null;
        }

        return User::query()->where('email', $email)->first();
    }

    /**
     * Ya existe una membresia para el par (conjunto, usuario).
     */
    protected function membershipExists(User $account): bool
    {
        return ResidentialComplexMembership::query()
            ->where('residential_complex_id', $this->complexId())
            ->where('user_id', $account->id)
            ->exists();
    }

    /**
     * El conjunto activo verificado por el middleware.
     */
    protected function complexId(): int
    {
        return (int) $this->attributes->get('complex_id');
    }
}
