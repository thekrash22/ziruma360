<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Builders\AuditableDeleteBuilder;
use LogicException;

/**
 * Trait for models that require automatic auditing (spec §1.6).
 *
 * The record keeps who (`actor_user_id`), when (`occurred_at`), the previous
 * value and the new one (`before_data` / `after_data`) and the complex of the
 * audited model. Snapshots never keep passwords nor credentials (spec §1.6).
 *
 * The complex of the trail is the complex of the audited model, never the
 * complex of the session: the audit cannot depend on the surface the write
 * came from. A record that belongs to a complex but cannot resolve it fails
 * explicitly (the trail would be lost silently otherwise); a platform object
 * (a catalog role, which belongs to no complex) is not audited and does not
 * fail the write.
 */
trait Auditable
{
    /**
     * The value kept in a snapshot for a redacted attribute.
     *
     * The key stays visible so the structure of the change (which fields
     * changed) remains readable, but the sensitive value never reaches the
     * snapshot.
     */
    public const AUDIT_REDACTED = '[redacted]';

    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        // Log creation
        static::created(function ($model) {
            $complexId = $model->auditResolvedComplexId();

            // A platform object is not audited: it belongs to no complex and
            // a trail without a complex is impossible (RN-C04).
            if ($complexId === null) {
                return;
            }

            AuditLog::logCreated(
                get_class($model),
                $model->getKey(),
                $model->auditSnapshot($model->getAttributes()),
                null,
                $complexId
            );
        });

        // Log updates (only changed attributes)
        static::updated(function ($model) {
            $changes = $model->getChanges();

            // Don't log if only timestamps changed
            $relevantChanges = array_diff_key($changes, array_flip(['updated_at', 'created_at']));
            if (empty($relevantChanges)) {
                return;
            }

            $complexId = $model->auditResolvedComplexId();

            if ($complexId === null) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $relevantChanges);

            AuditLog::logUpdated(
                get_class($model),
                $model->getKey(),
                $model->auditSnapshot($original),
                $model->auditSnapshot($relevantChanges),
                null,
                $complexId
            );
        });

        // Log deletions
        static::deleted(function ($model) {
            if (! $model->auditRecordsDeletion()) {
                return;
            }

            $complexId = $model->auditResolvedComplexId();

            if ($complexId === null) {
                return;
            }

            AuditLog::logDeleted(
                get_class($model),
                $model->getKey(),
                $model->auditSnapshot($model->getAttributes()),
                null,
                $complexId
            );
        });
    }

    /**
     * The builder of an audited model.
     *
     * A mass delete (`Model::query()->delete()`, `$relation->delete()`) does
     * not fire the model events, so the deleted rows would vanish without
     * leaving their trail. This builder deletes every row through the model,
     * so the audit promise does not depend on how the delete was written.
     */
    public function newEloquentBuilder($query): AuditableDeleteBuilder
    {
        return new AuditableDeleteBuilder($query);
    }

    /**
     * Delete the row of the model.
     *
     * The deletion of a single row goes straight to the database: the mass
     * delete of the builder removes the rows one by one through the model (so
     * each one leaves its trail), and this method is what keeps that promise
     * from calling itself again.
     */
    protected function performDeleteOnModel()
    {
        $this->setKeysForSaveQuery($this->newModelQuery())->toBase()->delete();

        $this->exists = false;
    }

    /**
     * The attributes that never reach a snapshot.
     *
     * @return list<string>
     */
    public function getAuditExcludedAttributes(): array
    {
        return ['created_at', 'updated_at', 'password', 'password_confirmation', 'remember_token'];
    }

    /**
     * If the deletion of the model must leave an audit record.
     *
     * A residential complex is its own audit container: when it is deleted
     * its trail goes with it (the `audit_logs` FK cascades), so there cannot
     * be a record pointing to the deleted complex.
     */
    public function auditRecordsDeletion(): bool
    {
        return true;
    }

    /**
     * Additional attributes this model does not want in the snapshots.
     *
     * The exclusion is always per model, never a global list: only the model
     * that holds a sensitive value knows how it has to be kept out of the
     * trail.
     *
     * @return list<string>
     */
    public function auditSensitiveAttributes(): array
    {
        return [];
    }

    /**
     * The complex the audited record belongs to.
     *
     * The models that are a complex themselves override it.
     */
    public function auditComplexId(): ?int
    {
        $complexId = $this->getAttribute('residential_complex_id');

        return $complexId === null ? null : (int) $complexId;
    }

    /**
     * Whether the record is a platform object: it belongs to no complex.
     *
     * A platform object (a catalog role) is skipped by the audit without
     * failing: it is an object of the platform, not of the complex, and a
     * trail without a complex is impossible (RN-C04). A record that belongs to
     * a complex but cannot resolve it is not a platform object: it fails
     * visibly, because the trail would be lost silently.
     */
    public function auditIsPlatformObject(): bool
    {
        return false;
    }

    /**
     * The complex of the trail, or null when the record is a platform object.
     *
     * @throws LogicException when the record belongs to a complex that cannot be resolved
     */
    public function auditResolvedComplexId(): ?int
    {
        $complexId = $this->auditComplexId();

        if ($complexId !== null) {
            return $complexId;
        }

        if ($this->auditIsPlatformObject()) {
            return null;
        }

        throw new LogicException(sprintf(
            'The %s record #%s belongs to a complex that cannot be resolved: its audit trail would be lost silently.',
            static::class,
            (string) $this->getKey()
        ));
    }

    /**
     * Audit snapshot of a set of attributes.
     *
     * It leaves out the excluded attributes and redacts the sensitive ones
     * (the key stays, the value does not), and it returns the JSON values in
     * their structured form (not as the raw string of the database).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditSnapshot(array $attributes): array
    {
        $excluded = $this->getAuditExcludedAttributes();
        $sensitive = $this->auditSensitiveAttributes();
        $snapshot = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            if (in_array($key, $sensitive, true)) {
                // The value never reaches the snapshot, but the key stays
                // visible: the structure of the change (which fields changed)
                // has to remain readable.
                $snapshot[$key] = $value === null ? null : self::AUDIT_REDACTED;

                continue;
            }

            $snapshot[$key] = $this->auditValue($key, $value);
        }

        return $snapshot;
    }

    /**
     * Normalizes the value of an attribute for the snapshot.
     */
    protected function auditValue(string $key, mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $casts = $this->getCasts();

        if (! isset($casts[$key])) {
            return $value;
        }

        if (! in_array($casts[$key], ['array', 'json', 'object', 'collection'], true)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return $decoded === null && $value !== 'null' ? $value : $decoded;
    }
}
