<?php

namespace App\Http\Requests\Core;

use App\Enums\ComplexPermission;
use App\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Alta de un rol propio del conjunto (spec S85).
 *
 * El conjunto activo lo resuelve el servidor (S9): el cliente nunca lo
 * envia. La escritura la exige `complex.manage_members` dentro del conjunto.
 *
 * Las reglas de negocio del alta tambien se comprueban aqui, para que un
 * rechazo llegue como 422 con un mensaje que dice que falta y sin tocar la
 * base de datos:
 *
 * - la `key` es el identificador estable del rol: es unica dentro del
 *   conjunto y no puede coincidir con la de un rol del catalogo, para que la
 *   asignacion de roles por clave siga siendo inequivoca;
 * - el `name` es unico dentro del conjunto;
 * - los permisos salen del catalogo cerrado (`ComplexPermission`).
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [Role::class, $this->complexId()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
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
            'key.required' => 'La clave del rol es obligatoria.',
            'key.string' => 'La clave del rol debe ser un texto.',
            'key.max' => 'La clave del rol no puede superar los 64 caracteres.',
            'key.regex' => 'La clave del rol debe empezar por una letra y solo puede llevar letras, digitos y guiones bajos.',
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
     * Las reglas de negocio del alta (S85, S86).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $key = (string) $this->input('key');
            $name = (string) $this->input('name');

            // La clave no puede ser la de un rol del catalogo: la asignacion
            // de roles por clave tiene que ser inequivoca.
            if ($this->systemRoleWithKeyExists($key)) {
                $validator->errors()->add('key', 'La clave ya pertenece a un rol del catálogo de roles.');

                return;
            }

            if ($this->complexRoleWithKeyExists($key)) {
                $validator->errors()->add('key', 'Ya existe un rol con esa clave en el conjunto.');

                return;
            }

            if ($this->complexRoleWithNameExists($name)) {
                $validator->errors()->add('name', 'Ya existe un rol con ese nombre en el conjunto.');

                return;
            }

            $this->rejectUnknownPermissions($validator);
        });
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
     * Un rol del catalogo con esa clave.
     */
    protected function systemRoleWithKeyExists(string $key): bool
    {
        return Role::query()
            ->where('key', $key)
            ->whereNull('residential_complex_id')
            ->exists();
    }

    /**
     * Un rol del conjunto con esa clave.
     */
    protected function complexRoleWithKeyExists(string $key): bool
    {
        return Role::query()
            ->where('residential_complex_id', $this->complexId())
            ->where('key', $key)
            ->exists();
    }

    /**
     * Un rol del conjunto con ese nombre.
     */
    protected function complexRoleWithNameExists(string $name): bool
    {
        return Role::query()
            ->where('residential_complex_id', $this->complexId())
            ->where('name', $name)
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
