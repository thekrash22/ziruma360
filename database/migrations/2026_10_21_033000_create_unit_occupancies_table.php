<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * unit_occupancies (spec §4.4 / S18).
 *
 * Quien habita o usa una unidad de forma continua, distinto de la titularidad
 * y de las visitas. Garantías a nivel de motor:
 *   - FK compuesta (residential_complex_id, property_unit_id) ->
 *     property_units y FK compuesta (residential_complex_id, person_id) ->
 *     people, con ON DELETE RESTRICT (ACE-C02).
 *   - FK compuesta (residential_complex_id, lease_id) -> unit_leases: la
 *     ocupacion de un arrendamiento pertenece al mismo conjunto que el
 *     arrendamiento.
 *   - RN-P07: UNIQUE parcial, un solo contacto principal vigente por unidad.
 *   - RN-P09: el arrendamiento crea o vincula la ocupacion tenant_resident
 *     del arrendatario, y toda ocupacion con lease_id debe ser de tipo
 *     tenant_resident, del mismo arrendatario y de la misma unidad.
 *   - RN-P10: al terminar el arrendamiento se cierra la ocupacion asociada y
 *     se revocan las delegaciones del arrendamiento.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE unit_occupancies (
                id bigserial PRIMARY KEY,
                uuid uuid NOT NULL,
                residential_complex_id bigint NOT NULL REFERENCES residential_complexes(id) ON DELETE CASCADE,
                property_unit_id bigint NOT NULL,
                person_id bigint NOT NULL,
                occupancy_type varchar(255) NOT NULL,
                is_primary_contact boolean NOT NULL DEFAULT false,
                lease_id bigint NULL,
                valid_from date NOT NULL,
                valid_until date NULL,
                status varchar(255) NOT NULL DEFAULT 'active',
                created_at timestamp(0) without time zone NULL,
                updated_at timestamp(0) without time zone NULL,
                CONSTRAINT unit_occupancies_uuid_unique UNIQUE (uuid),
                CONSTRAINT unit_occupancies_validity_check
                    CHECK (valid_until IS NULL OR valid_until >= valid_from),
                CONSTRAINT unit_occupancies_occupancy_type_check
                    CHECK (occupancy_type IN ('owner_resident', 'tenant_resident', 'family_member', 'other_resident')),
                CONSTRAINT unit_occupancies_status_check
                    CHECK (status IN ('active', 'closed')),
                CONSTRAINT unit_occupancies_property_unit_complex_fk
                    FOREIGN KEY (residential_complex_id, property_unit_id)
                    REFERENCES property_units (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT unit_occupancies_person_complex_fk
                    FOREIGN KEY (residential_complex_id, person_id)
                    REFERENCES people (residential_complex_id, id)
                    ON DELETE RESTRICT,
                CONSTRAINT unit_occupancies_lease_complex_fk
                    FOREIGN KEY (residential_complex_id, lease_id)
                    REFERENCES unit_leases (residential_complex_id, id)
                    ON DELETE RESTRICT
            )
        SQL);

        // RN-P07: un solo contacto principal vigente por unidad. El indice es
        // parcial: las ocupaciones cerradas o no principales no compiten.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unit_occupancies_primary_contact_unique
            ON unit_occupancies (property_unit_id)
            WHERE is_primary_contact AND status = 'active'
        SQL);

        // Un arrendamiento vigente tiene una sola ocupacion tenant_resident.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unit_occupancies_active_tenant_per_lease_unique
            ON unit_occupancies (lease_id)
            WHERE lease_id IS NOT NULL
              AND occupancy_type = 'tenant_resident'
              AND status = 'active'
        SQL);

        // Una persona no tiene dos ocupaciones vigentes en la misma unidad:
        // los residentes de una unidad son personas distintas.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX unit_occupancies_active_person_per_unit_unique
            ON unit_occupancies (property_unit_id, person_id)
            WHERE status = 'active'
        SQL);

        // RN-P09 (validacion): una ocupacion con lease_id es la ocupacion del
        // arrendatario de ese arrendamiento, en la unidad arrendada, y no
        // puede quedar vigente si el arrendamiento ya termino.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION unit_occupancies_rn_p09_check() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                lease_tenant bigint;
                lease_unit bigint;
                lease_status varchar(255);
            BEGIN
                IF NEW.lease_id IS NULL THEN
                    RETURN NEW;
                END IF;

                -- El estado del arrendamiento se lee con un bloqueo de
                -- comparticion: si el arrendamiento se termina en otra
                -- transaccion, esta escritura espera y recien entonces ve el
                -- estado definitivo. Sin este bloqueo, una ocupacion insertada
                -- en paralelo a la terminacion podria quedar vigente sobre un
                -- arrendamiento ya terminado.
                SELECT tenant_person_id, property_unit_id, status
                INTO lease_tenant, lease_unit, lease_status
                FROM unit_leases
                WHERE id = NEW.lease_id
                FOR SHARE;

                IF lease_tenant IS NULL THEN
                    RAISE EXCEPTION 'RN-P09: el arrendamiento % no existe', NEW.lease_id
                        USING ERRCODE = '23503';
                END IF;

                IF NEW.occupancy_type <> 'tenant_resident' THEN
                    RAISE EXCEPTION 'RN-P09: la ocupacion de un arrendamiento debe ser de tipo tenant_resident'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.person_id <> lease_tenant THEN
                    RAISE EXCEPTION 'RN-P09: la ocupacion de un arrendamiento pertenece al arrendatario'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.property_unit_id <> lease_unit THEN
                    RAISE EXCEPTION 'RN-P09: la ocupacion de un arrendamiento pertenece a la unidad arrendada'
                        USING ERRCODE = '23514';
                END IF;

                IF NEW.status = 'active' AND lease_status <> 'active' THEN
                    RAISE EXCEPTION 'RN-P10: el arrendamiento % esta terminado y no admite una ocupacion vigente', NEW.lease_id
                        USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER unit_occupancies_rn_p09_check_trigger
            BEFORE INSERT OR UPDATE ON unit_occupancies
            FOR EACH ROW EXECUTE FUNCTION unit_occupancies_rn_p09_check()
        SQL);

        // RN-P09 (creacion): al crearse un arrendamiento se crea la ocupacion
        // tenant_resident del arrendatario. Si un arrendamiento terminado
        // vuelve a estar vigente, se vuelve a crear la ocupacion: todo
        // arrendamiento vigente tiene su ocupacion.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION unit_leases_rn_p09_sync_occupancy() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status <> 'active' THEN
                    RETURN NEW;
                END IF;

                IF TG_OP = 'INSERT' THEN
                    INSERT INTO unit_occupancies (
                        uuid, residential_complex_id, property_unit_id, person_id,
                        occupancy_type, is_primary_contact, lease_id, valid_from,
                        valid_until, status, created_at, updated_at
                    ) VALUES (
                        gen_random_uuid(), NEW.residential_complex_id, NEW.property_unit_id,
                        NEW.tenant_person_id, 'tenant_resident', false, NEW.id,
                        NEW.starts_on, NULL, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    );
                ELSIF OLD.status <> 'active' THEN
                    INSERT INTO unit_occupancies (
                        uuid, residential_complex_id, property_unit_id, person_id,
                        occupancy_type, is_primary_contact, lease_id, valid_from,
                        valid_until, status, created_at, updated_at
                    ) VALUES (
                        gen_random_uuid(), NEW.residential_complex_id, NEW.property_unit_id,
                        NEW.tenant_person_id, 'tenant_resident', false, NEW.id,
                        NEW.starts_on, NULL, 'active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    );
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER unit_leases_rn_p09_sync_occupancy_trigger
            AFTER INSERT OR UPDATE OF status ON unit_leases
            FOR EACH ROW EXECUTE FUNCTION unit_leases_rn_p09_sync_occupancy()
        SQL);

        // RN-P10 (revocacion): un arrendamiento terminado no conserva
        // permisos delegables.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION unit_leases_rn_p10_revoke_delegations() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status IN ('ended', 'terminated') AND NEW.delegated_permissions IS NOT NULL THEN
                    NEW.delegated_permissions = NULL;
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER unit_leases_rn_p10_revoke_delegations_trigger
            BEFORE UPDATE ON unit_leases
            FOR EACH ROW EXECUTE FUNCTION unit_leases_rn_p10_revoke_delegations()
        SQL);

        // RN-P10 (cierre): al terminar el arrendamiento se cierra la ocupacion
        // asociada. La revocacion de las autorizaciones dependientes queda
        // para T15, porque property_unit_authorizations todavia no existe.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION unit_leases_rn_p10_close_occupancy() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.status IN ('ended', 'terminated') AND OLD.status = 'active' THEN
                    UPDATE unit_occupancies
                    SET status = 'closed',
                        valid_until = GREATEST(COALESCE(NEW.ends_on, CURRENT_DATE), valid_from),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE lease_id = NEW.id
                      AND status = 'active';
                END IF;

                RETURN NEW;
            END;
            $$
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER unit_leases_rn_p10_close_occupancy_trigger
            AFTER UPDATE OF status ON unit_leases
            FOR EACH ROW EXECUTE FUNCTION unit_leases_rn_p10_close_occupancy()
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS unit_leases_rn_p10_close_occupancy_trigger ON unit_leases');
        DB::statement('DROP FUNCTION IF EXISTS unit_leases_rn_p10_close_occupancy()');
        DB::statement('DROP TRIGGER IF EXISTS unit_leases_rn_p10_revoke_delegations_trigger ON unit_leases');
        DB::statement('DROP FUNCTION IF EXISTS unit_leases_rn_p10_revoke_delegations()');
        DB::statement('DROP TRIGGER IF EXISTS unit_leases_rn_p09_sync_occupancy_trigger ON unit_leases');
        DB::statement('DROP FUNCTION IF EXISTS unit_leases_rn_p09_sync_occupancy()');
        DB::statement('DROP TRIGGER IF EXISTS unit_occupancies_rn_p09_check_trigger ON unit_occupancies');
        DB::statement('DROP FUNCTION IF EXISTS unit_occupancies_rn_p09_check()');
        DB::statement('DROP INDEX IF EXISTS unit_occupancies_active_person_per_unit_unique');
        DB::statement('DROP INDEX IF EXISTS unit_occupancies_active_tenant_per_lease_unique');
        DB::statement('DROP INDEX IF EXISTS unit_occupancies_primary_contact_unique');
        DB::statement('DROP TABLE IF EXISTS unit_occupancies');
    }

};
