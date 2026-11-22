<?php

namespace App\Services;

use App\Models\ResidentialComplex;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Proyeccion de los conjuntos del actor (spec S94).
 *
 * Vive en un servicio y no en un controller porque la consumen tres
 * superficies: la pantalla de conjuntos, la cabecera (a traves de las props
 * de Inertia) y la redireccion del middleware cuando el actor tiene mas de
 * un conjunto y todavia no eligió ninguno.
 *
 * La plataforma ve el catalogo completo y el resto ve sus conjuntos
 * (membresias activas), igual que en la API.
 */
final class ComplexCatalog
{
    /**
     * Los conjuntos que el actor ve, en orden por nombre.
     *
     * @return Collection<int, ResidentialComplex>
     */
    public static function visibleComplexes(User $user): Collection
    {
        if ($user->isPlatformAdmin()) {
            return ResidentialComplex::query()->orderBy('name')->get();
        }

        return ResidentialComplex::query()
            ->whereIn('id', self::memberComplexIds($user))
            ->orderBy('name')
            ->get();
    }

    /**
     * Los conjuntos del actor, cada uno con su proyeccion.
     *
     * @return list<array<string, mixed>>
     */
    public static function catalog(User $user): array
    {
        $memberIds = self::memberComplexIds($user);

        return self::visibleComplexes($user)
            ->map(fn (ResidentialComplex $complex): array => self::summarize($complex, $memberIds))
            ->values()
            ->all();
    }

    /**
     * El conjunto activo del actor, como lo ve la cabecera.
     *
     * @return array<string, mixed>|null
     */
    public static function active(?User $user): ?array
    {
        $current = ComplexContext::getCurrentComplex();

        if (! $current instanceof ResidentialComplex) {
            return null;
        }

        return self::summarize($current, $user === null ? [] : self::memberComplexIds($user));
    }

    /**
     * Los conjuntos de los que el actor es miembro activo.
     *
     * @return list<int>
     */
    public static function memberComplexIds(User $user): array
    {
        return $user->activeMemberships()
            ->pluck('residential_complex_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Proyeccion de un conjunto para la cabecera y el listado.
     *
     * @param  list<int>  $memberIds
     * @return array<string, mixed>
     */
    public static function summarize(ResidentialComplex $complex, array $memberIds = []): array
    {
        $currentId = ComplexContext::getCurrentComplexId();

        return [
            'uuid' => $complex->uuid,
            'name' => $complex->name,
            'code' => $complex->code,
            'status' => $complex->status->value,
            'status_label' => $complex->status->label(),
            'is_current' => $currentId !== null && $currentId === (int) $complex->id,
            'is_member' => in_array((int) $complex->id, $memberIds, true),
        ];
    }
}
