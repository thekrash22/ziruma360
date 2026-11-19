<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Trait para modelos que requieren auditoría automática (spec §1.6).
 *
 * El registro guarda quién (`actor_user_id`), cuándo (`occurred_at`), el
 * valor anterior y el nuevo (`before_data` / `after_data`) y el conjunto del
 * modelo auditado. Los snapshots nunca guardan contraseñas ni credenciales
 * (spec §1.6).
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
                $model->auditSnapshot($model->getAttributes()),
                null,
                $model->auditComplexId()
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

            $original = array_intersect_key($model->getOriginal(), $relevantChanges);

            AuditLog::logUpdated(
                get_class($model),
                $model->getKey(),
                $model->auditSnapshot($original),
                $model->auditSnapshot($relevantChanges),
                null,
                $model->auditComplexId()
            );
        });

        // Log deletions
        static::deleted(function ($model) {
            if (! $model->auditRecordsDeletion()) {
                return;
            }

            AuditLog::logDeleted(
                get_class($model),
                $model->getKey(),
                $model->auditSnapshot($model->getAttributes()),
                null,
                $model->auditComplexId()
            );
        });
    }

    /**
     * Get the list of attributes to exclude from audit logging.
     */
    public function getAuditExcludedAttributes(): array
    {
        return array_merge(
            ['created_at', 'updated_at', 'password', 'password_confirmation', 'remember_token'],
            $this->auditSensitiveAttributes()
        );
    }

    /**
     * Si el borrado del modelo debe dejar registro de auditoría.
     *
     * Un conjunto residencial es su propio contenedor de auditoría: al
     * borrarse, su traza se va con él (la FK de `audit_logs` va en cascada),
     * así que no puede haber un registro que apunte al conjunto borrado.
     */
    public function auditRecordsDeletion(): bool
    {
        return true;
    }

    /**
     * Atributos adicionales que este modelo no quiere en las instantáneas.
     *
     * @return list<string>
     */
    public function auditSensitiveAttributes(): array
    {
        return [];
    }

    /**
     * El conjunto al que pertenece el registro auditado.
     *
     * Los modelos que son un conjunto en sí mismos lo redefinen.
     */
    public function auditComplexId(): ?int
    {
        $complexId = $this->getAttribute('residential_complex_id');

        return $complexId === null ? null : (int) $complexId;
    }

    /**
     * Instantánea de auditoría de un conjunto de atributos.
     *
     * Excluye los atributos sensibles y devuelve los valores JSON en su
     * forma estructurada (no como la cadena cruda de la base).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function auditSnapshot(array $attributes): array
    {
        $excluded = $this->getAuditExcludedAttributes();
        $snapshot = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $excluded, true)) {
                continue;
            }

            $snapshot[$key] = $this->auditValue($key, $value);
        }

        return $snapshot;
    }

    /**
     * Normaliza el valor de un atributo para la instantánea.
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
