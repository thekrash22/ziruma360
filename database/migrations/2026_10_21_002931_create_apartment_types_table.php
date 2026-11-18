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
        Schema::create('apartment_types', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('residential_complex_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->decimal('coefficient', 8, 6)->nullable();
            $table->json('features')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            // Unique code per complex
            $table->unique(['residential_complex_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apartment_types');
    }
};
