<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * property_unit_relations (spec §3.7 / S50).
 *
 * La vinculacion entre unidades (apartamento y parqueadero, deposito o anexo)
 * se registra como relacion, no como titularidad: uso, titularidad y
 * autorizacion son conceptos separados (RN-S05).
 *
 * Las garantias se implementan a nivel motor con SQL crudo porque el Blueprint
 * de Laravel no soporta:
 *   - FK compuesta (residential_complex_id, <unidad>) -> property_units
 *     (residential_complex_id, id), que rechaza cruces entre conjuntos
 *     (ACE-C02/ACE-P07)
 *   - CHECK de no relacion consigo misma
 *   - CHECK de vigencia (valid_until >= valid_from)
 *   - CHECK de dominio de relation_type y de status
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE property_unit_relations (
                id bigserial PRIMARY KEY,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                source_property_unit_id bigint NOT NULL,
                target_property_unit_id bigint NOT NULL,
                relation_type varchar(255) NOT NULL,
                valid_from date NULL,
                valid_until date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                notes text NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT property_unit_relations_source_fk
                    FOREIGN KEY (residential_complex_id, source_property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE CASCADE,
                CONSTRAINT property_unit_relations_target_fk
                    FOREIGN KEY (residential_complex_id, target_property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE CASCADE,
                CONSTRAINT property_unit_relations_not_self_check
                    CHECK (source_property_unit_id <> target_property_unit_id),
                CONSTRAINT property_unit_relations_validity_check
                    CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until >= valid_from),
                CONSTRAINT property_unit_relations_relation_type_check
                    CHECK (relation_type IN ('annex', 'use_assignment', 'adjacent')),
                CONSTRAINT property_unit_relations_status_check
                    CHECK (status IN ('active', 'inactive'))
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS property_unit_relations');
    }
};
