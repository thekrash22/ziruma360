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
        Schema::create('property_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('residential_complex_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('building_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('building_floor_id')->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('unit_type'); // apartment, private_parking, storage, commercial_unit, other
            $table->string('number');
            $table->string('internal_code')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->decimal('coefficient', 8, 6)->nullable();
            $table->string('status')->default('active'); // active, inactive, under_review
            $table->text('notes')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            // Unique number per building and type (allows null building_id)
            $table->unique(['building_id', 'unit_type', 'number'], 'property_units_unique_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_units');
    }
};
