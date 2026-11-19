<?php

namespace App\Http\Requests\Core;

use App\Enums\ComplexPermission;
use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Models\Person;
use App\Services\PermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Actualizacion de una persona (spec §4.2, S39).
 *
 * La persona se resuelve dentro del conjunto activo verificado: un
 * identificador de otro conjunto no se resuelve y responde 404, sin filtrar
 * su existencia (ACE-C01). El permiso de edicion lo decide el catalogo
 * (`person.edit`).
 */
class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = self::resolvePerson($this);

        return PermissionService::has(
            $this->user(),
            ComplexPermission::PERSON_EDIT,
            $this->complexId()
        );
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'document_type' => ['sometimes', 'nullable', Rule::enum(TipoDocumento::class)],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'status' => ['sometimes', Rule::enum(PersonStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * El documento de identidad es unico por conjunto (indice unico parcial
     * cuando tipo y numero estan presentes): la edicion no puede chocar con
     * otra persona del mismo conjunto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $person = $this->person();

            $type = $this->input('document_type', $person->document_type?->value);
            $number = $this->input('document_number', $person->document_number);

            if (! is_string($type) || ! is_string($number) || $type === '' || $number === '') {
                return;
            }

            $duplicate = Person::withoutGlobalScope('complex')
                ->where('residential_complex_id', $this->complexId())
                ->where('document_type', $type)
                ->where('document_number', $number)
                ->whereKeyNot($person->getKey())
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('document_number', 'Ya existe otra persona con ese documento en el conjunto.');
            }
        });
    }

    /**
     * Resuelve la persona dentro del conjunto activo.
     *
     * El alias legacy `/api/v1/people/{persona}` no tiene middleware de
     * resolucion, asi que se resuelve aqui con el mismo criterio que usa la
     * superficie `/api/v1/core/*`: solo dentro del conjunto activo y por
     * `uuid`.
     */
    public static function resolvePerson(Request $request): Person
    {
        $person = $request->attributes->get('person');

        if ($person instanceof Person) {
            return $person;
        }

        $value = $request->route('persona') ?? $request->route('person');

        if (! is_string($value) || ! Str::isUuid($value)) {
            abort(404, 'Persona no encontrada.');
        }

        $complexId = (int) $request->attributes->get('complex_id');

        $person = Person::query()
            ->forComplex($complexId)
            ->where('uuid', $value)
            ->first();

        abort_if($person === null, 404, 'Persona no encontrada.');

        $request->attributes->set('person', $person);

        return $person;
    }

    /**
     * La persona resuelta en esta peticion.
     */
    protected function person(): Person
    {
        return self::resolvePerson($this);
    }

    /**
     * El conjunto activo verificado por el middleware.
     */
    protected function complexId(): int
    {
        return (int) $this->attributes->get('complex_id');
    }
}
