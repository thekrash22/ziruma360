<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * employments (spec §4.6 / S20).
 *
 * Persona que trabaja para el conjunto, para una unidad o como contratista
 * con acceso recurrente. Garantías a nivel de motor:
 *   - FK compuestas (residential_complex_id, person_id) -> people y
 *     (residential_complex_id, employer_property_unit_id) -> property_units,
 *     ambas con ON DELETE RESTRICT: es imposible registrar un empleo con
 *     una persona o una unidad de otro conjunto (ACE-C02) y una unidad con
 *     historial de empleo no se borra, se desactiva (RN-S04).
 *   - CHECK de vigencia (valid_until >= valid_from), CHECK de dominio de
 *     employment_type y de status, CHECK de schedule (objeto JSON) y CHECK
 *     de coherencia entre el tipo de empleo y la unidad empleadora: el
 *     personal del conjunto no tiene unidad y el empleado domestico
 *     siempre la tiene.
 *   - RN-P11: el acceso al sistema exige que la persona tenga cuenta
 *     (people.user_id) y una membresía activa en el conjunto. Tener empleo
 *     no implica tener cuenta: un empleo sin user_id es valido y no
 *     habilita acceso.
 *   - Una persona no puede tener dos empleos activos del mismo tipo para
 *     el mismo empleador.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE employments (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                person_id bigint NOT NULL,
                employer_property_unit_id bigint NULL,
                employment_type varchar(255) NOT NULL,
                position varchar(255) NULL,
                schedule jsonb NULL,
                valid_from date NOT NULL,
                valid_until date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                access_authorized boolean NOT NULL DEFAULT false,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT employments_uuid_unique UNIQUE (uuid),
                CONSTRAINT employments_employment_type_check
                    CHECK (employment_type IN ('complex_staff', 'unit_domestic', 'contractor')),
                CONSTRAINT employments_status_check
                    CHECK (status IN ('active', 'inactive', 'ended')),
                CONSTRAINT employments_period_check
                    CHECK (valid_until IS NULL OR valid_until >= valid_from),
                CONSTRAINT employments_schedule_check
                    CHECK (schedule IS NULL OR jsonb_typeof(schedule) = 'object'),
                CONSTRAINT employments_employer_presence_check
                    CHECK (
                        employment_type NOT IN ('complex_staff', 'unit_domestic')
                        OR (employment_type = 'complex_staff' AND employer_property_unit_id IS NULL)
                        OR (employment_type = 'unit_domestic' AND employer_property_unit_id IS NOT NULL)
                    ),
                CONSTRAINT employments_person_complex_fk
                    FOREIGN KEY (residential_complex_id, person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT employments_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, employer_property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        DB::statement('CREATE INDEX employments_person_index ON employments (person_id)');
        DB::statement('CREATE INDEX employments_employer_index ON employments (employer_property_unit_id) WHERE employer_property_unit_id IS NOT NULL');

        // Red de seguridad a nivel de almacenamiento: un solo empleo activo
        // por persona, tipo y empleador. El indice no depende del nivel de
        // aislamiento de la transaccion; el trigger da el mensaje legible.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX employments_single_active_unique
            ON employments (person_id, employment_type, (COALESCE(employer_property_unit_id, 0)))
            WHERE status = 'active'
        SQL);

        // RN-P11: el acceso al sistema exige cuenta y membresía activa. Un
        // CHECK no puede consultar otras tablas, asi que la garantía vive en
        // un trigger.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION employments_access_authorization_check() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                person_user_id bigint;
            BEGIN
                IF NOT NEW.access_authorized THEN
                    RETURN NEW;
                END IF;

                SELECT p.user_id INTO person_user_id
                FROM people p
                WHERE p.id = NEW.person_id
                  AND p.residential_complex_id = NEW.residential_complex_id;

                IF person_user_id IS NULL THEN
                    RAISE EXCEPTION 'employments: el acceso autorizado exige que la persona tenga cuenta de acceso (RN-P11)'
                        USING ERRCODE = '23514';
                END IF;

                IF NOT EXISTS (
                    SELECT 1
                    FROM residential_complex_memberships m
                    WHERE m.residential_complex_id = NEW.residential_complex_id
                      AND m.user_id = person_user_id
                      AND m.status = 'active'
                ) THEN
                    RAISE EXCEPTION 'employments: el acceso autorizado exige una membresía activa en el conjunto (RN-P11)'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER employments_access_authorization_trigger
            BEFORE INSERT OR UPDATE ON employments
            FOR EACH ROW EXECUTE FUNCTION employments_access_authorization_check()
        SQL);

        // Una persona no puede tener dos empleos activos del mismo tipo para
        // el mismo empleador (ni unidad, ni conjunto cuando el empleador es
        // nulo).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION employments_single_active_check() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status <> 'active' THEN
                    RETURN NEW;
                END IF;

                -- Serializa el control: dos altas concurrentes del mismo
                -- empleo (misma persona, tipo y empleador) se ordenan con
                -- este bloqueo de fila, de modo que la segunda ve el empleo
                -- ya confirmado y es rechazada.
                PERFORM 1
                FROM people p
                WHERE p.id = NEW.person_id
                  AND p.residential_complex_id = NEW.residential_complex_id
                FOR UPDATE;

                IF EXISTS (
                    SELECT 1
                    FROM employments e
                    WHERE e.id <> NEW.id
                      AND e.person_id = NEW.person_id
                      AND e.employment_type = NEW.employment_type
                      AND e.employer_property_unit_id IS NOT DISTINCT FROM NEW.employer_property_unit_id
                      AND e.status = 'active'
                ) THEN
                    RAISE EXCEPTION 'employments: la persona ya tiene un empleo activo de ese tipo para ese empleador'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER employments_single_active_trigger
            BEFORE INSERT OR UPDATE ON employments
            FOR EACH ROW EXECUTE FUNCTION employments_single_active_check()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS employments_single_active_trigger ON employments');
        DB::statement('DROP FUNCTION IF EXISTS employments_single_active_check()');
        DB::statement('DROP TRIGGER IF EXISTS employments_access_authorization_trigger ON employments');
        DB::statement('DROP FUNCTION IF EXISTS employments_access_authorization_check()');
        DB::statement('DROP INDEX IF EXISTS employments_single_active_unique');
        DB::statement('DROP INDEX IF EXISTS employments_employer_index');
        DB::statement('DROP INDEX IF EXISTS employments_person_index');
        DB::statement('DROP TABLE IF EXISTS employments');
    }
};
