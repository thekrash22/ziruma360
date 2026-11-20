<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * parking_facilities (spec §3.10 / S55).
 *
 * Instalaciones de parqueadero comunitario del conjunto. El core registra la
 * instalacion (nombre, tipo, capacidad total, capacidad operativa, modo de
 * asignacion, estado); la logica de reservas, disponibilidad por intervalo y
 * concurrencia pertenece al modulo de rentas cortas y no se implementa aqui.
 *
 * `facility_type` y `assignment_mode` son cadenas libres: el spec §3.10 no
 * enumera sus valores, asi que se guardan tal cual.
 *
 * Las garantias se implementan a nivel motor con SQL crudo porque el Blueprint
 * de Laravel no soporta:
 *   - CHECK de capacidades no negativas
 *   - CHECK de operational_capacity <= total_capacity cuando ambas existen
 *   - CHECK de dominio de status
 *   - indice unico (residential_complex_id, id) que sostiene la FK compuesta
 *     de los espacios numerados
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE parking_facilities (
                id bigserial PRIMARY KEY,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                name varchar(255) NOT NULL,
                facility_type varchar(255) NOT NULL,
                total_capacity integer NULL,
                operational_capacity integer NULL,
                assignment_mode varchar(255) NOT NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                notes text NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT parking_facilities_total_capacity_check
                    CHECK (total_capacity IS NULL OR total_capacity >= 0),
                CONSTRAINT parking_facilities_operational_capacity_check
                    CHECK (operational_capacity IS NULL OR operational_capacity >= 0),
                CONSTRAINT parking_facilities_capacity_order_check
                    CHECK (total_capacity IS NULL OR operational_capacity IS NULL OR operational_capacity <= total_capacity),
                CONSTRAINT parking_facilities_status_check
                    CHECK (status IN ('active', 'inactive'))
            )
        SQL);

        // Índice único que sostiene la FK compuesta de los espacios numerados
        // -> parking_facilities (residential_complex_id, id).
        DB::statement('CREATE UNIQUE INDEX parking_facilities_residential_complex_id_id_unique ON parking_facilities (residential_complex_id, id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS parking_facilities');
    }
};
