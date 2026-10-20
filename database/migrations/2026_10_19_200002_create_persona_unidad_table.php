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
        Schema::create('persona_unidad', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('persona_id')
                ->constrained('personas')
                ->onDelete('cascade');
            
            $table->foreignId('unidad_id')
                ->constrained('unidades')
                ->onDelete('cascade');

            // Relación específica
            $table->enum('tipo_relacion', ['propietario', 'inquilino', 'residente', 'responsable']);
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('es_principal')->default(false);

            $table->timestamps();

            // Índices
            $table->unique(['persona_id', 'unidad_id', 'tipo_relacion']);
            $table->index('tipo_relacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persona_unidad');
    }
};
