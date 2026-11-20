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
 *
 * Guarda de escritura del documento de identidad (SEG-P01): el documento de
 * identidad es el PAR (`document_type`, `document_number`), no el numero
 * suelto, y escribir cualquiera de los dos campos exige el permiso explicito
 * `person.view_document`, el mismo que lo expone en claro. Sin el, la
 * peticion se rechaza con 403 y no modifica nada. Tambien es un intento de
 * escritura (borrado) anular cualquiera de los dos campos de una persona que
 * ya tiene documento.
 *
 * Atomicidad del documento (RN-P02, T17): el documento es completo o no
 * existe; un tipo sin numero (o un numero sin tipo) se rechaza con 422 en la
 * aplicacion, antes de que la base de datos lo rechace con su CHECK.
 */
class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = self::resolvePerson($this);

        if ($this->user() === null) {
            return false;
        }

        if (! PermissionService::has(
            $this->user(),
            ComplexPermission::PERSON_EDIT,
            $this->complexId()
        )) {
            return false;
        }

        $this->assertCanWriteIdentityDocument();

        return true;
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
     * Los datos validados, sin el documento de identidad cuando el actor no
     * tiene el permiso explicito: sin el, el documento que ya tiene la
     * persona se conserva (no se escribe ni se borra) y el campo prohibido
     * nunca llega al `fill()`.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        if ($key !== null || ! is_array($validated)) {
            return $validated;
        }

        if (! $this->canWriteIdentityDocument()) {
            unset($validated['document_type'], $validated['document_number']);
        }

        return $validated;
    }

    /**
     * Atomicidad del documento de identidad y unicidad por conjunto.
     *
     * El documento es el par (`document_type`, `document_number`): tipo y
     * numero van juntos o no hay documento, y el par es unico por conjunto
     * (indice unico parcial), de modo que la edicion no puede chocar con
     * otra persona del mismo conjunto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $person = $this->person();

            // Valores efectivos: lo que trae la peticion y, para el campo que
            // no viene, lo que la persona ya tiene almacenado.
            $type = $this->has('document_type')
                ? $this->input('document_type')
                : $person->document_type?->value;

            $number = $this->has('document_number')
                ? $this->input('document_number')
                : $person->document_number;

            $hasType = $this->documentFieldHasValue($type);
            $hasNumber = $this->documentFieldHasValue($number);

            // Atomicidad del documento: tipo y numero van juntos. Cuando la
            // peticion anula uno de los dos, el otro tiene que anularse
            // tambien (el documento se borra entero o no se borra).
            if ($hasType && ! $hasNumber) {
                if ($this->has('document_number')) {
                    $validator->errors()->add(
                        'document_type',
                        'Para dejar el documento de identidad vacio, anula tambien el tipo de documento.'
                    );
                } else {
                    $validator->errors()->add(
                        'document_number',
                        'El numero de documento es obligatorio cuando se informa el tipo de documento.'
                    );
                }
            }

            if ($hasNumber && ! $hasType) {
                if ($this->has('document_type')) {
                    $validator->errors()->add(
                        'document_number',
                        'Para dejar el documento de identidad vacio, anula tambien el numero de documento.'
                    );
                } else {
                    $validator->errors()->add(
                        'document_type',
                        'El tipo de documento es obligatorio cuando se informa el numero de documento.'
                    );
                }
            }

            if (! $hasType || ! $hasNumber) {
                return;
            }

            if (! is_string($type) || ! is_string($number)) {
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

    /**
     * Escribir el documento de identidad exige el permiso explicito
     * `person.view_document`: sin el, la peticion se rechaza con 403 antes de
     * tocar la base de datos.
     */
    protected function assertCanWriteIdentityDocument(): void
    {
        if (! $this->writesIdentityDocument()) {
            return;
        }

        abort_unless(
            $this->canWriteIdentityDocument(),
            403,
            'No tienes permiso para escribir el documento de identidad.'
        );
    }

    /**
     * Si el actor puede escribir el documento de identidad.
     */
    protected function canWriteIdentityDocument(): bool
    {
        return $this->user() !== null
            && PermissionService::has(
                $this->user(),
                ComplexPermission::PERSON_VIEW_DOCUMENT,
                $this->complexId()
            );
    }

    /**
     * La peticion escribe un documento de identidad cuando trae con valor
     * CUALQUIERA de los dos campos del par (`document_type` o
     * `document_number`). Un valor vacio o nulo tambien es un intento de
     * escritura cuando la persona almacenada tiene un documento: anularlo es
     * escribirlo. Si la persona no tiene documento, un valor vacio o nulo no
     * es ninguna escritura y el campo se ignora.
     */
    protected function writesIdentityDocument(): bool
    {
        if (! $this->has('document_type') && ! $this->has('document_number')) {
            return false;
        }

        if ($this->documentFieldHasValue($this->input('document_type'))
            || $this->documentFieldHasValue($this->input('document_number'))) {
            return true;
        }

        return $this->storedPersonHasDocument();
    }

    /**
     * Si la persona almacenada tiene un documento de identidad.
     */
    protected function storedPersonHasDocument(): bool
    {
        $person = $this->person();

        return $this->documentFieldHasValue($person->document_type?->value)
            || $this->documentFieldHasValue($person->document_number);
    }

    /**
     * Un valor de documento cuenta cuando no es nulo ni una cadena vacia.
     */
    protected function documentFieldHasValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        return is_int($value) || is_float($value);
    }
}
