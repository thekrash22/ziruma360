<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * unit_leases (spec §4.5 / S17).
 *
 * Arrendamiento de largo plazo (vivienda) de una unidad. Garantías a nivel
 * de motor:
 *   - FK compuesta (residential_complex_id, property_unit_id) -> property_units
 *     y FK compuestas (residential_complex_id, tenant_person_id) y
 *     (residential_complex_id, landlord_person_id) -> people, todas con
 *     ON DELETE RESTRICT: es imposible arrendar una unidad o a una persona
 *     de otro conjunto (ACE-C02) y una unidad con historial de arrendamiento
 *     no se borra, se desactiva (RN-S04).
 *   - CHECK de vigencia (ends_on >= starts_on) y CHECK de dominio de status.
 *   - delegated_permissions debe ser un objeto JSON y sus valores booleanos
 *     (el spec lo pide "JSONB validado"); el vocabulario concreto de permisos
 *     no lo define el spec, asi que no se restringe.
 *   - RN-P09 y RN-P10 se implementan en la migracion de unit_occupancies
 *     (que es la tabla que el arrendamiento crea o vincula).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE unit_leases (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                property_unit_id bigint NOT NULL,
                tenant_person_id bigint NOT NULL,
                landlord_person_id bigint NULL,
                starts_on date NOT NULL,
                ends_on date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                document_reference varchar(255) NULL,
                delegated_permissions jsonb NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT unit_leases_uuid_unique UNIQUE (uuid),
                CONSTRAINT unit_leases_period_check
                    CHECK (ends_on IS NULL OR ends_on >= starts_on),
                CONSTRAINT unit_leases_status_check
                    CHECK (status IN ('active', 'ended', 'terminated')),
                CONSTRAINT unit_leases_delegated_permissions_check
                    CHECK (delegated_permissions IS NULL OR jsonb_typeof(delegated_permissions) = 'object'),
                CONSTRAINT unit_leases_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT unit_leases_tenant_complex_fk
                    FOREIGN KEY (residential_complex_id, tenant_person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT unit_leases_landlord_complex_fk
                    FOREIGN KEY (residential_complex_id, landlord_person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        // Objetivo de la FK compuesta desde unit_occupancies: el arrendamiento
        // se identifica por (residential_complex_id, id).
        DB::statement('CREATE UNIQUE INDEX unit_leases_residential_complex_id_id_unique ON unit_leases (residential_complex_id, id)');

        // Los permisos delegables son un objeto JSON con valores booleanos. Un
        // CHECK no puede contener subconsultas, asi que la validacion de los
        // valores vive en un trigger; el CHECK del objeto queda como red de
        // seguridad en la propia tabla.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION unit_leases_delegated_permissions_check() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.delegated_permissions IS NOT NULL THEN
                    IF jsonb_typeof(NEW.delegated_permissions) <> 'object' THEN
                        RAISE EXCEPTION 'unit_leases: delegated_permissions debe ser un objeto JSON'
                            USING ERRCODE = '23514';
                    END IF;

                    IF EXISTS (
                        SELECT 1
                        FROM jsonb_each(NEW.delegated_permissions) AS entry
                        WHERE jsonb_typeof(entry.value) <> 'boolean'
                    ) THEN
                        RAISE EXCEPTION 'unit_leases: los permisos delegables deben ser valores booleanos'
                            USING ERRCODE = '23514';
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER unit_leases_delegated_permissions_trigger
            BEFORE INSERT OR UPDATE ON unit_leases
            FOR EACH ROW EXECUTE FUNCTION unit_leases_delegated_permissions_check()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS unit_leases_delegated_permissions_trigger ON unit_leases');
        DB::statement('DROP FUNCTION IF EXISTS unit_leases_delegated_permissions_check()');
        DB::statement('DROP INDEX IF EXISTS unit_leases_residential_complex_id_id_unique');
        DB::statement('DROP TABLE IF EXISTS unit_leases');
    }

};
