<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolución del conjunto activo en el servidor (spec S9).
 *
 * "Resolución de conjunto activo en servidor (middleware), nunca confiando
 * en ID del cliente". El conjunto se deduce de las membresías activas del
 * actor: un conjunto pedido que el actor no tiene se rechaza y nunca se
 * toma como contexto.
 */
class ActiveComplexResolver
{
    /**
     * Encabezado con el conjunto solicitado (opcional: si el actor tiene una
     * sola membresía activa, el conjunto se resuelve solo).
     */
    public const HEADER = 'X-Residential-Complex-Id';

    /**
     * @throws HttpException
     */
    public function resolve(Request $request): int
    {
        $user = $request->user();

        if ($user === null) {
            throw new HttpException(401, 'Debes iniciar sesion para operar sobre un conjunto.');
        }

        $memberships = $user->memberships()
            ->where('status', MembershipStatus::ACTIVE->value)
            ->pluck('residential_complex_id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $requested = $this->requestedComplexId($request);

        if ($requested !== null) {
            if (! $memberships->contains($requested)) {
                throw new HttpException(403, 'El conjunto solicitado no pertenece a tu cuenta.');
            }

            return $requested;
        }

        if ($memberships->isEmpty()) {
            throw new HttpException(403, 'No tienes una membresia activa en ningun conjunto.');
        }

        if ($memberships->count() > 1) {
            throw new HttpException(
                409,
                'Tienes membresias activas en varios conjuntos: indica cual con el encabezado '.self::HEADER.'.'
            );
        }

        return (int) $memberships->first();
    }

    /**
     * El conjunto que el cliente pide, si lo pide.
     *
     * @throws HttpException
     */
    public function requestedComplexId(Request $request): ?int
    {
        $value = $request->header(self::HEADER) ?? $request->query('complex_id');

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (int) $value <= 0) {
            throw new HttpException(403, 'El conjunto solicitado no es valido.');
        }

        return (int) $value;
    }
}
