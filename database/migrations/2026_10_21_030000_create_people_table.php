<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * people (spec §4.2 / S14).
 *
 * Identidad por conjunto residencial: una persona puede ser propietaria,
 * residente o visitante sin tener cuenta de acceso (user_id nullable).
 *
 * Garantías a nivel de motor (SQL crudo porque el Blueprint de Laravel no
 * soporta índices parciales):
 *   - UNIQUE parcial (residential_complex_id, document_type, document_number)
 *     cuando ambos documentos están presentes (RN-P02).
 *   - Índice único (residential_complex_id, id) que sostiene la FK compuesta
 *     desde property_unit_ownerships (ACE-C02).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE people (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                user_id bigint NULL REFERENCES users(id) ON DELETE SET NULL,
                full_name varchar(255) NOT NULL,
                first_name varchar(255) NULL,
                last_name varchar(255) NULL,
                document_type varchar(255) NULL,
                document_number varchar(255) NULL,
                phone varchar(255) NULL,
                email varchar(255) NULL,
                birth_date date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                data_consent_at timestamp(0) without time zone NULL,
                data_consent_ref varchar(255) NULL,
                notes text NULL,
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT people_uuid_unique UNIQUE (uuid)
            )
        SQL);

        // RN-P02: unicidad del documento por conjunto, solo cuando ambos
        // campos de documento están presentes (índice único parcial).
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX people_document_unique
            ON people (residential_complex_id, document_type, document_number)
            WHERE document_type IS NOT NULL AND document_number IS NOT NULL
        SQL);

        // Índice único que sostiene la FK compuesta desde property_unit_ownerships.
        DB::statement('CREATE UNIQUE INDEX people_residential_complex_id_id_unique ON people (residential_complex_id, id)');

        DB::statement('CREATE INDEX people_user_id_index ON people (user_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS people');
    }

};
