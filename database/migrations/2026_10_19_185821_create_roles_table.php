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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('residential_complex_id')->nullable()
                ->constrained()
                ->cascadeOnDelete();

            $table->string('key')->index();
            $table->string('name');
            $table->string('description')->nullable();

            // System roles cannot be edited/deleted
            $table->boolean('is_system')->default(false);
            $table->boolean('is_editable')->default(true);

            $table->timestamps();

            // Key is unique within a complex, or globally if complex_id is null (system roles)
            $table->unique(['residential_complex_id', 'key']);
            $table->unique(['residential_complex_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
