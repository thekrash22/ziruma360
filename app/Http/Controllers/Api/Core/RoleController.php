<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Requests\Core\StoreRoleRequest;
use App\Http\Requests\Core\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Roles del conjunto (specs S83-S88).
 *
 * La superficie es `/api/v1/core/roles` y gobierna los roles: lista los del
 * sistema y los propios del conjunto activo, crea roles propios y los edita
 * (nombre, descripcion, permisos y estado). La escritura la exige
 * `complex.manage_members` dentro del conjunto (S84).
 *
 * Un rol no se elimina: se desactiva (S88). Un rol desactivado conserva su
 * traza y sus asignaciones, pero deja de estar en vigor: `ScopedPermission`
 * lo ignora y deja de conceder permisos.
 *
 * Aislamiento (ACE-C01): un rol de otro conjunto no se resuelve y responde
 * 404, sin filtrar su existencia.
 */
class RoleController extends CoreController
{
    /**
     * Listado de los roles del conjunto activo (S83).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $complexId = $this->complexId($request);

        abort_unless(
            Gate::allows('viewAny', [Role::class, $complexId]),
            403,
            'No tienes permiso para consultar los roles del conjunto.'
        );

        $roles = Role::query()
            ->where(function (Builder $query) use ($complexId): void {
                $query->whereNull('residential_complex_id')
                    ->orWhere('residential_complex_id', $complexId);
            })
            ->with('permissions')
            ->orderBy('id')
            ->get();

        return RoleResource::collection($roles);
    }

    /**
     * Alta de un rol propio del conjunto (S85, S86).
     *
     * El rol nace en el conjunto activo, como rol propio (`is_system` en
     * false) y en vigor. Sus permisos salen del catalogo cerrado: la
     * request los valida antes de tocar la base de datos.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $complexId = $this->complexId($request);

        try {
            $role = DB::transaction(function () use ($request, $complexId): Role {
                $role = new Role;
                $role->residential_complex_id = $complexId;
                $role->key = (string) $request->input('key');
                $role->name = (string) $request->input('name');
                $role->description = $request->input('description');
                $role->is_system = false;
                $role->is_editable = true;
                $role->save();

                foreach ($request->permissions() as $permission) {
                    $role->grantPermission($permission);
                }

                return $role;
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicatedRole($exception)) {
                throw $exception;
            }

            abort(422, 'Ya existe un rol con esa clave o ese nombre en el conjunto.');
        }

        return $this->present($role)->response()->setStatusCode(201);
    }

    /**
     * Edicion de un rol del conjunto (S86, S87).
     *
     * La `key` no se toca: es inmutable. Los permisos, cuando vienen,
     * reemplazan el conjunto vigente.
     */
    public function update(UpdateRoleRequest $request, int $role): JsonResponse
    {
        $model = $request->role();

        DB::transaction(function () use ($request, $model): void {
            if ($request->has('name')) {
                $model->name = (string) $request->input('name');
            }

            if ($request->has('description')) {
                $model->description = $request->input('description');
            }

            $model->save();

            if ($request->replacesPermissions()) {
                $model->permissions()->delete();

                foreach ($request->permissions() as $permission) {
                    $model->grantPermission($permission);
                }
            }
        });

        return $this->present($model)->response();
    }

    /**
     * El rol con sus permisos, listo para exponer.
     */
    protected function present(Role $role): RoleResource
    {
        return new RoleResource($role->load('permissions'));
    }

    /**
     * El error es la violacion de `UNIQUE(residential_complex_id, key)` o de
     * `UNIQUE(residential_complex_id, name)`.
     *
     * PostgreSQL responde con el SQLSTATE 23505 (violacion de unicidad) y
     * MySQL con el 23000; en ambos casos el mensaje nombra la tabla.
     */
    protected function isDuplicatedRole(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23505', '23000'], true)) {
            return false;
        }

        return str_contains($exception->getMessage(), 'roles');
    }
}
