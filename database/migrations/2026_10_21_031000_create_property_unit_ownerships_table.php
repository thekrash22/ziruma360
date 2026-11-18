<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * property_unit_ownerships (spec §4.3 / S15).
 *
 * Historial de titularidad sobre una unidad. Garantías a nivel de motor:
 *   - FK compuesta (residential_complex_id, property_unit_id) -> property_units
 *     y FK compuesta (residential_complex_id, person_id) -> people, ambas con
 *     ON DELETE RESTRICT: es imposible mezclar conjuntos (ACE-C02) y una
 *     unidad o persona con historial de titularidad no se borra, se desactiva
 *     (RN-S04).
 *   - CHECK percentage 0-100 y CHECK de vigencia (valid_until >= valid_from).
 *   - UNIQUE parcial (property_unit_id, person_id, ownership_type) WHERE
 *     status = 'active': una única titularidad vigente por unidad, persona y tipo.
 *   - RN-P04: la suma de los porcentajes de las titularidades vigentes de la
 *     misma unidad no puede superar 100.
 *   - RN-P05: la titularidad no se edita en sitio; se cierra el registro
 *     vigente y se crea uno nuevo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $this->upPostgres();
        } else {
            $this->upSqlite();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS property_unit_ownerships_rn_p04_insert_trigger ON property_unit_ownerships');
            DB::statement('DROP TRIGGER IF EXISTS property_unit_ownerships_rn_p04_update_trigger ON property_unit_ownerships');
            DB::statement('DROP TRIGGER IF EXISTS property_unit_ownerships_rn_p05_update_trigger ON property_unit_ownerships');
            DB::statement('DROP FUNCTION IF EXISTS property_unit_ownerships_rn_p04_insert()');
            DB::statement('DROP FUNCTION IF EXISTS property_unit_ownerships_rn_p04_update()');
            DB::statement('DROP FUNCTION IF EXISTS property_unit_ownerships_rn_p05_update()');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS property_unit_ownerships_rn_p04_insert');
            DB::statement('DROP TRIGGER IF EXISTS property_unit_ownerships_rn_p04_update');
            DB::statement('DROP TRIGGER IF EXISTS property_unit_ownerships_rn_p05_update');
        }

        DB::statement('DROP TABLE IF EXISTS property_unit_ownerships');
    }

    /**
     * PostgreSQL.
     */
    private function upPostgres(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE property_unit_ownerships (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                property_unit_id bigint NOT NULL,
                person_id bigint NOT NULL,
                ownership_type varchar(255) NOT NULL,
                percentage numeric(5, 2) NULL,
                valid_from date NOT NULL,
                valid_until date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                document_reference varchar(255) NULL,
                notes text NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT property_unit_ownerships_uuid_unique UNIQUE (uuid),
                CONSTRAINT property_unit_ownerships_percentage_check
                    CHECK (percentage IS NULL OR (percentage >= 0 AND percentage <= 100)),
                CONSTRAINT property_unit_ownerships_validity_check
                    CHECK (valid_until IS NULL OR valid_until >= valid_from),
                CONSTRAINT property_unit_ownerships_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT property_unit_ownerships_person_complex_fk
                    FOREIGN KEY (residential_complex_id, person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        // Una única titularidad vigente por unidad, persona y tipo.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX property_unit_ownerships_active_unique
            ON property_unit_ownerships (property_unit_id, person_id, ownership_type)
            WHERE status = 'active'
        SQL);

        // RN-P04 (INSERT): la suma de los porcentajes de las titularidades
        // vigentes de la misma unidad (a la fecha NEW.valid_from) más el
        // porcentaje nuevo no puede superar 100.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION property_unit_ownerships_rn_p04_insert() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                total numeric(12, 2);
            BEGIN
                IF NEW.percentage IS NULL OR NEW.status <> 'active' THEN
                    RETURN NEW;
                END IF;

                SELECT COALESCE(SUM(COALESCE(puo.percentage, 0)), 0) INTO total
                FROM property_unit_ownerships puo
                WHERE puo.property_unit_id = NEW.property_unit_id
                  AND puo.status = 'active'
                  AND puo.valid_from <= NEW.valid_from
                  AND (puo.valid_until IS NULL OR puo.valid_until >= NEW.valid_from);

                IF total + NEW.percentage > 100 THEN
                    RAISE EXCEPTION 'RN-P04: la suma de los porcentajes de las titularidades vigentes de la unidad % supera 100 por ciento', NEW.property_unit_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_ownerships_rn_p04_insert_trigger
            BEFORE INSERT ON property_unit_ownerships
            FOR EACH ROW EXECUTE FUNCTION property_unit_ownerships_rn_p04_insert()
        SQL);

        // RN-P04 (UPDATE): misma comprobación, excluyendo la fila actualizada.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION property_unit_ownerships_rn_p04_update() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                total numeric(12, 2);
            BEGIN
                IF NEW.percentage IS NULL OR NEW.status <> 'active' THEN
                    RETURN NEW;
                END IF;

                SELECT COALESCE(SUM(COALESCE(puo.percentage, 0)), 0) INTO total
                FROM property_unit_ownerships puo
                WHERE puo.property_unit_id = NEW.property_unit_id
                  AND puo.id <> OLD.id
                  AND puo.status = 'active'
                  AND puo.valid_from <= NEW.valid_from
                  AND (puo.valid_until IS NULL OR puo.valid_until >= NEW.valid_from);

                IF total + NEW.percentage > 100 THEN
                    RAISE EXCEPTION 'RN-P04: la suma de los porcentajes de las titularidades vigentes de la unidad % supera 100 por ciento', NEW.property_unit_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_ownerships_rn_p04_update_trigger
            BEFORE UPDATE ON property_unit_ownerships
            FOR EACH ROW EXECUTE FUNCTION property_unit_ownerships_rn_p04_update()
        SQL);

        // RN-P05: la titularidad no se edita en sitio. Cerrar el registro
        // (status distinto de 'active') sí está permitido, inclusoinformando
        // valid_until en la misma actualización.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION property_unit_ownerships_rn_p05_update() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.status = 'active' AND NEW.status = 'active'
                   AND (
                       NEW.property_unit_id IS DISTINCT FROM OLD.property_unit_id
                    OR NEW.person_id IS DISTINCT FROM OLD.person_id
                    OR NEW.ownership_type IS DISTINCT FROM OLD.ownership_type
                    OR NEW.percentage IS DISTINCT FROM OLD.percentage
                    OR NEW.valid_from IS DISTINCT FROM OLD.valid_from
                   ) THEN
                    RAISE EXCEPTION 'RN-P05: la titularidad no se edita en sitio: cierre el registro vigente y cree uno nuevo'
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_ownerships_rn_p05_update_trigger
            BEFORE UPDATE ON property_unit_ownerships
            FOR EACH ROW EXECUTE FUNCTION property_unit_ownerships_rn_p05_update()
        SQL);
    }

    /**
     * SQLite (entorno de pruebas): misma semántica garantizada por triggers.
     */
    private function upSqlite(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE property_unit_ownerships (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid varchar(36) NOT NULL,
                residential_complex_id INTEGER NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                property_unit_id INTEGER NOT NULL,
                person_id INTEGER NOT NULL,
                ownership_type varchar(255) NOT NULL,
                percentage decimal(5, 2) NULL,
                valid_from date NOT NULL,
                valid_until date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                document_reference varchar(255) NULL,
                notes text NULL,
                created_at datetime NULL,
                updated_at datetime NULL,
                CONSTRAINT property_unit_ownerships_uuid_unique UNIQUE (uuid),
                CONSTRAINT property_unit_ownerships_percentage_check
                    CHECK (percentage IS NULL OR (percentage >= 0 AND percentage <= 100)),
                CONSTRAINT property_unit_ownerships_validity_check
                    CHECK (valid_until IS NULL OR valid_until >= valid_from),
                CONSTRAINT property_unit_ownerships_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT property_unit_ownerships_person_complex_fk
                    FOREIGN KEY (residential_complex_id, person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX property_unit_ownerships_active_unique
            ON property_unit_ownerships (property_unit_id, person_id, ownership_type)
            WHERE status = 'active'
        SQL);

        // RN-P04 (INSERT).
        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_ownerships_rn_p04_insert
            BEFORE INSERT ON property_unit_ownerships
            BEGIN
                SELECT RAISE(ABORT, 'RN-P04: la suma de los porcentajes de las titularidades vigentes de la unidad supera 100 por ciento')
                WHERE NEW.percentage IS NOT NULL
                  AND NEW.status = 'active'
                  AND (
                    SELECT COALESCE(SUM(COALESCE(puo.percentage, 0)), 0)
                    FROM property_unit_ownerships puo
                    WHERE puo.property_unit_id = NEW.property_unit_id
                      AND puo.status = 'active'
                      AND puo.valid_from <= NEW.valid_from
                      AND (puo.valid_until IS NULL OR puo.valid_until >= NEW.valid_from)
                  ) + NEW.percentage > 100;
            END
        SQL);

        // RN-P04 (UPDATE), excluyendo la fila que se está actualizando.
        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_ownerships_rn_p04_update
            BEFORE UPDATE ON property_unit_ownerships
            BEGIN
                SELECT RAISE(ABORT, 'RN-P04: la suma de los porcentajes de las titularidades vigentes de la unidad supera 100 por ciento')
                WHERE NEW.percentage IS NOT NULL
                  AND NEW.status = 'active'
                  AND (
                    SELECT COALESCE(SUM(COALESCE(puo.percentage, 0)), 0)
                    FROM property_unit_ownerships puo
                    WHERE puo.property_unit_id = NEW.property_unit_id
                      AND puo.id <> OLD.id
                      AND puo.status = 'active'
                      AND puo.valid_from <= NEW.valid_from
                      AND (puo.valid_until IS NULL OR puo.valid_until >= NEW.valid_from)
                  ) + NEW.percentage > 100;
            END
        SQL);

        // RN-P05: la titularidad no se edita en sitio.
        DB::statement(<<<'SQL'
            CREATE TRIGGER property_unit_ownerships_rn_p05_update
            BEFORE UPDATE ON property_unit_ownerships
            BEGIN
                SELECT RAISE(ABORT, 'RN-P05: la titularidad no se edita en sitio: cierre el registro vigente y cree uno nuevo')
                WHERE OLD.status = 'active'
                  AND NEW.status = 'active'
                  AND (
                        NEW.property_unit_id IS NOT OLD.property_unit_id
                     OR NEW.person_id IS NOT OLD.person_id
                     OR NEW.ownership_type IS NOT OLD.ownership_type
                     OR NEW.percentage IS NOT OLD.percentage
                     OR NEW.valid_from IS NOT OLD.valid_from
                  );
            END
        SQL);
    }
};
