<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los roles no se eliminan: se desactivan (spec S88).
     *
     * La columna distingue el rol vivo del rol desactivado, que conserva su
     * traza y sus asignaciones pero deja de estar en vigor.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
