<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * common_areas (spec §3.8 / S53).
 *
 * Las areas comunes son zonas de uso compartido del conjunto (piscina, salon
 * social, BBQ, gimnasio, juegos, canchas, terraza, parqueadero comunitario).
 * Son distintas de las unidades privadas y de los sistemas de asignacion de
 * parqueaderos: un area comun no es una `property_unit`.
 *
 * Las reglas de reserva se dejan preparadas (`is_reservable`), pero el modulo
 * de reservas es futuro.
 *
 * Las garantias se implementan a nivel motor con SQL crudo porque el Blueprint
 * de Laravel no soporta:
 *   - FK compuesta (residential_complex_id, building_id) -> buildings
 *     (residential_complex_id, id), que rechaza cruces entre conjuntos
 *     (ACE-C02/ACE-P07)
 *   - CHECK de dominio de area_type y de status
 *   - unicidad de code por conjunto
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Índice único requerido por la FK compuesta (building_id -> buildings).
        // Se crea antes de la tabla porque PostgreSQL exige que las columnas
        // referenciadas por una FK compuesta tengan un indice unico.
        DB::statement('CREATE UNIQUE INDEX buildings_residential_complex_id_id_unique ON buildings (residential_complex_id, id)');

        DB::statement(<<<'SQL'
            CREATE TABLE common_areas (
                id bigserial PRIMARY KEY,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                building_id bigint NULL,
                name varchar(255) NOT NULL,
                code varchar(255) NOT NULL,
                area_type varchar(255) NOT NULL,
                description text NULL,
                location varchar(255) NULL,
                capacity integer NULL,
                area_m2 numeric(10, 2) NULL,
                is_reservable boolean NOT NULL DEFAULT false,
                status varchar(255) NOT NULL DEFAULT 'active',
                features jsonb NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT common_areas_building_fk
                    FOREIGN KEY (residential_complex_id, building_id)
                    REFERENCES buildings (residential_complex_id, id)
                    ON DELETE NO ACTION,
                CONSTRAINT common_areas_area_type_check
                    CHECK (area_type IN ('pool', 'social_hall', 'bbq', 'gym', 'playground', 'court', 'terrace', 'parking', 'other')),
                CONSTRAINT common_areas_status_check
                    CHECK (status IN ('active', 'inactive', 'maintenance')),
                CONSTRAINT common_areas_capacity_check
                    CHECK (capacity IS NULL OR capacity >= 0),
                CONSTRAINT common_areas_area_m2_check
                    CHECK (area_m2 IS NULL OR area_m2 >= 0)
            )
        SQL);

        // Unicidad del code del area comun por conjunto.
        DB::statement('CREATE UNIQUE INDEX common_areas_residential_complex_id_code_unique ON common_areas (residential_complex_id, code)');

        // Índice único que sostiene la FK compuesta de las asignaciones de uso
        // exclusivo -> common_areas.
        DB::statement('CREATE UNIQUE INDEX common_areas_residential_complex_id_id_unique ON common_areas (residential_complex_id, id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS common_areas');
        DB::statement('DROP INDEX IF EXISTS buildings_residential_complex_id_id_unique');
    }
};
