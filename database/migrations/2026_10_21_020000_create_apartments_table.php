<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * apartments (spec §3.6 / S12).
 *
 * Extensión 1:1 de property_units:
 *   - UNIQUE (property_unit_id)
 *   - FK compuesta (residential_complex_id, property_unit_id) -> property_units (residential_complex_id, id)
 *   - TEC-S01: trigger de base de datos que exige unit_type = 'apartment' en la unidad base.
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
            DB::statement('DROP TRIGGER IF EXISTS apartments_tec_s01_trigger ON apartments');
            DB::statement('DROP FUNCTION IF EXISTS apartments_enforce_apartment_unit_type()');
        } else {
            DB::statement('DROP TRIGGER IF EXISTS apartments_tec_s01_insert');
            DB::statement('DROP TRIGGER IF EXISTS apartments_tec_s01_update');
        }

        DB::statement('DROP TABLE IF EXISTS apartments');
    }

    /**
     * PostgreSQL.
     */
    private function upPostgres(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE apartments (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL,
                property_unit_id bigint NOT NULL,
                apartment_type_id bigint NULL REFERENCES apartment_types(id) ON DELETE SET NULL,
                bedrooms smallint NOT NULL DEFAULT 0,
                bathrooms smallint NOT NULL DEFAULT 0,
                operational_status varchar(255) NOT NULL DEFAULT 'vacant',
                short_term_rental_enabled boolean NOT NULL DEFAULT false,
                features jsonb NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT apartments_uuid_unique UNIQUE (uuid),
                CONSTRAINT apartments_property_unit_id_unique UNIQUE (property_unit_id),
                CONSTRAINT apartments_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE CASCADE
            )
        SQL);

        // TEC-S01: la unidad base debe ser de tipo apartment (garantizado en BD).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION apartments_enforce_apartment_unit_type() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM property_units pu
                    WHERE pu.id = NEW.property_unit_id AND pu.unit_type = 'apartment'
                ) THEN
                    RAISE EXCEPTION 'TEC-S01: la unidad base % debe ser de tipo apartment', NEW.property_unit_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER apartments_tec_s01_trigger
            BEFORE INSERT OR UPDATE ON apartments
            FOR EACH ROW EXECUTE FUNCTION apartments_enforce_apartment_unit_type()
        SQL);
    }

    /**
     * SQLite (entorno de pruebas): misma semántica garantizada por triggers.
     */
    private function upSqlite(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE apartments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid varchar(36) NOT NULL,
                residential_complex_id INTEGER NOT NULL,
                property_unit_id INTEGER NOT NULL,
                apartment_type_id INTEGER NULL REFERENCES apartment_types(id) ON DELETE SET NULL,
                bedrooms smallint NOT NULL DEFAULT 0,
                bathrooms smallint NOT NULL DEFAULT 0,
                operational_status varchar(255) NOT NULL DEFAULT 'vacant',
                short_term_rental_enabled tinyint(1) NOT NULL DEFAULT 0,
                features text NULL,
                created_at datetime NULL,
                updated_at datetime NULL,
                CONSTRAINT apartments_uuid_unique UNIQUE (uuid),
                CONSTRAINT apartments_property_unit_id_unique UNIQUE (property_unit_id),
                CONSTRAINT apartments_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE CASCADE
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER apartments_tec_s01_insert
            BEFORE INSERT ON apartments
            BEGIN
                SELECT RAISE(ABORT, 'TEC-S01: la unidad base debe ser de tipo apartment')
                WHERE NOT EXISTS (
                    SELECT 1 FROM property_units pu
                    WHERE pu.id = NEW.property_unit_id AND pu.unit_type = 'apartment'
                );
            END
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER apartments_tec_s01_update
            BEFORE UPDATE ON apartments
            BEGIN
                SELECT RAISE(ABORT, 'TEC-S01: la unidad base debe ser de tipo apartment')
                WHERE NOT EXISTS (
                    SELECT 1 FROM property_units pu
                    WHERE pu.id = NEW.property_unit_id AND pu.unit_type = 'apartment'
                );
            END
        SQL);
    }
};
