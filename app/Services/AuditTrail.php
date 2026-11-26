<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The audit trail of a complex (specs S100, S101 and S102).
 *
 * The trail is filtered explicitly by `residential_complex_id`: the record
 * does not depend on a global scope, so the complex of a listing is always
 * the active one and a trail of another complex is never listed.
 *
 * The service is the single place that builds the query: the API and the web
 * screen filter the trail exactly the same way.
 */
final class AuditTrail
{
    /**
     * The actions the trail records.
     *
     * @var list<string>
     */
    public const ACTIONS = ['created', 'updated', 'deleted'];

    /**
     * The trail of a complex, narrowed by the filters.
     *
     * @param  int|null  $complexId  the complex of the trail
     * @param  string|null  $action  `created`, `updated` or `deleted`
     * @param  string|null  $entityType  the class of the audited record
     * @param  string|null  $entityId  the id of the audited record
     */
    public static function query(
        int $complexId,
        ?string $action = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): Builder {
        $query = AuditLog::query()->where('residential_complex_id', $complexId);

        if ($action !== null && $action !== '') {
            $query->where('action', $action);
        }

        if ($entityType !== null && $entityType !== '') {
            $query->where('entity_type', $entityType);
        }

        if ($entityId !== null && $entityId !== '') {
            $query->where('entity_id', $entityId);
        }

        if ($from !== null) {
            $query->where('occurred_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('occurred_at', '<=', $to);
        }

        return $query;
    }

    /**
     * The trail of a complex, in descending order by moment.
     *
     * The id breaks the tie of two records of the same instant, so the
     * order never changes between two pages.
     */
    public static function recent(
        int $complexId,
        ?string $action = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ): Builder {
        return self::query($complexId, $action, $entityType, $entityId, $from, $to)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /**
     * The entity types the trail of a complex holds.
     *
     * The list feeds the filter of the screen: it offers the types that
     * really exist in the trail instead of a hardcoded list.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function entityTypes(int $complexId): array
    {
        return AuditLog::query()
            ->where('residential_complex_id', $complexId)
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => self::entityLabel($type),
            ])
            ->all();
    }

    /**
     * The label of an entity type.
     *
     * The trail stores the class of the audited record; the label is the
     * short name of that class, translated for the domains the audit
     * covers.
     */
    public static function entityLabel(string $entityType): string
    {
        $short = class_basename($entityType);

        return match ($short) {
            'ResidentialComplex' => 'Conjunto',
            'ResidentialComplexMembership' => 'Membresía',
            'MembershipRole' => 'Asignación de rol',
            'Role' => 'Rol',
            'RolePermission' => 'Permiso de rol',
            'BuildingFloor' => 'Piso',
            'Building' => 'Edificio',
            'Person' => 'Persona',
            'PropertyUnit' => 'Unidad',
            'PropertyUnitOwnership' => 'Titularidad',
            'PropertyUnitAuthorization' => 'Autorización de unidad',
            'PropertyUnitRelation' => 'Relación de unidades',
            'UnitOccupancy' => 'Ocupación',
            'UnitLease' => 'Arrendamiento',
            'Employment' => 'Empleo',
            'CommonArea' => 'Área común',
            'CommonPropertyExclusiveUseAssignment' => 'Uso exclusivo',
            'Apartment' => 'Apartamento',
            'ApartmentType' => 'Tipo de apartamento',
            'ParkingFacility' => 'Instalación de parqueo',
            'ParkingSpace' => 'Unidad de parqueo',
            default => $short,
        };
    }

    /**
     * The label of an action.
     */
    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Creado',
            'updated' => 'Actualizado',
            'deleted' => 'Eliminado',
            default => $action,
        };
    }

    /**
     * The actions of the trail, with their label.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function actions(): array
    {
        return array_map(
            static fn (string $action): array => [
                'value' => $action,
                'label' => self::actionLabel($action),
            ],
            self::ACTIONS
        );
    }
}
