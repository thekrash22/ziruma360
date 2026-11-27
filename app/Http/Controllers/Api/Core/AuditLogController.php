<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Requests\Core\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Audit trail of the complex (specs S100 and S101).
 *
 * The surface is `GET /api/v1/core/audit-logs` and it is read only: there is
 * no route to change or delete a trail (RN-C04: the history is not rewritten).
 *
 * The trail is filtered explicitly by `residential_complex_id`: the record
 * does not depend on a global scope, so the complex of the listing is
 * always the active one and a trail of another complex is never listed.
 *
 * Reading it requires `complex.view_audit` inside the complex: without it
 * the answer is 403, never an empty list.
 */
class AuditLogController extends CoreController
{
    /**
     * The trail of the active complex (S100).
     */
    public function index(IndexAuditLogRequest $request): AnonymousResourceCollection
    {
        $complexId = $this->complexId($request);

        $this->authorizeReading($request, $complexId);

        $logs = AuditTrail::recent(
            $complexId,
            $request->action(),
            $request->entityType(),
            $request->entityId(),
            $request->fromDate(),
            $request->toDate(),
        )
            ->with('actor')
            ->paginate($request->perPage())
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }

    /**
     * One record of the trail of the active complex (S101).
     *
     * A record of another complex is not found (404): the surface does not
     * filter the existence of the trails of other complexes.
     */
    public function show(Request $request, int $auditLog): JsonResponse
    {
        $complexId = $this->complexId($request);

        $this->authorizeReading($request, $complexId);

        $log = AuditLog::query()
            ->where('residential_complex_id', $complexId)
            ->with('actor')
            ->find($auditLog);

        abort_if($log === null, 404, 'Registro de auditoría no encontrado.');

        return (new AuditLogResource($log))->response();
    }

    /**
     * The door of the surface: `complex.view_audit` in the active complex.
     */
    protected function authorizeReading(Request $request, int $complexId): void
    {
        abort_unless(
            Gate::allows('viewAny', [AuditLog::class, $complexId]),
            403,
            'No tienes permiso para consultar la auditoría del conjunto.'
        );
    }
}
