<?php

namespace Tests\Feature;

use App\Enums\OwnershipType;
use App\Enums\PropertyUnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * RN-P04 bajo escrituras concurrentes (PostgreSQL): los triggers toman un
 * bloqueo de fila sobre la unidad, asi que dos escrituras concurrentes de la
 * misma unidad se serializan y la segunda ve el total ya confirmado.
 *
 * La segunda conexion es un PDO crudo a proposito: las filas de referencia
 * tienen que estar confirmadas (si no, el bloqueo observado seria el de la
 * insercion y no el del trigger) y por eso escapan del rollback de
 * RefreshDatabase, asi que se borran explicitamente al terminar la prueba.
 */
class PropertyUnitOwnershipConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private ?PDO $second = null;

    /** @var array<string, int> */
    private array $referenceRows = [];

    protected function tearDown(): void
    {
        // Primero el framework revierte la transaccion principal; despues se
        // borran las filas de referencia, que estaban confirmadas en la
        // segunda conexion.
        parent::tearDown();

        $this->purgeReferenceRows();
    }

    public function test_the_insert_check_holds_a_row_lock_on_the_unit(): void
    {
        $second = $this->secondConnection();
        $rows = $this->createReferenceRows($second);

        // La conexion principal inserta la titularidad y deja la transaccion
        // abierta: el trigger de RN-P04 debe haber tomado la fila de la unidad.
        DB::table('property_unit_ownerships')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $rows['residential_complexes'],
            'property_unit_id' => $rows['property_units'],
            'person_id' => $rows['people'],
            'ownership_type' => OwnershipType::OWNER->value,
            'percentage' => 60,
            'valid_from' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $second->exec("SET lock_timeout = '300ms'");

        // Si el trigger no tomara el bloqueo, esta sentencia obtendria la
        // fila de la unidad de inmediato y no habria error.
        $this->assertLockTimeout(fn () => $second->query(
            'SELECT 1 FROM property_units WHERE id = '.$rows['property_units'].' FOR UPDATE'
        ));
    }

    public function test_the_update_check_holds_a_row_lock_on_the_unit(): void
    {
        $second = $this->secondConnection();
        $rows = $this->createReferenceRows($second);

        $ownershipId = $this->insertOwnership($second, $rows, 60);

        // La transaccion principal actualiza la titularidad (un dato que no
        // es un hecho del registro): el trigger de UPDATE de RN-P04 debe tomar
        // el mismo bloqueo de fila sobre la unidad.
        DB::table('property_unit_ownerships')->where('id', $ownershipId)->update([
            'notes' => 'Anotacion posterior',
            'updated_at' => now(),
        ]);

        $second->exec("SET lock_timeout = '300ms'");

        $this->assertLockTimeout(fn () => $second->query(
            'SELECT 1 FROM property_units WHERE id = '.$rows['property_units'].' FOR UPDATE'
        ));
    }

    /**
     * @param  array<string, int>  $rows
     */
    private function assertLockTimeout(callable $attempt): void
    {
        try {
            $attempt();
        } catch (PDOException $exception) {
            $this->assertStringContainsString('lock timeout', $exception->getMessage());

            return;
        }

        $this->fail('El trigger de RN-P04 no bloqueo la fila de la unidad.');
    }

    private function secondConnection(): PDO
    {
        if ($this->second instanceof PDO) {
            return $this->second;
        }

        $config = config('database.connections.pgsql');

        $this->second = new PDO(
            sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'],
                $config['port'],
                $config['database'],
            ),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        return $this->second;
    }

    /**
     * @return array<string, int>
     */
    private function createReferenceRows(PDO $second): array
    {
        $now = now();

        $complexId = $this->insert($second, 'residential_complexes', [
            'uuid' => (string) Str::uuid(),
            'name' => 'Conjunto Concurrencia',
            'code' => 'CONC-1',
            'address' => 'Calle 1',
            'city' => 'Cali',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $buildingId = $this->insert($second, 'buildings', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'name' => 'Edificio Concurrencia',
            'code' => 'CONC-B1',
            'building_type' => 'residential',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $floorId = $this->insert($second, 'building_floors', [
            'uuid' => (string) Str::uuid(),
            'building_id' => $buildingId,
            'floor_number' => 1,
            'floor_type' => 'residential',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $unitId = $this->insert($second, 'property_units', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'building_id' => $buildingId,
            'building_floor_id' => $floorId,
            'unit_type' => PropertyUnitType::APARTMENT->value,
            'number' => 'C-101',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->insert($second, 'people', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'full_name' => 'Titular Concurrente',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->referenceRows;
    }

    /**
     * @param  array<string, int>  $rows
     */
    private function insertOwnership(PDO $second, array $rows, int $percentage): int
    {
        $now = now();

        return $this->insert($second, 'property_unit_ownerships', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $rows['residential_complexes'],
            'property_unit_id' => $rows['property_units'],
            'person_id' => $rows['people'],
            'ownership_type' => OwnershipType::OWNER->value,
            'percentage' => $percentage,
            'valid_from' => $now->toDateString(),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insert(PDO $second, string $table, array $values): int
    {
        $columns = array_keys($values);

        $statement = $second->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(fn (string $column): string => ':'.$column, $columns)),
        ));

        foreach ($values as $column => $value) {
            $statement->bindValue(':'.$column, $value);
        }

        $statement->execute();

        $id = (int) $statement->fetchColumn();

        $this->referenceRows[$table] = $id;

        return $id;
    }

    private function purgeReferenceRows(): void
    {
        if ($this->second === null) {
            return;
        }

        foreach ([
            'property_unit_ownerships',
            'property_units',
            'people',
            'building_floors',
            'buildings',
            'residential_complexes',
        ] as $table) {
            if (isset($this->referenceRows[$table])) {
                $this->second->exec(
                    'DELETE FROM '.$table.' WHERE id = '.(int) $this->referenceRows[$table]
                );
            }
        }
    }
}
