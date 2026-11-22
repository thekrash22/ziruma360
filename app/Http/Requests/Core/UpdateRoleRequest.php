<?php

namespace App\Http\Requests\Core;

use App\Enums\ComplexPermission;
use App\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Edicion de un rol del conjunto (spec S87).
 *
 * La escritura la exige `complex.manage_members` dentro del conjunto y el
 * rol se busca en el conjunto activo: un rol de otro conjunto no se resuelve
 * (404) y no se filtra su existencia (ACE-C01). Un rol del sistema se
 * resuelve pero no se puede editar (403).
 *
 * La `key` es el identificador estable del rol y es **inmutable**: si la
 * peticion trae una `key` distinta de la del rol, se rechaza con 422 y la
 * `key` sigue siendo la misma. Los permisos tienen que estar en el catalogo
 * cerrado (`ComplexPermission`) y, cuando vienen, **reemplazan** el conjunto
 * vigente; si no vienen, el conjunto vigente se conserva.
 */
class UpdateRoleRequest extends FormRequest
{
    private ?Role $role = null;

    public function authorize(): bool
    {
        $role = $this->role();

        abort_unless(
            Gate::allows('update', [$role, $this->complexId()]),
            403,
            'No tienes permiso para editar los roles del conjunto.'
        );

        abort_if(
            $role->is_system,
            403,
            'Los roles del sistema no se pueden editar.'
        );

        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'string', 'max:64'],
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.string' => 'La clave del rol debe ser un texto.',
            'key.max' => 'La clave del rol no puede superar los 64 caracteres.',
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.string' => 'El nombre del rol debe ser un texto.',
            'name.max' => 'El nombre del rol no puede superar los 100 caracteres.',
            'description.string' => 'La descripcion del rol debe ser un texto.',
            'description.max' => 'La descripcion del rol no puede superar los 255 caracteres.',
            'permissions.array' => 'Los permisos del rol deben ser una lista.',
            'permissions.*.string' => 'Cada permiso del rol debe ser un texto.',
        ];
    }

    /**
     * Las reglas de negocio de la edicion (S86, S87).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $role = $this->role();

            // La `key` es inmutable (S87).
            if ($this->has('key') && (string) $this->input('key') !== (string) $role->key) {
                $validator->errors()->add('key', 'La clave del rol es inmutable.');

                return;
            }

            // El `name` es unico dentro del conjunto (S87).
            if ($this->has('name') && $this->complexRoleWithNameExists((string) $this->input('name'))) {
                $validator->errors()->add('name', 'Ya existe un rol con ese nombre en el conjunto.');

                return;
            }

            $this->rejectUnknownPermissions($validator);
        });
    }

    /**
     * El rol del conjunto activo que se edita.
     *
     * Se resuelven tambien los roles del sistema: existen y se listan, pero
     * no se pueden editar (403).
     */
    public function role(): Role
    {
        if ($this->role !== null) {
            return $this->role;
        }

        $role = Role::query()
            ->where(function (Builder $query): void {
                $query->whereNull('residential_complex_id')
                    ->orWhere('residential_complex_id', $this->complexId());
            })
            ->find((int) $this->route('role'));

        abort_if($role === null, 404, 'Rol no encontrado.');

        return $this->role = $role;
    }

    /**
     * Los permisos enviados en la peticion.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        $permissions = $this->input('permissions', []);

        if (! is_array($permissions)) {
            return [];
        }

        return array_values(array_filter($permissions, 'is_string'));
    }

    /**
     * La peticion trae el conjunto de permisos (lo que se reemplaza).
     */
    public function replacesPermissions(): bool
    {
        return $this->has('permissions');
    }

    /**
     * Otro rol del conjunto con ese nombre.
     */
    protected function complexRoleWithNameExists(string $name): bool
    {
        return Role::query()
            ->where('residential_complex_id', $this->complexId())
            ->where('name', $name)
            ->where('id', '!=', $this->role()->id)
            ->exists();
    }

    /**
     * Los permisos tienen que estar en el catalogo cerrado (S86).
     */
    protected function rejectUnknownPermissions(Validator $validator): void
    {
        foreach ($this->permissions() as $index => $permission) {
            if (! in_array($permission, ComplexPermission::keys(), true)) {
                $validator->errors()->add(
                    "permissions.{$index}",
                    'El permiso no pertenece al catálogo de permisos.'
                );
            }
        }
    }

    /**
     * El conjunto activo verificado por el middleware.
     */
    protected function complexId(): int
    {
        return (int) $this->attributes->get('complex_id');
    }
}
