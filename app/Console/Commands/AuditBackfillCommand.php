<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill of the complex of the audit scoped rows (spec S98).
 *
 * `building_floors`, `membership_roles` and `role_permissions` receive the
 * `residential_complex_id` column so the audit trail always has a complex.
 * The model completes the column when the row is created, but the rows that
 * were written before the migration (or outside the model) keep it empty:
 * this command fills the ones that can be resolved from their relation and
 * lists the orphans that cannot.
 *
 * The command exits with a failure code when orphans remain: they need a
 * manual decision (the relation points to a row that does not exist).
 */
class AuditBackfillCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'z360:audit-backfill';

    /**
     * @var string
     */
    protected $description = 'Backfills residential_complex_id on the audit scoped tables and lists the orphans that cannot be resolved';

    public function handle(): int
    {
        $result = $this->backfill();

        $this->line('Backfilled rows (complex resolved from the relation):');

        foreach ($result['filled'] as $table => $count) {
            $this->line(sprintf('  %s: %d', $table, $count));
        }

        $this->line('Platform catalog rows (no complex by design):');

        foreach ($result['platform'] as $table => $count) {
            $this->line(sprintf('  %s: %d', $table, $count));
        }

        if ($result['orphans'] === []) {
            $this->info('No orphans: every row resolves its complex.');

            return self::SUCCESS;
        }

        $this->error('Orphans that cannot be resolved:');

        foreach ($result['orphans'] as $table => $rows) {
            foreach ($rows as $row) {
                $this->line(sprintf(
                    '  %s: id=%d %s=%d',
                    $table,
                    $row['id'],
                    $row['relation'],
                    $row['relation_id']
                ));
            }
        }

        return self::FAILURE;
    }

    /**
     * Fills the complex of the rows that can resolve it and collects the ones
     * that cannot.
     *
     * @return array{
     *     filled: array<string, int>,
     *     platform: array<string, int>,
     *     orphans: array<string, list<array{id: int, relation: string, relation_id: int}>>
     * }
     */
    public function backfill(): array
    {
        $filled = [
            'building_floors' => $this->backfillFromRelation('building_floors', 'buildings', 'building_id'),
            'membership_roles' => $this->backfillFromRelation(
                'membership_roles',
                'residential_complex_memberships',
                'membership_id'
            ),
            'role_permissions' => $this->backfillFromRelation('role_permissions', 'roles', 'role_id'),
        ];

        $platform = [
            'role_permissions' => $this->countCatalogPermissions(),
        ];

        $orphans = [
            'building_floors' => $this->orphans('building_floors', 'buildings', 'building_id'),
            'membership_roles' => $this->orphans(
                'membership_roles',
                'residential_complex_memberships',
                'membership_id'
            ),
            'role_permissions' => $this->orphans('role_permissions', 'roles', 'role_id'),
        ];

        // Only the tables with orphans are reported.
        $orphans = array_filter($orphans, fn (array $rows): bool => $rows !== []);

        return [
            'filled' => $filled,
            'platform' => $platform,
            'orphans' => $orphans,
        ];
    }

    /**
     * Fills the complex of a table from the complex of its relation.
     *
     * @param  string  $table  The audited table.
     * @param  string  $relationTable  The table that holds the relation.
     * @param  string  $foreignKey  The foreign key of the relation.
     */
    private function backfillFromRelation(string $table, string $relationTable, string $foreignKey): int
    {
        return (int) DB::update(sprintf(
            'UPDATE %s SET residential_complex_id = %s.residential_complex_id FROM %s WHERE %s.%s = %s.id AND %s.residential_complex_id IS NULL AND %s.residential_complex_id IS NOT NULL',
            $table,
            $relationTable,
            $relationTable,
            $table,
            $foreignKey,
            $relationTable,
            $table,
            $relationTable
        ));
    }

    /**
     * The rows of a table whose relation does not exist anymore.
     *
     * @param  string  $table  The audited table.
     * @param  string  $relationTable  The table that holds the relation.
     * @param  string  $foreignKey  The foreign key of the relation.
     * @return list<array{id: int, relation: string, relation_id: int}>
     */
    private function orphans(string $table, string $relationTable, string $foreignKey): array
    {
        $rows = DB::table($table.' as audited')
            ->leftJoin($relationTable.' as related', 'related.id', '=', 'audited.'.$foreignKey)
            ->whereNull('audited.residential_complex_id')
            ->whereNull('related.id')
            ->get(['audited.id', 'audited.'.$foreignKey]);

        return $rows->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'relation' => $foreignKey,
            'relation_id' => (int) $row->{$foreignKey},
        ])->all();
    }

    /**
     * The permissions of the catalog roles: platform objects with no complex
     * by design (they are not orphans).
     */
    private function countCatalogPermissions(): int
    {
        return (int) DB::table('role_permissions as permission')
            ->join('roles as role', 'role.id', '=', 'permission.role_id')
            ->whereNull('permission.residential_complex_id')
            ->whereNull('role.residential_complex_id')
            ->count();
    }
}
