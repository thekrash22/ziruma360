<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * parking_spaces (spec §3.10 / S55).
 *
 * Espacios numerados de una instalacion de parqueadero comunitario, si
 * aplica. El numero del espacio es unico dentro de su instalacion.
 *
 * La logica de reservas, disponibilidad por intervalo y concurrencia
 * pertenece al modulo de rentas cortas y no se implementa aqui.
 *
 * Las garantias se implementan a nivel motor con SQL crudo porque el Blueprint
 * de Laravel no soporta:
 *   - FK compuesta (residential_complex_id, parking_facility_id) ->
 *     parking_facilities (residential_complex_id, id), que rechaza cruces
 *     entre conjuntos (ACE-C02/ACE-P07)
 * *   - unicidad del numero de espacio por instalacion
 *   - CHECK de dominio de status
 *   - ON DELETE RESTRICT en la FK de la instalacion: una instalacion con
 *     espacios numerados no se borra, se desactiva (status inactive) y sus
 *     espacios conservan su historial
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE parking_spaces (
                id bigserial PRIMARY KEY,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                parking_facility_id bigint NOT NULL,
                number varchar(255) NOT NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                notes text NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT parking_spaces_parking_facility_fk
                    FOREIGN KEY (residential_complex_id, parking_facility_id)
                    REFERENCES parking_facilities (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT parking_spaces_status_check
                    CHECK (status IN ('active', 'inactive'))
            )
        SQL);

        // Unicidad del numero de espacio dentro de la instalacion.
        DB::statement('CREATE UNIQUE INDEX parking_spaces_parking_facility_id_number_unique ON parking_spaces (parking_facility_id, number)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS parking_spaces');
    }
};
