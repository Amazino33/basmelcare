<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brings existing rows in line with NormalisesName, which stores `name`
     * uppercase and trimmed. Products were done separately; this covers the rest.
     */
    private array $tables = ['categories', 'customers', 'suppliers', 'locations', 'branches'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
                continue;
            }

            DB::table($table)
                ->select('id', 'name')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        if ($row->name === null || trim((string) $row->name) === '') {
                            continue;
                        }

                        $normalised = strtoupper(trim((string) $row->name));

                        if ($normalised !== $row->name) {
                            DB::table($table)->where('id', $row->id)->update(['name' => $normalised]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // Original casing is not recoverable; uppercase is the intended form.
    }
};
