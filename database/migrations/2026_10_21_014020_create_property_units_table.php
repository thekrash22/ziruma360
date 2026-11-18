<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * property_units (spec §3.5 / S11).
 *
 * La unicidad del número y la coherencia piso↔edificio se implementan a nivel
 * motor con SQL crudo porque el Blueprint de Laravel no soporta:
 *   - UNIQUE NULLS NOT DISTINCT (PostgreSQL 15+) para cubrir building_id nulo
 *   - FK compuesta (building_id, building_floor_id) -> building_floors
 *     (ON DELETE RESTRICT: un piso con unidades no se borra, RN-S02/RN-S04)
 *   - building_id -> buildings con ON DELETE RESTRICT: un edificio con unidades
 *     asociadas no se borra, se desactiva (RN-S02)
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
        DB::statement('DROP TABLE IF EXISTS property_units');
        DB::statement('DROP INDEX IF EXISTS building_floors_building_id_id_unique');
    }

    /**
     * PostgreSQL: unicidad NULLS NOT DISTINCT + FK compuesta (S11).
     */
    private function upPostgres(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE property_units (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                building_id bigint NULL REFERENCES buildings(id) ON DELETE RESTRICT,
                building_floor_id bigint NULL,
                unit_type varchar(255) NOT NULL,
                number varchar(255) NOT NULL,
                internal_code varchar(255) NULL,
                area_m2 numeric(10, 2) NULL,
                coefficient numeric(8, 6) NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                notes text NULL,
                settings jsonb NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT property_units_uuid_unique UNIQUE (uuid),
                CONSTRAINT property_units_building_floor_building_check
                    CHECK (building_floor_id IS NULL OR building_id IS NOT NULL)
            )
        SQL);

        // Índice único requerido por la FK compuesta (building_floor_id -> building_id).
        DB::statement('CREATE UNIQUE INDEX building_floors_building_id_id_unique ON building_floors (building_id, id)');

        // Unicidad del número por (conjunto, edificio, tipo) incluyendo building_id nulo.
        DB::statement(<<<'SQL'
            ALTER TABLE property_units
            ADD CONSTRAINT property_units_unique_number_nnd
            UNIQUE NULLS NOT DISTINCT (residential_complex_id, building_id, unit_type, number)
        SQL);

        // Coherencia piso↔edificio: el piso debe pertenecer al mismo edificio.
        // RESTRICT (no SET NULL): en una FK compuesta, SET NULL anularía también
        // building_id, y el borrado en cascada de un edificio dejaría la unidad con
        // building_id nulo y building_floor_id todavía presente. Con RESTRICT el
        // borrado se rechaza con un error claro (RN-S02: un edificio con unidades
        // asociadas no se elimina, se desactiva).
        DB::statement(<<<'SQL'
            ALTER TABLE property_units
            ADD CONSTRAINT property_units_building_floor_fk
            FOREIGN KEY (building_id, building_floor_id)
            REFERENCES building_floors (building_id, id)
            ON DELETE RESTRICT
        SQL);

        // Índice único que sostiene la FK compuesta de apartments -> property_units.
        DB::statement('CREATE UNIQUE INDEX property_units_residential_complex_id_id_unique ON property_units (residential_complex_id, id)');
    }

    /**
     * SQLite (entorno de pruebas): equivalencia semántica de las mismas reglas.
     *
     * - La unicidad con building_id nulo se modela con un índice único sobre
     *   ifnull(building_id, 0), que en SQLite sí trata los NULL como iguales.
     * - La FK compuesta se declara inline (SQLite no permite ADD CONSTRAINT).
     */
    private function upSqlite(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE property_units (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid varchar(36) NOT NULL,
                residential_complex_id INTEGER NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                building_id INTEGER NULL REFERENCES buildings(id) ON DELETE RESTRICT,
                building_floor_id INTEGER NULL,
                unit_type varchar(255) NOT NULL,
                number varchar(255) NOT NULL,
                internal_code varchar(255) NULL,
                area_m2 decimal(10, 2) NULL,
                coefficient decimal(8, 6) NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                notes text NULL,
                settings text NULL,
                created_at datetime NULL,
                updated_at datetime NULL,
                CONSTRAINT property_units_uuid_unique UNIQUE (uuid),
                CONSTRAINT property_units_building_floor_building_check
                    CHECK (building_floor_id IS NULL OR building_id IS NOT NULL),
                CONSTRAINT property_units_building_floor_fk
                    FOREIGN KEY (building_id, building_floor_id)
                    REFERENCES building_floors (building_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS building_floors_building_id_id_unique ON building_floors (building_id, id)');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS property_units_number_unique ON property_units (residential_complex_id, ifnull(building_id, 0), unit_type, number)');

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS property_units_residential_complex_id_id_unique ON property_units (residential_complex_id, id)');
    }
};
