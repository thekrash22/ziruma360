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

    /** @var array<int, array{table: string, id: int}> */
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
     * Dos escrituras concurrentes sobre la misma unidad: la segunda queda
     * esperando el bloqueo de fila que toma el trigger de RN-P04 y, cuando la
     * primera se confirma, el trigger ve el total real y rechaza la segunda.
     */
    public function test_a_competing_write_is_rejected_after_the_first_one_commits(): void
    {
        $second = $this->secondConnection();
        $rows = $this->createReferenceRows($second);

        // Escritor A: 60% en una transaccion abierta (la unidad queda
        // bloqueada por el trigger y el total todavia no es visible).
        $second->beginTransaction();
        $this->insertOwnership($second, $rows, 60, $rows['people']);

        [$parentSide, $childSide] = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );

        $pid = pcntl_fork();

        if ($pid === 0) {
            // Escritor B, en otro proceso: intenta escribir 50%.
            fclose($parentSide);

            $outcome = 'inserted';

            try {
                $child = $this->thirdConnection();
                $child->beginTransaction();

                // Senal de "voy a insertar" con el PID del backend: el padre
                // espera a que la escritura este efectivamente bloqueada en
                // vez de asumirlo por un tiempo fijo.
                fwrite($childSide, json_encode([
                    'pid' => (int) $child->query('SELECT pg_backend_pid()')->fetchColumn(),
                ])."\n");
                fflush($childSide);

                $child->exec($this->ownershipInsertSql($rows, 50, $rows['people_secondary']));
                $child->rollBack();
            } catch (PDOException $exception) {
                $outcome = 'error: '.$exception->getMessage();
            }

            fwrite($childSide, $outcome."\n");
            fflush($childSide);

            // Salida dura: el cierre ordenado de PHP destruiria los PDO
            // heredados del padre y cortaria su conexion con el servidor.
            posix_kill(posix_getpid(), SIGKILL);
        }

        fclose($childSide);

        // El hijo ya va a insertar: se espera su senal antes de confirmar.
        $signal = json_decode((string) fgets($parentSide), true);
        $this->assertIsArray($signal);
        $this->assertTrue($this->waitForLockWait((int) $signal['pid']));

        // Escritor A confirma y libera el bloqueo de la unidad.
        $second->commit();

        $outcome = (string) stream_get_contents($parentSide);

        fclose($parentSide);
        pcntl_waitpid($pid, $status);

        $this->assertStringContainsString('error:', $outcome);
        $this->assertStringContainsString('RN-P04', $outcome);
    }

    /**
     * Espera a que el backend del hijo este efectivamente esperando el
     * bloqueo de fila: sin esto la prueba podria pasar sin que hubiera
     * habido contencion real.
     */
    private function waitForLockWait(int $pid): bool
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $waiting = $this->secondConnection()->query(
                'SELECT count(*) FROM pg_stat_activity WHERE pid = '.$pid
                ." AND wait_event_type = 'Lock'"
            )->fetchColumn();

            if ((int) $waiting > 0) {
                return true;
            }

            usleep(20000);
        }

        return false;
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

    private function thirdConnection(): PDO
    {
        $config = config('database.connections.pgsql');

        return new PDO(
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
    }

    /**
     * @param  array<string, int>  $rows
     */
    private function ownershipInsertSql(array $rows, int $percentage, int $personId): string
    {
        return sprintf(
            'INSERT INTO property_unit_ownerships (uuid, residential_complex_id, property_unit_id, '
            .'person_id, ownership_type, percentage, valid_from, status, created_at, updated_at) '
            ."VALUES ('%s', %d, %d, %d, '%s', %d, CURRENT_DATE, 'active', NOW(), NOW())",
            (string) Str::uuid(),
            $rows['residential_complexes'],
            $rows['property_units'],
            $personId,
            OwnershipType::OWNER->value,
            $percentage,
        );
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

        $personId = $this->insert($second, 'people', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'full_name' => 'Titular Concurrente',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Un segundo titular: dos cotitulares distintos pueden coexistir y
        // compiten por el mismo total de porcentajes.
        $secondaryPersonId = $this->insert($second, 'people', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'full_name' => 'Titular Competidor',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'residential_complexes' => $complexId,
            'property_units' => $unitId,
            'people' => $personId,
            'people_secondary' => $secondaryPersonId,
        ];
    }

    /**
     * @param  array<string, int>  $rows
     */
    private function insertOwnership(PDO $second, array $rows, int $percentage, ?int $personId = null): int
    {
        $now = now();

        return $this->insert($second, 'property_unit_ownerships', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $rows['residential_complexes'],
            'property_unit_id' => $rows['property_units'],
            'person_id' => $personId ?? $rows['people'],
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

        $this->referenceRows[] = ['table' => $table, 'id' => $id];

        return $id;
    }

    private function purgeReferenceRows(): void
    {
        if ($this->second === null) {
            return;
        }

        // Orden de dependencias explicito: primero las filas que dependen de
        // otras (titularidades, personas, unidades) y al final el conjunto.
        // No se rely en el borrado en cascada de las tablas legacy.
        $order = [
            'property_unit_ownerships',
            'people',
            'property_units',
            'building_floors',
            'buildings',
            'residential_complexes',
        ];

        foreach ($order as $table) {
            foreach ($this->referenceRows as $row) {
                if ($row['table'] === $table) {
                    $this->second->exec(
                        'DELETE FROM '.$table.' WHERE id = '.(int) $row['id']
                    );
                }
            }
        }
    }
}
