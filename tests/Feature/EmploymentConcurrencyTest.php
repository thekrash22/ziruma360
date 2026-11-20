<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Un solo empleo activo por persona, tipo y empleador bajo escrituras
 * concurrentes (PostgreSQL): el control del trigger serializa la fila de la
 * persona (FOR UPDATE), de modo que la segunda alta concurrente espera el
 * bloqueo real y la base rechaza el empleo duplicado en vez de dejar dos
 * empleos activos equivalentes.
 *
 * La segunda conexion es un PDO crudo a proposito: las filas de referencia
 * tienen que estar confirmadas (si no, el bloqueo observado seria el de la
 * insercion y no el del trigger) y por eso escapan del rollback de
 * RefreshDatabase, asi que se borran explicitamente al terminar la prueba.
 */
class EmploymentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private ?PDO $second = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->second instanceof PDO) {
            $this->second->exec('DELETE FROM employments');
            $this->second->exec('DELETE FROM people');
            $this->second->exec('DELETE FROM residential_complexes');
        }
    }

    public function test_two_concurrent_active_employments_are_rejected(): void
    {
        $second = $this->secondConnection();
        $rows = $this->createReferenceRows($second);

        // Escritor A: alta de empleo activo, en transaccion abierta.
        $second->beginTransaction();
        $second->exec($this->employmentInsertSql($rows));

        [$parentSide, $childSide] = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_STREAM,
            STREAM_IPPROTO_IP
        );

        $pid = pcntl_fork();

        if ($pid === 0) {
            fclose($parentSide);
            $outcome = 'inserted';

            try {
                $child = $this->thirdConnection();
                $child->beginTransaction();

                fwrite($childSide, json_encode([
                    'pid' => (int) $child->query('SELECT pg_backend_pid()')->fetchColumn(),
                ])."\n");
                fflush($childSide);

                $child->exec($this->employmentInsertSql($rows));
                $child->rollBack();
            } catch (PDOException $exception) {
                $outcome = 'error: '.$exception->getMessage();
            }

            fwrite($childSide, $outcome."\n");
            fflush($childSide);

            posix_kill(posix_getpid(), SIGKILL);
        }

        fclose($childSide);

        $signal = json_decode((string) fgets($parentSide), true);
        $this->assertIsArray($signal);
        $waited = $this->waitForLockWait((int) $signal['pid']);

        $second->commit();

        $outcome = (string) stream_get_contents($parentSide);
        fclose($parentSide);
        pcntl_waitpid($pid, $status);

        $this->assertTrue($waited, 'El hijo no espero un bloqueo de fila: no hubo contencion real.');
        $this->assertStringContainsString('error:', $outcome);
        $this->assertStringContainsString('empleo activo', $outcome);
    }

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
     * @return array<string, int>
     */
    private function createReferenceRows(PDO $second): array
    {
        $now = now();

        $complexId = $this->insert($second, 'residential_complexes', [
            'uuid' => (string) Str::uuid(),
            'name' => 'Conjunto Concurrencia Empleos',
            'code' => 'CONC-E',
            'address' => 'Calle 1',
            'city' => 'Cali',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $personId = $this->insert($second, 'people', [
            'uuid' => (string) Str::uuid(),
            'residential_complex_id' => $complexId,
            'full_name' => 'Empleado Concurrente',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'residential_complexes' => $complexId,
            'people' => $personId,
        ];
    }

    /**
     * @param  array<string, int>  $rows
     */
    private function employmentInsertSql(array $rows): string
    {
        return sprintf(
            'INSERT INTO employments (uuid, residential_complex_id, person_id, employment_type, '
            .'valid_from, status, access_authorized, created_at, updated_at) '
            ."VALUES ('%s', %d, %d, 'complex_staff', CURRENT_DATE, 'active', false, NOW(), NOW())",
            (string) Str::uuid(),
            $rows['residential_complexes'],
            $rows['people'],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function insert(PDO $connection, string $table, array $values): int
    {
        $columns = array_keys($values);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $statement = $connection->prepare(sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table,
            implode(', ', $columns),
            $placeholders
        ));

        $statement->execute(array_values($values));

        return (int) $statement->fetchColumn();
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
}
