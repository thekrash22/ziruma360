<?php

namespace Tests\Feature;

use App\Enums\OwnershipType;
use App\Enums\PropertyUnitType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * RN-P04 bajo escrituras concurrentes (solo PostgreSQL): el trigger toma un
 * bloqueo de fila sobre la unidad, asi que dos inserciones concurrentes de la
 * misma unidad se serializan y la segunda ve el total ya confirmado.
 */
class PropertyUnitOwnershipConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sum_check_holds_a_row_lock_on_the_unit(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('El bloqueo de fila es propio de PostgreSQL.');
        }

        config(['database.connections.pgsql_second' => array_merge(
            config('database.connections.pgsql'), ['name' => 'pgsql_second'],
        )]);
        $other = DB::connection('pgsql_second');

        // Los datos de referencia se confirman en otra conexion: si quedaran
        // sin confirmar, el bloqueo observado seria el de la transaccion de la
        // prueba y no el del trigger.
        $now = now();
        $complexId = $other->table('residential_complexes')->insertGetId([
            'uuid' => (string) Str::uuid(), 'name' => 'Conjunto Concurrencia', 'code' => 'CONC-1',
            'address' => 'Calle 1', 'city' => 'Cali', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $buildingId = $other->table('buildings')->insertGetId([
            'uuid' => (string) Str::uuid(), 'residential_complex_id' => $complexId,
            'name' => 'Edificio Concurrencia', 'code' => 'CONC-B1', 'building_type' => 'residential',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $floorId = $other->table('building_floors')->insertGetId([
            'uuid' => (string) Str::uuid(), 'building_id' => $buildingId, 'floor_number' => 1,
            'floor_type' => 'residential', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $unitId = $other->table('property_units')->insertGetId([
            'uuid' => (string) Str::uuid(), 'residential_complex_id' => $complexId,
            'building_id' => $buildingId, 'building_floor_id' => $floorId,
            'unit_type' => PropertyUnitType::APARTMENT->value, 'number' => 'C-101',
            'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $personId = $other->table('people')->insertGetId([
            'uuid' => (string) Str::uuid(), 'residential_complex_id' => $complexId,
            'full_name' => 'Titular Concurrente', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // La conexion principal inserta la titularidad y deja la transaccion
        // abierta: el trigger de RN-P04 debe haber tomado la fila de la unidad.
        DB::table('property_unit_ownerships')->insert([
            'uuid' => (string) Str::uuid(), 'residential_complex_id' => $complexId,
            'property_unit_id' => $unitId, 'person_id' => $personId,
            'ownership_type' => OwnershipType::OWNER->value, 'percentage' => 60,
            'valid_from' => $now->toDateString(), 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $other->statement("SET lock_timeout = '300ms'");

        // Si el trigger no tomara el bloqueo, esta sentencia obtendria la
        // fila de la unidad de inmediato y no habria error.
        $this->expectException(QueryException::class);
        $other->table('property_units')->where('id', $unitId)->lockForUpdate()->first();
    }
}
