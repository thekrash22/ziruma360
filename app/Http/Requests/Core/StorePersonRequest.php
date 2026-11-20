<?php

namespace App\Http\Requests\Core;

use App\Enums\ComplexPermission;
use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Services\PermissionService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una persona (spec §4.2, S39).
 *
 * El conjunto activo lo resuelve el servidor (S9): el cliente nunca lo
 * envia. El permiso de alta lo decide el catalogo (`person.create`).
 *
 * Guarda de escritura del documento de identidad (SEG-P01): el documento de
 * identidad es el PAR (`document_type`, `document_number`), no el numero
 * suelto, y escribir cualquiera de los dos campos exige el permiso explicito
 * `person.view_document`, el mismo que lo expone en claro. Sin el, la
 * peticion se rechaza con 403 y no se crea ninguna persona.
 *
 * Atomicidad del documento (RN-P02, T17): el documento es completo o no
 * existe; un tipo sin numero (o un numero sin tipo) se rechaza con 422 en la
 * aplicacion, antes de que la base de datos lo rechace con su CHECK.
 */
class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user() === null) {
            return false;
        }

        if (! PermissionService::has(
            $this->user(),
            ComplexPermission::PERSON_CREATE,
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
            'full_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'document_type' => ['nullable', Rule::enum(TipoDocumento::class)],
            'document_number' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'status' => ['nullable', Rule::enum(PersonStatus::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Los datos validados, sin el documento de identidad cuando el actor no
     * tiene el permiso explicito: sin el, el documento nunca llega al
     * `fill()`.
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
     * El documento de identidad es atomico: tipo y numero van juntos.
     *
     * Un tipo sin numero (o un numero sin tipo) no es un documento, y el
     * CHECK de la base lo rechazaria: se responde 422 en la aplicacion para
     * que el error sea de negocio y no un error del motor.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasType = $this->documentFieldHasValue($this->input('document_type'));
            $hasNumber = $this->documentFieldHasValue($this->input('document_number'));

            if ($hasType && ! $hasNumber) {
                $validator->errors()->add(
                    'document_number',
                    'El numero de documento es obligatorio cuando se informa el tipo de documento.'
                );
            }

            if ($hasNumber && ! $hasType) {
                $validator->errors()->add(
                    'document_type',
                    'El tipo de documento es obligatorio cuando se informa el numero de documento.'
                );
            }
        });
    }

    // RN-P02: antes de crear se busca por documento y, si ya existe una
    // persona con ese documento en el conjunto, se reutiliza en lugar de
    // duplicar la informacion. La busqueda la hace el controlador, que
    // devuelve la persona existente en vez de crear una nueva.

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
     * `document_number`). Un valor vacio o nulo no es una escritura: en un
     * alta no hay documento almacenado que borrar, asi que el campo se
     * ignora.
     */
    protected function writesIdentityDocument(): bool
    {
        return $this->documentFieldHasValue($this->input('document_type'))
            || $this->documentFieldHasValue($this->input('document_number'));
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
