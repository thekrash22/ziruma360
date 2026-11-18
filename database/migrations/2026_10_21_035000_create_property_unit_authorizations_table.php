<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * property_unit_authorizations (spec §4.8 / S25).
 *
 * Quién puede actuar sobre un inmueble (distinto de ser titular o
 * residente).
 *
 * Garantías a nivel de motor (SQL crudo porque el Blueprint de Laravel no
 * soporta índices parciales ni CHECK con funciones JSONB):
 *   - FK compuestas RESTRICT hacia property_units y people: la unidad y la
 *     persona autorizadas pertenecen al mismo conjunto que la fila
 *     (ACE-C02 / ACE-P07).
 *   - RN-P15: la vigencia se verifica al ejecutar, no al crear. El estado
 *     solo distingue activa de revocada; lo que concede permisos es el
 *     periodo (valid_from..valid_until) evaluado en el momento de la
 *     operacion, expuesto por la vista
 *     `effective_property_unit_authorizations`.
 *   - RN-P16: revocar conserva la fila y su historial, y una revocacion
 *     es irreversible (no se puede volver a `active` ni perder la marca de
 *     revocacion).
 *   - RN-P10 (parte diferida desde T13): al terminar un arrendamiento se
 *     revocan de forma controlada las autorizaciones que dependen de el.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE property_unit_authorizations (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                property_unit_id bigint NOT NULL,
                person_id bigint NOT NULL,
                user_id bigint NULL REFERENCES users(id) ON DELETE SET NULL,
                lease_id bigint NULL,
                authorization_type varchar(255) NOT NULL,
                scope jsonb NOT NULL DEFAULT '[]'::jsonb,
                valid_from timestamp(0) without time zone NOT NULL,
                valid_until timestamp(0) without time zone NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                granted_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
                revoked_by bigint NULL REFERENCES users(id) ON DELETE SET NULL,
                revoked_at timestamp(0) without time zone NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT property_unit_authorizations_uuid_unique UNIQUE (uuid),
                CONSTRAINT property_unit_authorizations_period_check
                    CHECK (valid_until IS NULL OR valid_until >= valid_from),
                CONSTRAINT property_unit_authorizations_status_check
                    CHECK (status IN ('active', 'revoked')),
                CONSTRAINT property_unit_authorizations_type_check
                    CHECK (authorization_type IN ('manager', 'host', 'delegate')),
                CONSTRAINT property_unit_authorizations_scope_check
                    CHECK (jsonb_typeof(scope) = 'array'),
                CONSTRAINT property_unit_authorizations_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT property_unit_authorizations_person_complex_fk
                    FOREIGN KEY (residential_complex_id, person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT property_unit_authorizations_lease_complex_fk
                    FOREIGN KEY (residential_complex_id, lease_id)
                    REFERENCES unit_leases (residential_complex_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        DB::statement('CREATE INDEX property_unit_authorizations_property_unit_index ON property_unit_authorizations (property_unit_id)');
        DB::statement('CREATE INDEX property_unit_authorizations_person_index ON property_unit_authorizations (person_id)');
        DB::statement('CREATE INDEX property_unit_authorizations_user_index ON property_unit_authorizations (user_id)');
        DB::statement('CREATE INDEX property_unit_authorizations_lease_index ON property_unit_authorizations (lease_id)');

        // Autorizaciones vigentes por unidad y persona: la consulta de
        // permisos efectivos es la operacion frecuente (RN-P15).
        DB::statement(<<<'SQL'
            CREATE INDEX property_unit_authorizations_active_unit_person_index
            ON property_unit_authorizations (property_unit_id, person_id)
            WHERE status = 'active'
        SQL);

        // RN-P15: la vigencia se resuelve en el momento de la operacion. La
        // vista deja explicito que una autorizacion futura o vencida no
        // concede permisos aunque su estado siga siendo `active`.
        DB::statement(<<<'SQL'
            CREATE VIEW effective_property_unit_authorizations AS
            SELECT *
            FROM property_unit_authorizations
            WHERE status = 'active'
              AND valid_from <= now()
              AND (valid_until IS NULL OR valid_until >= now())
        SQL);

        // El scope es la lista de permisos concedidos: tiene que ser una
        // lista de cadenas no vacias.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION property_unit_authorizations_scope_check() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                item jsonb;
            BEGIN
                IF jsonb_typeof(NEW.scope) <> 'array' THEN
                    RAISE EXCEPTION 'property_unit_authorizations: scope debe ser una lista de permisos'
                        USING ERRCODE = 'check_violation';
                END IF;

                FOR item IN SELECT value FROM jsonb_array_elements(NEW.scope) LOOP
                    IF jsonb_typeof(item) <> 'string' OR btrim(item #>> '{}') = '' THEN
                        RAISE EXCEPTION 'property_unit_authorizations: cada permiso de scope debe ser una cadena no vacia'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END LOOP;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_authorizations_scope_trigger
            BEFORE INSERT OR UPDATE ON property_unit_authorizations
            FOR EACH ROW EXECUTE FUNCTION property_unit_authorizations_scope_check()
        SQL);

        // RN-P16: revocar conserva la fila y su historial, y la revocacion es
        // irreversible. `revoked_at` se completa solo si no viene informado.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION property_unit_authorizations_revocation_guard() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'revoked' THEN
                    IF NEW.status <> 'revoked' THEN
                        RAISE EXCEPTION 'property_unit_authorizations: una autorizacion revocada no puede volver a estar vigente'
                            USING ERRCODE = 'check_violation';
                    END IF;

                    IF OLD.revoked_at IS DISTINCT FROM NEW.revoked_at
                        OR OLD.revoked_by IS DISTINCT FROM NEW.revoked_by THEN
                        RAISE EXCEPTION 'property_unit_authorizations: una autorizacion revocada no puede perder su marca de revocacion'
                            USING ERRCODE = 'check_violation';
                    END IF;
                END IF;

                IF NEW.status = 'revoked' AND NEW.revoked_at IS NULL THEN
                    NEW.revoked_at = now();
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_authorizations_revocation_trigger
            BEFORE UPDATE ON property_unit_authorizations
            FOR EACH ROW EXECUTE FUNCTION property_unit_authorizations_revocation_guard()
        SQL);

        // Coherencia de la cuenta: si la autorizacion trae `user_id`, esa
        // cuenta es la de la persona autorizada. Una persona sin cuenta
        // puede tener autorizacion con `user_id` nulo.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION property_unit_authorizations_user_check() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.user_id IS NOT NULL AND NOT EXISTS (
                    SELECT 1 FROM people WHERE id = NEW.person_id AND user_id = NEW.user_id
                ) THEN
                    RAISE EXCEPTION 'property_unit_authorizations: la cuenta no corresponde a la persona autorizada'
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_authorizations_user_trigger
            BEFORE INSERT OR UPDATE ON property_unit_authorizations
            FOR EACH ROW EXECUTE FUNCTION property_unit_authorizations_user_check()
        SQL);

        // RN-P10 (parte diferida desde T13): al terminar un arrendamiento se
        // revocan de forma controlada las autorizaciones que dependen de el.
        // La revocacion conserva la fila y su historial.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION unit_leases_revoke_dependent_authorizations() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'active' AND NEW.status IN ('ended', 'terminated') THEN
                    UPDATE property_unit_authorizations
                    SET status = 'revoked',
                        revoked_at = now(),
                        updated_at = now()
                    WHERE lease_id = NEW.id
                      AND status = 'active';
                END IF;

                RETURN NEW;
            END;
            $$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER unit_leases_revoke_dependent_authorizations_trigger
            AFTER UPDATE ON unit_leases
            FOR EACH ROW EXECUTE FUNCTION unit_leases_revoke_dependent_authorizations()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS unit_leases_revoke_dependent_authorizations_trigger ON unit_leases');
        DB::statement('DROP FUNCTION IF EXISTS unit_leases_revoke_dependent_authorizations()');
        DB::statement('DROP TRIGGER IF EXISTS property_unit_authorizations_user_trigger ON property_unit_authorizations');
        DB::statement('DROP FUNCTION IF EXISTS property_unit_authorizations_user_check()');
        DB::statement('DROP TRIGGER IF EXISTS property_unit_authorizations_revocation_trigger ON property_unit_authorizations');
        DB::statement('DROP FUNCTION IF EXISTS property_unit_authorizations_revocation_guard()');
        DB::statement('DROP TRIGGER IF EXISTS property_unit_authorizations_scope_trigger ON property_unit_authorizations');
        DB::statement('DROP FUNCTION IF EXISTS property_unit_authorizations_scope_check()');
        DB::statement('DROP VIEW IF EXISTS effective_property_unit_authorizations');
        DB::statement('DROP TABLE IF EXISTS property_unit_authorizations');
    }
};
