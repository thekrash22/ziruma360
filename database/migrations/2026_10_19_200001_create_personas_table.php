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
        Schema::create('personas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Datos personales
            $table->string('nombre');
            $table->string('apellido');
            $table->enum('tipo_documento', ['CC', 'CE', 'TI', 'PASAPORTE', 'NIT', 'PEP', 'PPT']);
            $table->string('numero_documento');
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('telefono_alternativo')->nullable();

            // Relación con usuario del sistema (opcional)
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            // Información adicional
            $table->text('notas')->nullable();
            $table->jsonb('settings')->default('{}');

            $table->timestamps();

            // Índices
            $table->unique(['tipo_documento', 'numero_documento']);
            $table->index('email');
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
