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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('residential_complex_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('actor_user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action'); // created, updated, deleted, etc.
            $table->string('entity_type'); // Model class name
            $table->string('entity_id')->nullable(); // UUID or ID

            // Snapshots (null for delete actions)
            $table->jsonb('before_data')->nullable();
            $table->jsonb('after_data')->nullable();

            $table->timestamp('occurred_at');
            $table->string('correlation_id')->nullable(); // For tracing related actions

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index(['residential_complex_id', 'occurred_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index('correlation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
