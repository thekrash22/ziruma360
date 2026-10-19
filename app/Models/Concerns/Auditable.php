<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Trait para modelos que requieren auditoría automática.
 */
trait Auditable
{
    /**
     * Boot the auditable trait.
     */
    public static function bootAuditable(): void
    {
        // Log creation
        static::created(function ($model) {
            AuditLog::logCreated(
                get_class($model),
                $model->getKey(),
                $model->getAttributes()
            );
        });

        // Log updates (only changed attributes)
        static::updated(function ($model) {
            $changes = $model->getChanges();
            $original = array_intersect_key($model->getOriginal(), $changes);

            // Don't log if only timestamps changed
            $relevantChanges = array_diff_key($changes, array_flip(['updated_at', 'created_at']));
            if (empty($relevantChanges)) {
                return;
            }

            AuditLog::logUpdated(
                get_class($model),
                $model->getKey(),
                $original,
                $changes
            );
        });

        // Log deletions
        static::deleted(function ($model) {
            AuditLog::logDeleted(
                get_class($model),
                $model->getKey(),
                $model->getAttributes()
            );
        });
    }

    /**
     * Get the list of attributes to exclude from audit logging.
     */
    public function getAuditExcludedAttributes(): array
    {
        return ['created_at', 'updated_at'];
    }
}
