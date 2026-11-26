<?php

namespace App\Http\Requests\Core;

use App\Services\AuditTrail;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Filters of the audit trail listing (specs S100 and S101).
 *
 * The filters are the ones the surface exposes: `action`, `entity_type`,
 * `entity_id`, `from`, `to` and the page size. An unknown action or an
 * inverted date range is a bad request (422), never a silent empty list.
 */
class IndexAuditLogRequest extends FormRequest
{
    /**
     * The default page size.
     */
    public const DEFAULT_PER_PAGE = 25;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'action' => ['nullable', Rule::in(AuditTrail::ACTIONS)],
            'entity_type' => ['nullable', 'string', 'max:255'],
            'entity_id' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];

        // The range is only comparable when both ends are given.
        if ($this->filled('from')) {
            $rules['to'] = ['nullable', 'date', $this->rangeRule()];
        }

        return $rules;
    }

    /**
     * The page size, bounded to a hundred records.
     */
    public function perPage(): int
    {
        $perPage = (int) $this->validated('per_page', self::DEFAULT_PER_PAGE);

        return min(max($perPage, 1), 100);
    }

    /**
     * The requested action, if any.
     */
    public function action(): ?string
    {
        $action = $this->validated('action');

        return is_string($action) ? $action : null;
    }

    /**
     * The requested entity type, if any.
     *
     * A short name (`Person`) is expanded to the class of the model
     * (`App\Models\Person`), so the caller does not have to know the
     * namespace of every audited model.
     */
    public function entityType(): ?string
    {
        $value = $this->validated('entity_type');

        if (! is_string($value) || $value === '') {
            return null;
        }

        if (str_contains($value, '\\')) {
            return $value;
        }

        return 'App\\Models\\'.$value;
    }

    /**
     * The requested entity id, if any.
     */
    public function entityId(): ?string
    {
        $value = $this->validated('entity_id');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The lower bound of the range, if any.
     *
     * A date without a time is the beginning of that day.
     */
    public function fromDate(): ?Carbon
    {
        return $this->resolvedBound('from');
    }

    /**
     * The upper bound of the range, if any.
     *
     * A date without a time is the end of that day.
     */
    public function toDate(): ?Carbon
    {
        return $this->resolvedBound('to');
    }

    /**
     * The rule of the upper bound: it cannot be before the lower one.
     *
     * The bounds are compared as they are resolved (a plain date is the end
     * of that day for `to`), so a range that mixes a time in `from` with a
     * plain date in `to` is accepted when it covers the same day.
     */
    private function rangeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = $this->resolvedBound('from');
            $to = $this->resolvedBound('to');

            if ($from !== null && $to !== null && $to->lt($from)) {
                $fail('The to field must be a date after or equal to from.');
            }
        };
    }

    /**
     * A bound of the range, as it is resolved.
     *
     * A date without a time is the beginning of the day for `from` and the
     * end of the day for `to`.
     */
    private function resolvedBound(string $key): ?Carbon
    {
        $value = $this->input($key);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (InvalidFormatException) {
            // The `date` rule reports the malformed value.
            return null;
        }

        if (! $this->isDateOnly($value)) {
            return $date;
        }

        return $key === 'to' ? $date->endOfDay() : $date->startOfDay();
    }

    /**
     * Whether the value is a plain date (no time of the day).
     */
    private function isDateOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }
}
