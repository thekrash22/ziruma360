<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Core\IndexAuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Audit trail of the complex in the web UI (specs S102 and S103).
 *
 * The screen is `/auditoria` and it is read only: it lists the trail of the
 * active complex with the same filters the API exposes (action, entity and
 * date range) and shows the diff of every record.
 *
 * The door is `complex.view_audit` inside the active complex: without it
 * the answer is 403, never an empty screen. The trail is filtered
 * explicitly by `residential_complex_id`, so a record of another complex is
 * never listed.
 */
class AuditLogController extends Controller
{
    /**
     * The trail of the active complex (S102).
     */
    public function index(IndexAuditLogRequest $request): Response
    {
        $complexId = (int) $request->attributes->get('complex_id');

        abort_unless(
            Gate::allows('viewAny', [AuditLog::class, $complexId]),
            403,
            'No tienes permiso para consultar la auditoría del conjunto.'
        );

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

        return Inertia::render('Auditoria/Index', [
            'logs' => [
                'data' => collect($logs->items())
                    ->map(fn (AuditLog $log): array => (new AuditLogResource($log))->toArray($request))
                    ->all(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
                'links' => $logs->linkCollection()->toArray(),
            ],
            'filters' => [
                'action' => $request->action(),
                'entity_type' => $request->entityType(),
                'entity_id' => $request->entityId(),
                'from' => $request->validated('from'),
                'to' => $request->validated('to'),
            ],
            'actions' => AuditTrail::actions(),
            'entityTypes' => AuditTrail::entityTypes($complexId),
        ]);
    }
}
