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
 */
class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PermissionService::has(
            $this->user(),
            ComplexPermission::PERSON_CREATE,
            $this->complexId()
        );
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
}
