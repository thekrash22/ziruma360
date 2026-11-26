<?php

namespace App\Http\Requests\Core;

use App\Services\AuditTrail;
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
            $rules['to'] = ['nullable', 'date', 'after_or_equal:from'];
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
        $value = $this->validated('from');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = Carbon::parse($value);

        return $this->isDateOnly($value) ? $date->startOfDay() : $date;
    }

    /**
     * The upper bound of the range, if any.
     *
     * A date without a time is the end of that day.
     */
    public function toDate(): ?Carbon
    {
        $value = $this->validated('to');

        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = Carbon::parse($value);

        return $this->isDateOnly($value) ? $date->endOfDay() : $date;
    }

    /**
     * Whether the value is a plain date (no time of the day).
     */
    private function isDateOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value));
    }
}
