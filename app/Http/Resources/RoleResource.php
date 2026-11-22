<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rol del conjunto (specs S83-S87).
 *
 * Expone la identidad del rol (su `key`, que es su identificador estable y
 * no cambia, y su `name`), su ambito (`is_system`), su estado (`is_active`,
 * que es la forma de baja: un rol se desactiva, no se elimina) y su conjunto
 * de permisos.
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_editable' => $this->is_editable,
            'is_active' => $this->is_active,
            'permissions' => $this->permissions
                ->pluck('permission_key')
                ->sort()
                ->values()
                ->all(),
        ];
    }
}
