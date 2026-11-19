<?php

namespace App\Settings;

use App\Enums\SystemRole;
use App\Enums\TipoDocumento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo cerrado de las políticas propias del conjunto (spec §1.4).
 *
 * El spec pide que `residential_complexes.settings` deje de ser JSON libre:
 * "se leen y escriben a través de clases tipadas con validación explícita
 * (DTO / enum / FormRequest). Nunca JSON libre para reglas críticas".
 *
 * Esta clase es esa clase tipada: define los grupos de política, las claves
 * admitidas, el tipo y la restricción de cada una, y rechaza en el backend
 * cualquier clave o valor que no exista en el catálogo.
 *
 * El vocabulario de las claves es provisional: el spec §1.4 ofrece "ejemplos
 * (core)" por grupo, no un catálogo cerrado (la decisión D-08 sobre plazos de
 * conservación sigue abierta). Lo que no se puede dejar abierto es la forma
 * de la regla: por eso toda clave tiene tipo y restricción explícita, y una
 * clave desconocida se rechaza en lugar de aceptarse en silencio.
 */
final class ComplexSettingsCatalog
{
    public const GROUP_REGIONAL = 'regional';

    public const GROUP_ACCESS = 'access';

    public const GROUP_PEOPLE = 'people';

    public const GROUP_OWNERSHIP = 'ownership';

    public const GROUP_PRIVACY = 'privacy';

    /**
     * Niveles de verificación documental exigidos para registrar propietarios
     * (grupo "titularidad"; D-04 recomienda exigir referencia documental).
     */
    public const VERIFICATION_LEVELS = ['none', 'reference', 'full'];

    /**
     * Formatos de fecha admitidos (grupo regional).
     */
    public const DATE_FORMATS = ['d/m/Y', 'm/d/Y', 'Y-m-d'];

    /**
     * Campos de persona que el conjunto puede exigir al registrarla (grupo
     * "personas y documentos").
     */
    public const PERSON_FIELDS = [
        'first_name',
        'last_name',
        'full_name',
        'document_type',
        'document_number',
        'phone',
        'email',
        'birth_date',
    ];

    /**
     * Grupos de política del catálogo.
     *
     * @return list<string>
     */
    public static function groups(): array
    {
        return [
            self::GROUP_REGIONAL,
            self::GROUP_ACCESS,
            self::GROUP_PEOPLE,
            self::GROUP_OWNERSHIP,
            self::GROUP_PRIVACY,
        ];
    }

    /**
     * Reglas de validación por clave, en notación `grupo.clave`.
     *
     * Todas las reglas son condicionales al valor: una clave ausente no es un
     * error (el conjunto puede no definirla) y una clave presente se valida
     * siempre.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'regional.timezone' => ['string', 'timezone'],
            'regional.currency' => ['string', 'size:3', 'alpha'],
            'regional.date_format' => ['string', Rule::in(self::DATE_FORMATS)],

            'access.invitation_expiry_days' => ['integer', 'min:1', 'max:90'],
            'access.password_min_length' => ['integer', 'min:8', 'max:64'],
            'access.two_factor_required' => ['boolean'],
            'access.invitable_roles' => ['array'],
            'access.invitable_roles.*' => ['string', Rule::in(array_column(SystemRole::cases(), 'value'))],

            'people.accepted_document_types' => ['array'],
            'people.accepted_document_types.*' => ['string', Rule::in(array_column(TipoDocumento::cases(), 'value'))],
            'people.required_fields' => ['array'],
            'people.required_fields.*' => ['string', Rule::in(self::PERSON_FIELDS)],

            'ownership.verification_level' => ['string', Rule::in(self::VERIFICATION_LEVELS)],

            'privacy.retention_days' => ['integer', 'min:1', 'max:3650'],
            'privacy.data_processing_notice' => ['nullable', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * Valores por defecto del catálogo.
     *
     * Lo que el conjunto no define se lee con el valor por defecto
     * documentado, nunca con `null`.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        return [
            self::GROUP_REGIONAL => [
                'timezone' => 'America/Bogota',
                'currency' => 'COP',
                'date_format' => 'd/m/Y',
            ],
            self::GROUP_ACCESS => [
                'invitation_expiry_days' => 7,
                'password_min_length' => 12,
                'two_factor_required' => false,
                'invitable_roles' => [],
            ],
            self::GROUP_PEOPLE => [
                'accepted_document_types' => array_column(TipoDocumento::cases(), 'value'),
                'required_fields' => ['full_name', 'document_type', 'document_number'],
            ],
            self::GROUP_OWNERSHIP => [
                // D-04: por defecto se exige referencia documental.
                'verification_level' => 'reference',
            ],
            self::GROUP_PRIVACY => [
                // D-08 sigue abierta: el plazo por defecto es provisional.
                'retention_days' => 1825,
                'data_processing_notice' => null,
            ],
        ];
    }

    /**
     * Valida un conjunto de políticas contra el catálogo.
     *
     * Rechaza las claves y los grupos desconocidos con un mensaje claro y
     * las reglas de tipo y rango de cada clave.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed> Las políticas validadas.
     *
     * @throws ValidationException
     */
    public static function validate(array $settings): array
    {
        $rules = self::rules();
        $errors = [];

        foreach ($settings as $group => $values) {
            if (! in_array($group, self::groups(), true)) {
                $errors["{$group}"][] = "El grupo de politicas «{$group}» no existe en el catalogo.";

                continue;
            }

            if (! is_array($values)) {
                $errors["{$group}"][] = "El grupo de politicas «{$group}» debe ser un objeto.";

                continue;
            }

            foreach (array_keys($values) as $key) {
                $path = "{$group}.{$key}";

                if (! array_key_exists($path, $rules)) {
                    $errors["{$path}"][] = "La clave de politica «{$path}» no existe en el catalogo.";
                }
            }
        }

        $validator = Validator::make($settings, $rules);

        foreach ($validator->errors()->messages() as $key => $messages) {
            foreach ($messages as $message) {
                $errors["{$key}"][] = $message;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $settings;
    }

    /**
     * Solo las políticas que el catálogo conoce.
     *
     * Las claves que el JSON libre dejo en el conjunto no forman parte de
     * las políticas: no se arrastran al escribir y la auditoria conserva el
     * valor anterior, asi que el cambio queda trazado (RN-C04).
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    public static function onlyKnown(array $settings): array
    {
        $rules = self::rules();
        $known = [];

        foreach (self::groups() as $group) {
            if (! isset($settings[$group]) || ! is_array($settings[$group])) {
                continue;
            }

            $values = [];

            foreach ($settings[$group] as $key => $value) {
                if (array_key_exists("{$group}.{$key}", $rules)) {
                    $values[$key] = $value;
                }
            }

            $known[$group] = $values;
        }

        return $known;
    }

    /**
     * Lectura tipada: aplica los valores por defecto del catálogo a las
     * claves que el conjunto no define.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    public static function effective(array $settings): array
    {
        $effective = self::defaults();

        foreach ($settings as $group => $values) {
            if (! is_array($values)) {
                continue;
            }

            $effective[$group] = array_merge($effective[$group] ?? [], $values);
        }

        return $effective;
    }
}
