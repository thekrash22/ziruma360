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
        Schema::create('building_floors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('building_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->smallInteger('floor_number'); // Negative for basements
            $table->string('floor_type');
            $table->string('name')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            // Unique floor number per building
            $table->unique(['building_id', 'floor_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('building_floors');
    }
};
