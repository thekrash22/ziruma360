<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retiro del modelo legacy (spec S40, T17 unidad C).
     *
     * Motivo: el legacy (`unidades`, `personas`, `persona_unidad`) queda sin
     * usuarios: las rutas web `/units` y `/people` y las rutas de API
     * `/api/v1/units` y `/api/v1/people` ya estan re-apuntadas al dominio
     * nuevo (`property_units`, `people`). Se retiran las tres tablas del
     * esquema.
     *
     * No hay migracion de datos (spec S41): T17 asume el legacy vacio (0
     * filas verificadas) y los datos legacy, si aparecieran en algun
     * ambiente, no se migran al dominio nuevo.
     *
     * Las migraciones que crearon las tablas NO se borran: el historial de
     * migraciones se conserva.
     *
     * El orden de borrado respeta las claves foraneas: primero la tabla
     * puente `persona_unidad` (que referencia a `personas` y a `unidades`),
     * despues `personas` y por ultimo `unidades`.
     */
    public function up(): void
    {
        Schema::dropIfExists('persona_unidad');
        Schema::dropIfExists('personas');
        Schema::dropIfExists('unidades');
    }

    /**
     * El retiro del legacy es definitivo (S40): las tablas no se restauran.
     *
     * El esquema legacy solo vuelve a existir si se revierte hasta antes de
     * las migraciones que lo creaban (`2026_10_19_200000`,
     * `2026_10_19_200001` y `2026_10_19_200002`), que se conservan en el
     * historial. Restaurarlas aqui significaria duplicar su DDL y dejar una
     * trampa: el codigo legacy ya no existe.
     */
    public function down(): void
    {
        // Sin accion: el legacy esta retirado de forma definitiva.
    }
};
