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
        Schema::create('residential_complex_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('residential_complex_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('status', ['invited', 'active', 'suspended', 'revoked'])
                ->default('invited');

            $table->foreignId('invited_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_active_at')->nullable();

            $table->timestamps();

            // Unique membership per user per complex
            $table->unique(['residential_complex_id', 'user_id']);

            $table->index('status');
            $table->index(['residential_complex_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residential_complex_memberships');
    }
};
