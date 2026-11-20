<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Atomicidad del documento de identidad en `people` (spec §4.2, RN-P02, T17).
 *
 * MOTIVO
 * ------
 * El indice unico parcial `people_document_unique` cubre
 * `(residential_complex_id, document_type, document_number)` y solo exige
 * unicidad cuando AMBOS campos estan presentes. Por eso el indice es
 * PARCIAL POR PAR y no solo por numero: el MISMO numero con distinto tipo de
 * documento es una identidad distinta (un `CC/12345678` y un `CE/12345678`
 * son dos personas diferentes, no un duplicado). Un indice unico sobre el
 * numero suelto rechazaria un caso legitimo del dominio.
 *
 * Esa misma razon exige que el documento sea ATOMICO en la base: una
 * persona tiene un documento completo (tipo y numero) o no tiene documento.
 * Con el esquema anterior, una persona con solo `document_number` (tipo
 * nulo) convivía con otra que si tenia el par completo, y el indice parcial
 * no las veia: el hueco de RN-P02 quedaba abierto (el primer alta con solo
 * numero no entraba en conflicto con nadie).
 *
 * El CHECK de abajo cierra ese hueco en el motor: no se admiten filas con
 * solo uno de los dos campos. La aplicacion valida lo mismo y responde 422
 * (ver `StorePersonRequest`, `UpdatePersonRequest` y el controlador web),
 * de modo que el error de negocio llegue antes que el error del motor.
 *
 * La migracion es EXPLICITA con los datos que ya existen: antes de agregar
 * la constraint comprueba si hay filas que la violen y, si las hay, falla
 * con un mensaje accionable que las lista. No borra ni modifica ninguna
 * fila en silencio: el dominio se esta formando en una base en desarrollo
 * sin datos (0 filas en `people` verificadas), y si alguna vez apareciera
 * una fila inconsistente, la decision de como resolverla (completar el
 * documento o dejarlo vacio) es de producto, no de una migracion.
 *
 * Nota: el CHECK exige la presencia o la ausencia de los dos campos, no que
 * no sean cadenas vacias; la aplicacion normaliza la cadena vacia a nulo
 * antes de escribir, de modo que el dato guardado nunca es una cadena
 * vacia.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $violations = $this->violatingRows();

        if ($violations->isNotEmpty()) {
            $lines = $violations
                ->map(fn (object $row): string => sprintf(
                    '  - id=%s uuid=%s nombre="%s" document_type=%s document_number=%s',
                    $row->id,
                    $row->uuid,
                    $row->full_name,
                    $row->document_type === null ? 'NULL' : '"'.$row->document_type.'"',
                    $row->document_number === null ? 'NULL' : '"'.$row->document_number.'"'
                ))
                ->implode(PHP_EOL);

            throw new RuntimeException(sprintf(
                'No se puede agregar el CHECK de atomicidad del documento de identidad: hay %d fila(s) en `people` con solo uno de los dos campos (document_type / document_number). '
                .'Resuelvelas antes de migrar (completar el par o dejar el documento vacio) y vuelve a ejecutar la migracion. Filas afectadas:%s%s',
                $violations->count(),
                PHP_EOL,
                $lines
            ));
        }

        DB::statement(<<<'SQL'
            ALTER TABLE people
            ADD CONSTRAINT people_identity_document_atomic
            CHECK (
                (document_number IS NULL AND document_type IS NULL)
                OR (document_number IS NOT NULL AND document_type IS NOT NULL)
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE people DROP CONSTRAINT IF EXISTS people_identity_document_atomic');
    }

    /**
     * Filas que violan la atomicidad del documento de identidad.
     *
     * @return Collection<int, object>
     */
    protected function violatingRows(): Collection
    {
        return DB::table('people')
            ->select(['id', 'uuid', 'full_name', 'document_type', 'document_number'])
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('document_number')->whereNull('document_type');
                })->orWhere(function ($query): void {
                    $query->whereNull('document_number')->whereNotNull('document_type');
                });
            })
            ->orderBy('id')
            ->get();
    }
};
