<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relación con el conjunto residencial
            $table->foreignId('residential_complex_id')
                ->constrained('residential_complexes')
                ->onDelete('cascade');

            // Identificación de la unidad
            $table->string('numero'); // ej: "101", "2A", "PH-01"
            $table->string('piso')->nullable();
            $table->string('torre')->nullable(); // Para edificios con múltiples torres
            $table->string('tipo'); // Casa, Apartamento, Local, Parqueadero, Bodega

            // Metadatos
            $table->integer('area_m2')->nullable();
            $table->integer('habitaciones')->nullable();
            $table->integer('banos')->nullable();

            // Estado
            $table->enum('status', ['draft', 'active', 'inactive', 'maintenance'])->default('draft');

            // Configuraciones adicionales (JSONB)
            $table->jsonb('settings')->default('{}');

            $table->timestamps();

            // Índices
            $table->index('status');
            $table->index('residential_complex_id');
            $table->unique(['residential_complex_id', 'numero', 'torre']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
