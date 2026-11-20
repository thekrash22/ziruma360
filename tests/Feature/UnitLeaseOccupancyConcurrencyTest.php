<?php

namespace Tests\Feature;

use App\Enums\PropertyUnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * RN-P09 y RN-P10 bajo escrituras concurrentes (PostgreSQL): el trigger que
 * valida la ocupacion de un arrendamiento lee el estado del arrendamiento con
 * un bloqueo de comparticion, asi que una ocupacion insertada en paralelo a
 * la terminacion del arrendamiento espera y ve el estado definitivo en vez de
 * quedar vigente sobre un arrendamiento ya terminado.
 *
 * La segunda conexion es un PDO crudo a proposito: las filas de referencia
 * tienen que estar confirmadas (si no, el bloqueo observado seria el de la
 * insercion y no el del trigger) y por eso escapan del rollback de
 * RefreshDatabase, asi que se borran explicitamente al terminar la prueba.
 */
class UnitLeaseOccupancyConcurrencyTest extends TestCase
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

    public function test_the_occupancy_check_holds_a_row_lock_on_the_lease(): void
    {
        $second = $this->secondConnection();
        $rows = $this->createReferenceRows($second);

        // La conexion principal inserta una ocupacion vinculada al
        // arrendamiento: el trigger debe haber tomado la fila del
        // arrendamiento. La ocupacion que creo el trigger al registrar el
        // arrendamiento se cierra primero, porque solo puede haber una
        // ocupacion vigente del arrendatario por arrendamiento.
        DB::table('unit_occupancies')
            ->where('lease_id', $rows['unit_leases'])
            ->update([
                'status' => 'closed',
                'valid_until' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        DB::table('unit_occupancies')->insert([
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $rows['residential_complexes'],
            'property_unit_id' => $rows['property_units'],
            'person_id' => $rows['people'],
            'occupancy_type' => 'tenant_resident',
            'is_primary_contact' => false,
            'lease_id' => $rows['unit_leases'],
            'valid_from' => now()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $second->exec("SET lock_timeout = '300ms'");

        // Si el trigger no tomara el bloqueo, esta sentencia obtendria la
        // fila del arrendamiento de inmediato y no habria error.
        $this->assertLockTimeout(fn () => $second->query(
            'SELECT 1 FROM unit_leases WHERE id = '.$rows['unit_leases'].' FOR UPDATE'
        ));
    }

    /**
     * Terminacion del arrendamiento en paralelo con la insercion de una
     * ocupacion: la escritura concurrente espera el estado definitivo y la
     * base la rechaza (RN-P10) en vez de dejar una ocupacion vigente sobre un
     * arrendamiento ya terminado.
     */
    public function test_a_concurrent_termination_rejects_the_occupancy_insert(): void
    {
        $second = $this->secondConnection();
        $rows = $this->createReferenceRows($second);

        // Escritor A: termina el arrendamiento en una transaccion abierta. La
        // fila del arrendamiento queda bloqueada hasta que confirme.
        $second->beginTransaction();
        $second->exec("UPDATE unit_leases SET status = 'terminated' WHERE id = ".$rows['unit_leases']);

        [$parentSide, $childSide] = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );

        $pid = pcntl_fork();

        if ($pid === 0) {
            // Escritor B, en otro proceso: intenta registrar la ocupacion
            // del arrendatario.
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

                $child->exec($this->occupancyInsertSql($rows));
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

        // Escritor A confirma y libera el bloqueo del arrendamiento.
        $second->commit();

        $outcome = (string) stream_get_contents($parentSide);

        fclose($parentSide);
        pcntl_waitpid($pid, $status);

        $this->assertStringContainsString('error:', $outcome);
        $this->assertStringContainsString('RN-P10', $outcome);
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

    private function assertLockTimeout(callable $attempt): void
    {
        try {
            $attempt();
        } catch (PDOException $exception) {
            $this->assertStringContainsString('lock timeout', $exception->getMessage());

            return;
        }

        $this->fail('El trigger de RN-P09 no bloqueo la fila del arrendamiento.');
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
    private function occupancyInsertSql(array $rows): string
    {
        return sprintf(
            'INSERT INTO unit_occupancies (uuid, residential_complex_id, property_unit_id, '
            .'person_id, occupancy_type, is_primary_contact, lease_id, valid_from, status, '
            .'created_at, updated_at) '
            ."VALUES ('%s', %d, %d, %d, 'tenant_resident', false, %d, CURRENT_DATE, 'active', NOW(), NOW())",
            (string) Str::uuid(),
            $rows['residential_complexes'],
            $rows['property_units'],
            $rows['people'],
            $rows['unit_leases'],
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
            'name' => 'Conjunto Concurrencia Arrendamientos',
            'code' => 'CONC-L',
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
            'full_name' => 'Arrendatario Concurrente',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $leaseId = $this->insert($second, 'unit_leases', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'property_unit_id' => $unitId,
            'tenant_person_id' => $personId,
            'starts_on' => $now->toDateString(),
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'residential_complexes' => $complexId,
            'property_units' => $unitId,
            'people' => $personId,
            'unit_leases' => $leaseId,
        ];
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

        // Las ocupaciones que creo el trigger al registrar el arrendamiento
        // no estan entre las filas de referencia (las inserciona la base), asi
        // que se borran por lease_id antes de borrar los arrendamientos: la
        // FK de las ocupaciones es RESTRICT.
        foreach ($this->referenceRows as $row) {
            if ($row['table'] === 'unit_leases') {
                $this->second->exec(
                    'DELETE FROM unit_occupancies WHERE lease_id = '.(int) $row['id']
                );
            }
        }

        // Orden de dependencias explicito: primero las filas que dependen de
        // otras (ocupaciones, arrendamientos, personas, unidades) y al final
        // el conjunto. No se rely en el borrado en cascada de las tablas
        // legacy.
        $order = [
            'unit_occupancies',
            'unit_leases',
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
