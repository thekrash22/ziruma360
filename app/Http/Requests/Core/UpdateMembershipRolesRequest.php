<?php

namespace App\Http\Requests\Core;

use App\Models\ResidentialComplexMembership;
use App\Models\Role;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Asignacion (reemplazo) del conjunto de roles de una membresia (spec S76).
 *
 * La escritura la exige `complex.manage_members` dentro del conjunto y la
 * membresia se busca en el conjunto activo: una membresia de otro conjunto no
 * se resuelve (404) y no se filtra su existencia (ACE-C01).
 *
 * Solo roles del catalogo: un rol del sistema o del propio conjunto. Un rol
 * desconocido o de otro conjunto se rechaza con 422 y el conjunto de roles
 * de la membresia queda como estaba: el rechazo no deja la membresia a medias.
 */
class UpdateMembershipRolesRequest extends FormRequest
{
    private ?ResidentialComplexMembership $membership = null;

    public function authorize(): bool
    {
        return Gate::allows('assignRoles', $this->membership());
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array'],
            'roles.*' => ['array'],
            'roles.*.role_id' => ['nullable', 'integer'],
            'roles.*.role_key' => ['nullable', 'string', 'max:64'],
            'roles.*.valid_from' => ['nullable', 'date'],
            'roles.*.valid_until' => ['nullable', 'date'],
        ];
    }

    /**
     * Los roles tienen que existir y ser del conjunto, y su vigencia no puede
     * ser contradictoria.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->roles() as $index => $role) {
                if (! is_array($role)) {
                    continue;
                }

                $resolved = $this->resolveRole($role);

                if ($resolved === null) {
                    $validator->errors()->add(
                        "roles.{$index}",
                        'El rol indicado no existe en el catálogo de roles.'
                    );

                    continue;
                }

                if ($resolved->residential_complex_id !== null
                    && (int) $resolved->residential_complex_id !== $this->complexId()) {
                    $validator->errors()->add(
                        "roles.{$index}",
                        'El rol indicado pertenece a otro conjunto.'
                    );

                    continue;
                }

                $validFrom = $role['valid_from'] ?? null;
                $validUntil = $role['valid_until'] ?? null;

                if ($validFrom !== null && $validUntil !== null
                    && Carbon::parse($validUntil)->lt(Carbon::parse($validFrom))) {
                    $validator->errors()->add(
                        "roles.{$index}",
                        'La vigencia del rol no puede terminar antes de empezar.'
                    );
                }
            }
        });
    }

    /**
     * La membresia del conjunto activo a la que se asignan los roles.
     */
    public function membership(): ResidentialComplexMembership
    {
        if ($this->membership !== null) {
            return $this->membership;
        }

        $membership = ResidentialComplexMembership::query()
            ->where('residential_complex_id', $this->complexId())
            ->find((int) $this->route('membership'));

        abort_if($membership === null, 404, 'Membresía no encontrada.');

        return $this->membership = $membership;
    }

    /**
     * Los roles enviados en la peticion.
     *
     * @return array<int, mixed>
     */
    public function roles(): array
    {
        $roles = $this->input('roles', []);

        return is_array($roles) ? array_values($roles) : [];
    }

    /**
     * Los roles resueltos, listos para reemplazar el conjunto de roles.
     *
     * @return array<int, array<string, string|null>>
     */
    public function resolvedRoles(): array
    {
        $resolved = [];

        foreach ($this->roles() as $role) {
            if (! is_array($role)) {
                continue;
            }

            $model = $this->resolveRole($role);

            if ($model === null) {
                continue;
            }

            $resolved[$model->id] = [
                'valid_from' => $this->validityDate($role['valid_from'] ?? null),
                'valid_until' => $this->validityDate($role['valid_until'] ?? null),
            ];
        }

        return $resolved;
    }

    /**
     * El rol del catalogo que pide la peticion, por id o por clave.
     *
     * @param  array<string, mixed>  $role
     */
    protected function resolveRole(array $role): ?Role
    {
        $roleId = $role['role_id'] ?? null;

        if (is_numeric($roleId)) {
            return Role::query()->find((int) $roleId);
        }

        $roleKey = $role['role_key'] ?? null;

        if (! is_string($roleKey) || $roleKey === '') {
            return null;
        }

        return Role::query()
            ->where('key', $roleKey)
            ->where(function ($query): void {
                $query->whereNull('residential_complex_id')
                    ->orWhere('residential_complex_id', $this->complexId());
            })
            ->first();
    }

    /**
     * Una fecha de vigencia en el formato de la base.
     */
    protected function validityDate(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateTimeString();
    }

    /**
     * El conjunto activo verificado por el middleware.
     */
    protected function complexId(): int
    {
        return (int) $this->attributes->get('complex_id');
    }
}
