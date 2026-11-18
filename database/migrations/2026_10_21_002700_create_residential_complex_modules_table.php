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
        Schema::create('residential_complex_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residential_complex_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('module_key');
            $table->boolean('enabled')->default(false);
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            // Unique: one entry per module per complex
            $table->unique(['residential_complex_id', 'module_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residential_complex_modules');
    }
};
