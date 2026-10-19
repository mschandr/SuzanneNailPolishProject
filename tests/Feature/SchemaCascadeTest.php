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
        // A column named *user_id with no foreign key is invisible to the check above. These are known and
        // cleaned up by the deletion code itself.
        $known = ['sessions.user_id'];
        $unconstrained = [];

        $tables = DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'");
        foreach ($tables as $table) {
            $fks = collect(DB::select("pragma foreign_key_list('{$table->name}')"))->pluck('from')->all();
            foreach (DB::select("pragma table_info('{$table->name}')") as $col) {
                if (str_ends_with($col->name, 'user_id') && ! in_array($col->name, $fks, true)) {
                    $unconstrained[] = "{$table->name}.{$col->name}";
                }
            }
        }

        $this->assertEqualsCanonicalizing($known, $unconstrained, 'A new user_id column without a foreign key needs explicit cleanup in account deletion.');
    }
}
