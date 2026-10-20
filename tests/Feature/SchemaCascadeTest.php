<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Account deletion relies on the database removing everything a User owns. This fails the moment someone adds
 * a table that points at users without ON DELETE CASCADE, so deletion can never quietly leave a person's data behind.
 */
class SchemaCascadeTest extends TestCase
{
    use RefreshDatabase;

    /** Foreign keys to users that deliberately do NOT cascade: table.column => allowed on-delete action. */
    private const EXCEPTIONS = [
        // The audit log must outlive the Admin who acted; the column is simply emptied.
        'moderation_log.admin_id' => 'SET NULL',
        // An import's history (what was added, when) is about the Catalog, not the Admin personally; it is kept.
        'imports.admin_id' => 'SET NULL',
    ];

    /** @return array<int, array{table: string, column: string, on_delete: string}> */
    private function foreignKeysToUsers(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            // Postgres: the foreign keys of the current schema that point at users.
            return array_map(fn ($r) => ['table' => $r->tbl, 'column' => $r->col, 'on_delete' => strtoupper($r->rule)], DB::select(
                "select kcu.table_name as tbl, kcu.column_name as col, rc.delete_rule as rule
                 from information_schema.referential_constraints rc
                 join information_schema.key_column_usage kcu
                   on kcu.constraint_name = rc.constraint_name and kcu.constraint_schema = rc.constraint_schema
                 join information_schema.constraint_column_usage ccu
                   on ccu.constraint_name = rc.unique_constraint_name or ccu.constraint_name = rc.constraint_name
                 where rc.constraint_schema = current_schema() and ccu.table_name = 'users' and ccu.table_schema = current_schema()
                   and kcu.table_name <> 'users'",
            ));
        }

        $found = [];
        $tables = DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'");

        foreach ($tables as $table) {
            foreach (DB::select("pragma foreign_key_list('{$table->name}')") as $fk) {
                if ($fk->table === 'users') {
                    $found[] = ['table' => $table->name, 'column' => $fk->from, 'on_delete' => strtoupper($fk->on_delete)];
                }
            }
        }

        return $found;
    }

    /** Columns named like *user_id that have no foreign key: invisible to the check above. @return array<int, string> */
    private function unconstrainedUserIdColumns(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            $columns = DB::select("select table_name as t, column_name as c from information_schema.columns where table_schema = current_schema() and column_name like '%user_id'");
            $constrained = collect($this->foreignKeysToUsers())->map(fn ($fk) => $fk['table'].'.'.$fk['column'])->all();

            return collect($columns)->map(fn ($r) => "{$r->t}.{$r->c}")->reject(fn ($c) => in_array($c, $constrained, true))->values()->all();
        }

        $unconstrained = [];
        foreach (DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'") as $table) {
            $fks = collect(DB::select("pragma foreign_key_list('{$table->name}')"))->pluck('from')->all();
            foreach (DB::select("pragma table_info('{$table->name}')") as $col) {
                if (str_ends_with($col->name, 'user_id') && ! in_array($col->name, $fks, true)) {
                    $unconstrained[] = "{$table->name}.{$col->name}";
                }
            }
        }

        return $unconstrained;
    }

    public function test_every_foreign_key_to_users_cascades_unless_explicitly_excepted(): void
    {
        $offenders = [];
        foreach ($this->foreignKeysToUsers() as $fk) {
            $key = "{$fk['table']}.{$fk['column']}";
            $expected = self::EXCEPTIONS[$key] ?? 'CASCADE';

            if ($fk['on_delete'] !== $expected) {
                $offenders[] = "{$key} is ON DELETE {$fk['on_delete']}, expected {$expected}";
            }
        }

        $this->assertSame([], $offenders, "Account deletion would leave data behind:\n".implode("\n", $offenders));
    }

    public function test_the_check_actually_sees_the_known_user_tables(): void
    {
        $tables = array_unique(array_column($this->foreignKeysToUsers(), 'table'));

        foreach (['locations', 'bottles', 'manicures', 'hearts', 'reports', 'polishes', 'moderation_log'] as $expected) {
            $this->assertContains($expected, $tables, "{$expected} should be recognised as pointing at users");
        }
    }

    public function test_tables_that_hold_a_user_id_without_a_foreign_key_are_listed_so_they_are_handled_explicitly(): void
    {
        // These are cleaned up by the account-deletion code itself, because the database cannot cascade them.
        $known = ['sessions.user_id'];

        $this->assertEqualsCanonicalizing($known, $this->unconstrainedUserIdColumns(), 'A new user_id column without a foreign key needs explicit cleanup in account deletion.');
    }
}
