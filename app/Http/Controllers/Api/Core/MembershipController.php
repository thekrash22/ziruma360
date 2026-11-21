<?php

namespace App\Http\Controllers\Api\Core;

use App\Enums\MembershipStatus;
use App\Http\Requests\Core\StoreMembershipRequest;
use App\Http\Requests\Core\UpdateMembershipRolesRequest;
use App\Http\Resources\MembershipResource;
use App\Models\ResidentialComplexMembership;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Membresias del conjunto (specs S73-S77).
 *
 * La superficie es `/api/v1/core/members` y gobierna las membresias del
 * conjunto activo: las lista, invita, acepta la invitacion, suspende, revoca
 * y asigna roles. La escritura la exige `complex.manage_members` dentro del
 * conjunto (S77); la aceptacion de la propia invitacion la hace el invitado,
 * que todavia no tiene membresia activa.
 *
 * Aislamiento (ACE-C01): una membresia de otro conjunto no se resuelve y
 * responde 404, sin filtrar su existencia.
 */
class MembershipController extends CoreController
{
    /**
     * Listado de las membresias del conjunto activo (S73).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $complexId = $this->complexId($request);

        abort_unless(
            Gate::allows('viewAny', [ResidentialComplexMembership::class, $complexId]),
            403,
            'No tienes permiso para consultar las membresías del conjunto.'
        );

        $memberships = ResidentialComplexMembership::query()
            ->where('residential_complex_id', $complexId)
            ->with(['user', 'inviter', 'roles'])
            ->orderBy('id')
            ->get();

        return MembershipResource::collection($memberships);
    }

    /**
     * Invitacion de una persona del conjunto (S74, S81).
     *
     * La persona se localiza por su documento dentro del conjunto y se
     * reutiliza si ya existe (RN-P02); la cuenta, por su correo. Si la
     * persona no tiene cuenta, se vincula a la cuenta invitada sin duplicar
     * ni una persona ni una cuenta (ACE-P03). La membresia nace en estado
     * `invited` con su traza de invitacion.
     *
     * La vinculacion y el alta van en la **misma transaccion**: si el alta
     * falla, la vinculacion se revierte y no queda una persona vinculada sin
     * su membresia. La violacion de `UNIQUE(residential_complex_id, user_id)`
     * (la carrera entre dos invitaciones del mismo par) se traduce a un 422
     * con un mensaje claro, nunca a un 500.
     */
    public function store(StoreMembershipRequest $request): JsonResponse
    {
        $complexId = $this->complexId($request);

        /** @var User $account */
        $account = $request->account();
        $person = $request->person();

        try {
            $membership = DB::transaction(function () use ($request, $complexId, $account, $person) {
                // La persona sin cuenta se vincula a la cuenta invitada
                // (ACE-P03): no se crea una persona nueva ni una cuenta
                // nueva.
                if ($person !== null && $person->user_id === null) {
                    $person->user_id = $account->id;
                    $person->save();
                }

                $membership = new ResidentialComplexMembership;
                $membership->residential_complex_id = $complexId;
                $membership->user_id = $account->id;
                $membership->status = MembershipStatus::INVITED;
                $membership->invited_by = $request->user()->id;
                $membership->invited_at = now();
                $membership->save();

                return $membership;
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicatedMembership($exception)) {
                throw $exception;
            }

            abort(422, 'Esta cuenta ya tiene una membresía en el conjunto.');
        }

        return $this->present($membership)->response()->setStatusCode(201);
    }

    /**
     * El error es la violacion de `UNIQUE(residential_complex_id, user_id)`.
     *
     * PostgreSQL responde con el SQLSTATE 23505 (violacion de unicidad) y
     * MySQL con el 23000; en ambos casos el mensaje nombra la tabla.
     */
    protected function isDuplicatedMembership(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23505', '23000'], true)) {
            return false;
        }

        return str_contains($exception->getMessage(), 'residential_complex_memberships');
    }

    /**
     * Aceptacion de la invitacion (S75).
     *
     * La acepta el propio invitado o un administrador del conjunto con
     * `complex.manage_members`. Fija `joined_at`.
     */
    public function activate(Request $request, int $membership): JsonResponse
    {
        $model = $this->resolveInvitation($request, $membership);

        abort_unless(
            Gate::allows('activate', $model),
            403,
            'No tienes permiso para activar esta membresía.'
        );

        abort_unless(
            $model->status === MembershipStatus::INVITED,
            422,
            'La membresía solo puede activarse cuando está en estado «invited».'
        );

        $model->activate();

        return $this->present($model)->response();
    }

    /**
     * Suspension de una membresia activa (S75).
     */
    public function suspend(Request $request, int $membership): JsonResponse
    {
        $model = $this->resolveMembership($request, $membership);

        abort_unless(
            Gate::allows('suspend', $model),
            403,
            'No tienes permiso para suspender las membresías del conjunto.'
        );

        abort_unless(
            $model->status === MembershipStatus::ACTIVE,
            422,
            'Solo una membresía activa puede suspenderse.'
        );

        $model->suspend();

        return $this->present($model)->response();
    }

    /**
     * Revocacion de una membresia (S75).
     *
     * La revocacion es terminal: una membresia revocada no vuelve a estar
     * activa.
     */
    public function revoke(Request $request, int $membership): JsonResponse
    {
        $model = $this->resolveMembership($request, $membership);

        abort_unless(
            Gate::allows('revoke', $model),
            403,
            'No tienes permiso para revocar las membresías del conjunto.'
        );

        abort_if(
            $model->status === MembershipStatus::REVOKED,
            422,
            'La membresía ya está revocada: la revocación es definitiva.'
        );

        abort_unless(
            in_array($model->status, [MembershipStatus::ACTIVE, MembershipStatus::SUSPENDED], true),
            422,
            'Solo una membresía activa o suspendida puede revocarse.'
        );

        $model->revoke();

        return $this->present($model)->response();
    }

    /**
     * Reemplazo del conjunto de roles de una membresia (S76).
     */
    public function roles(UpdateMembershipRolesRequest $request, int $membership): JsonResponse
    {
        $model = $request->membership();

        $model->roles()->sync($request->resolvedRoles());

        return $this->present($model)->response();
    }

    /**
     * La membresia dentro del conjunto activo (404 si no esta en el).
     */
    protected function resolveMembership(Request $request, int $membershipId): ResidentialComplexMembership
    {
        $model = ResidentialComplexMembership::query()
            ->where('residential_complex_id', $this->complexId($request))
            ->find($membershipId);

        abort_if($model === null, 404, 'Membresía no encontrada.');

        return $model;
    }

    /**
     * La membresia cuya invitacion se acepta (S77).
     *
     * La aceptacion la hace el propio invitado, que todavia no tiene una
     * membresia activa y, por tanto, no pertenece todavia al conjunto: el
     * alcance lo da la propia invitacion. Un conjunto fuera del alcance del
     * actor responde 404.
     */
    protected function resolveInvitation(Request $request, int $membershipId): ResidentialComplexMembership
    {
        $model = ResidentialComplexMembership::query()->find($membershipId);

        abort_if($model === null, 404, 'Membresía no encontrada.');

        abort_unless(
            $this->withinScope($request, $model),
            404,
            'Membresía no encontrada.'
        );

        return $model;
    }

    /**
     * La membresia esta en el alcance del actor cuando es la suya (la esta
     * aceptando) o cuando el actor es miembro activo de su conjunto.
     */
    protected function withinScope(Request $request, ResidentialComplexMembership $membership): bool
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $membership->user_id === (int) $user->id) {
            return true;
        }

        return $user->memberships()
            ->where('residential_complex_id', $membership->residential_complex_id)
            ->where('status', MembershipStatus::ACTIVE->value)
            ->exists();
    }

    /**
     * La membresia con sus relaciones, lista para exponer.
     */
    protected function present(ResidentialComplexMembership $membership): MembershipResource
    {
        return new MembershipResource($membership->load(['user', 'inviter', 'roles']));
    }
}
