<?php

namespace App\Http\Requests\Core;

use App\Enums\ComplexPermission;
use App\Enums\PersonStatus;
use App\Enums\TipoDocumento;
use App\Services\PermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de una persona (spec §4.2, S39).
 *
 * El conjunto activo lo resuelve el servidor (S9): el cliente nunca lo
 * envia. El permiso de alta lo decide el catalogo (`person.create`).
 *
 * Guarda de escritura del documento de identidad (SEG-P01): escribir un
 * documento exige el permiso explicito `person.view_document`, el mismo que
 * lo expone en claro. Sin el, la peticion se rechaza con 403 y no se crea
 * ninguna persona.
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
     * La peticion escribe un documento de identidad cuando trae un numero de
     * documento con valor. Un valor vacio o nulo no es una escritura de
     * documento (la UI lo envia siempre en el formulario de alta).
     */
    protected function writesIdentityDocument(): bool
    {
        $number = $this->input('document_number');

        if ($number === null) {
            return false;
        }

        if (is_string($number)) {
            return trim($number) !== '';
        }

        return (bool) $number;
    }
}
