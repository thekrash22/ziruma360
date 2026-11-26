<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro de auditoría (spec §1.6).
 *
 * RN-C04: todo cambio de políticas queda auditado (quién, cuándo, valor
 * anterior y nuevo) y no reescribe registros históricos.
 *
 * El conjunto del registro es el del modelo auditado, no el contexto de
 * sesión: la auditoría no puede depender de la superficie por la que entró la
 * escritura.
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'residential_complex_id',
        'actor_user_id',
        'action',
        'entity_type',
        'entity_id',
        'before_data',
        'after_data',
        'occurred_at',
        'correlation_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the actor (user who performed the action).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Get the complex this log belongs to.
     */
    public function residentialComplex(): BelongsTo
    {
        return $this->belongsTo(ResidentialComplex::class);
    }

    /**
     * Log an entity creation.
     */
    public static function logCreated(
        string $entityType,
        mixed $entityId,
        array $data,
        ?int $actorId = null,
        ?int $complexId = null
    ): self {
        return self::createLog('created', $entityType, $entityId, null, $data, $actorId, $complexId);
    }

    /**
     * Log an entity update.
     */
    public static function logUpdated(
        string $entityType,
        mixed $entityId,
        array $before,
        array $after,
        ?int $actorId = null,
        ?int $complexId = null
    ): self {
        return self::createLog('updated', $entityType, $entityId, $before, $after, $actorId, $complexId);
    }

    /**
     * Log an entity deletion.
     */
    public static function logDeleted(
        string $entityType,
        mixed $entityId,
        array $data,
        ?int $actorId = null,
        ?int $complexId = null
    ): self {
        return self::createLog('deleted', $entityType, $entityId, $data, null, $actorId, $complexId);
    }

    /**
     * Internal method to create audit log entries.
     */
    private static function createLog(
        string $action,
        string $entityType,
        mixed $entityId,
        ?array $before,
        ?array $after,
        ?int $actorId,
        ?int $complexId
    ): self {
        $actorId ??= auth()->id() === null ? null : (int) auth()->id();

        // Un registro sin conjunto no se puede auditar: se falla de forma
        // explicita en lugar de perder la traza en silencio (RN-C04). El
        // conjunto es siempre el del modelo auditado, nunca el de la sesion:
        // la auditoria no puede depender de la superficie por la que entro la
        // escritura.
        if ($complexId === null) {
            throw new LogicException('No se puede auditar un registro sin conjunto residencial.');
        }

        return self::create([
            'residential_complex_id' => $complexId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'before_data' => $before,
            'after_data' => $after,
            'occurred_at' => now(),
            'correlation_id' => request()->header('X-Correlation-Id'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
