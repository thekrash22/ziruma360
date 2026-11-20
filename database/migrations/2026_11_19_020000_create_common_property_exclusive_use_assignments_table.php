<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * common_property_exclusive_use_assignments (spec §3.9 / S54).
 *
 * Un bien comun puede asignarse en uso exclusivo a una unidad beneficiaria.
 * RN-S06: el bien comun asignado en uso exclusivo NO se registra como
 * propiedad privada; la asignacion se separa de la titularidad legal, asi que
 * esta tabla no toca `property_unit_ownerships`.
 *
 * Las garantias se implementan a nivel motor con SQL crudo porque el Blueprint
 * de Laravel no soporta:
 *   - FK compuesta (residential_complex_id, common_area_id) -> common_areas
 *     (residential_complex_id, id)
 *   - FK compuesta (residential_complex_id, property_unit_id) -> property_units
 *     (residential_complex_id, id), que rechaza cruces entre conjuntos
 *     (ACE-C02/ACE-P07)
 *   - CHECK de vigencia (valid_until >= valid_from)
 *   - CHECK de dominio de status
 *   - ON DELETE RESTRICT en las FKs de conjunto, area comun y unidad: un bien
 *     comun, una unidad o un conjunto con historial de asignaciones no se
 *     borra: se desactiva y la asignacion se cierra por estado (ended/revoked)
 *   - indice unico parcial por area comun entre las asignaciones activas: solo
 *     hay una asignacion vigente por bien comun, la anterior se cierra antes
 *   - trigger BEFORE DELETE que impide el borrado duro de una asignacion: el
 *     historial es inmutable y se cierra por estado, nunca se borra
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE common_property_exclusive_use_assignments (
                id bigserial PRIMARY KEY,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE RESTRICT,
                common_area_id bigint NOT NULL,
                property_unit_id bigint NOT NULL,
                valid_from date NOT NULL,
                valid_until date NULL,
                document_reference varchar(255) NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT common_property_exclusive_use_assignments_common_area_fk
                    FOREIGN KEY (residential_complex_id, common_area_id)
                    REFERENCES common_areas (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT common_property_exclusive_use_assignments_property_unit_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT common_property_exclusive_use_assignments_validity_check
                    CHECK (valid_until IS NULL OR valid_until >= valid_from),
                CONSTRAINT common_property_exclusive_use_assignments_status_check
                    CHECK (status IN ('active', 'ended', 'revoked'))
            )
        SQL);

        // Solo una asignacion de uso exclusivo activa por bien comun: la
        // anterior se cierra por estado (ended/revoked) antes de abrir la
        // siguiente, asi dos unidades nunca tienen el uso exclusivo del mismo
        // bien comun a la vez.
        DB::statement("CREATE UNIQUE INDEX common_property_exclusive_use_assignments_active_area_unique ON common_property_exclusive_use_assignments (residential_complex_id, common_area_id) WHERE status = 'active'");

        // El historial de las asignaciones es inmutable: la asignacion se cierra
        // por estado (ended/revoked) y nunca se borra. La garantia vive en el
        // motor, de modo que ningun camino de borrado (Eloquent, SQL directo o
        // cascada) pueda destruir el registro.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION z360_forbid_exclusive_use_assignment_delete()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION
                    'las asignaciones de uso exclusivo no se borran: cerra la asignacion por estado (ended/revoked)'
                    USING ERRCODE = 'restrict_violation';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER common_property_exclusive_use_assignments_forbid_delete
            BEFORE DELETE ON common_property_exclusive_use_assignments
            FOR EACH ROW EXECUTE FUNCTION z360_forbid_exclusive_use_assignment_delete()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS common_property_exclusive_use_assignments');
        DB::statement('DROP FUNCTION IF EXISTS z360_forbid_exclusive_use_assignment_delete()');
    }
};
