<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateFromSqlite extends Command
{
    protected $signature = 'db:import-from-sqlite';
    protected $description = 'Copy all rows from the legacy SQLite database into MySQL';

    public function handle(): int
    {
        $tables = DB::connection('sqlite_legacy')
            ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT IN ('sqlite_sequence','migrations')");

        if (empty($tables)) {
            $this->error('No tables found in SQLite. Is database/database.sqlite present?');
            return self::FAILURE;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $row) {
            $table = $row->name;
            $count = DB::connection('sqlite_legacy')->table($table)->count();
            $this->info("Migrating `{$table}` ({$count} rows)…");

            DB::table($table)->truncate();

            DB::connection('sqlite_legacy')
                ->table($table)
                ->orderByRaw('rowid')
                ->chunk(500, function ($rows) use ($table) {
                    DB::table($table)->insert(
                        $rows->map(fn($r) => (array) $r)->all()
                    );
                });

            $this->line("  ✓ done");
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->newLine();
        $this->info('Import complete. Next steps on the server:');
        $this->line('  1. Edit .env  →  DB_CONNECTION=mysql');
        $this->line('  2. php artisan config:clear');
        $this->line('  3. Verify the site loads, then delete database/database.sqlite');

        return self::SUCCESS;
    }
}
