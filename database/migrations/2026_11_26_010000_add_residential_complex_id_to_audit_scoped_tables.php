<?php

use App\Console\Commands\AuditBackfillCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The complex of the audit trail (spec S98).
 *
 * `building_floors`, `membership_roles` and `role_permissions` receive the
 * `residential_complex_id` column (foreign key to `residential_complexes`,
 * with an index) so the audit trail of a write always has a complex
 * (RN-C04).
 *
 * The column stays nullable so the orphan rows are not invalidated, but the
 * model always completes it when the row is created and the backfill fills
 * the rows that can be resolved from their relation (floor -> building,
 * assignment -> membership, permission -> role).
 */
return new class extends Migration
{
    /**
     * The tables that receive the column, with the relation they resolve it
     * from.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private array $targets = [
        'building_floors' => ['buildings', 'building_id'],
        'membership_roles' => ['residential_complex_memberships', 'membership_id'],
        'role_permissions' => ['roles', 'role_id'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (array_keys($this->targets) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('residential_complex_id')
                    ->nullable()
                    ->constrained('residential_complexes')
                    ->nullOnDelete();

                $blueprint->index('residential_complex_id');
            });
        }

        // The rows that were written before the migration keep the column
        // empty: they are filled from their relation.
        app(AuditBackfillCommand::class)->backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys($this->targets) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropForeign(['residential_complex_id']);
                $blueprint->dropIndex(['residential_complex_id']);
                $blueprint->dropColumn('residential_complex_id');
            });
        }
    }
};
