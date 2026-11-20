<?php

namespace App\Http\Resources;

use App\Enums\ComplexPermission;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha completa de una persona (spec 4.2).
 *
 * Solo la exponen los roles con permiso person.view. El documento de
 * identidad se muestra enmascarado salvo que el actor tenga el permiso
 * explicito person.view_document (SEG-P01).
 */
class PersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'full_name' => $this->full_name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'document_type' => $this->documentTypeFor($request),
            'document_number' => $this->documentNumberFor($request),
            'phone' => $this->phone,
            'email' => $this->email,
            'birth_date' => $this->birth_date?->toDateString(),
            'status' => $this->status?->value,
            'data_consent_at' => $this->data_consent_at?->toIso8601String(),
        ];
    }

    /**
     * El tipo de documento solo con permiso explicito; sin el, se oculta.
     *
     * El documento de identidad es el par (tipo, numero): si el numero va
     * enmascarado, el tipo tambien se oculta, para que la lectura no exponga
     * mas dato que la escritura.
     */
    private function documentTypeFor(Request $request): ?string
    {
        $type = $this->document_type;

        if ($type === null) {
            return null;
        }

        $user = $request->user();

        if ($user !== null && PermissionService::has(
            $user,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
            (int) $this->residential_complex_id
        )) {
            return $type->value;
        }

        return null;
    }

    /**
     * El documento en claro solo con permiso explicito; si no, enmascarado.
     */
    private function documentNumberFor(Request $request): ?string
    {
        $number = $this->document_number;

        if ($number === null) {
            return null;
        }

        $user = $request->user();

        if ($user !== null && PermissionService::has(
            $user,
            ComplexPermission::PERSON_VIEW_DOCUMENT,
            (int) $this->residential_complex_id
        )) {
            return $number;
        }

        return self::mask($number);
    }

    /**
     * Enmascara el documento dejando visibles los ultimos cuatro caracteres.
     */
    private static function mask(string $number): string
    {
        if (strlen($number) <= 4) {
            return str_repeat('*', strlen($number));
        }

        return str_repeat('*', strlen($number) - 4).substr($number, -4);
    }
}
