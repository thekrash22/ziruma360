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
        Schema::create('residential_complexes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Identity
            $table->string('name');
            $table->string('code')->unique();
            $table->string('legal_identifier')->nullable();

            // Location
            $table->string('address');
            $table->string('city');
            $table->string('state')->nullable();
            $table->char('country_code', 2)->default('CO');

            // Regional settings
            $table->string('timezone')->default('America/Bogota');
            $table->char('currency_code', 3)->default('COP');

            // Status
            $table->enum('status', ['draft', 'active', 'suspended', 'archived'])->default('draft');

            // Policies (JSONB)
            $table->jsonb('settings')->default('{}');

            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residential_complexes');
    }
};
