<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    /**
     * El proyecto es PostgreSQL: la suite corre contra una base de pruebas
     * dedicada (configurada en phpunit.xml), nunca contra la base de
     * desarrollo. La base se crea si todavia no existe.
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->ensureTestDatabaseExists();

        return $app;
    }

    private function ensureTestDatabaseExists(): void
    {
        $config = config('database.connections.pgsql');

        $database = $config['database'];

        if ($database === '' || $database === ':memory:') {
            return;
        }

        $pdo = new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=postgres', $config['host'], $config['port']),
            $config['username'],
            $config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
        $statement->execute(['database' => $database]);

        if ($statement->fetchColumn() === false) {
            $pdo->exec(sprintf('CREATE DATABASE "%s"', str_replace('"', '', $database)));
        }
    }
}
