<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('residential_complex_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('building_type');
            $table->unsignedSmallInteger('floor_count')->nullable();
            $table->unsignedSmallInteger('apartments_per_floor_reference')->nullable();
            $table->boolean('has_elevators')->default(false);
            $table->unsignedTinyInteger('elevator_count')->nullable();
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Unique code per complex
            $table->unique(['residential_complex_id', 'code']);
        });

        // Add check constraint for elevator validation (PostgreSQL only)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE buildings ADD CONSTRAINT buildings_elevator_check CHECK (NOT has_elevators OR elevator_count > 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buildings');
    }
};
