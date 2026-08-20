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
    /** table => model class recorded in the audit trail */
    private array $tables = [
        'categories' => \App\Models\Category::class,
        'customers'  => \App\Models\Customer::class,
        'suppliers'  => \App\Models\Supplier::class,
        'locations'  => \App\Models\Location::class,
        'branches'   => \App\Models\Branch::class,
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $model) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'name')) {
                continue;
            }

            DB::table($table)
                ->select('id', 'name')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($table, $model) {
                    $changes = [];

                    foreach ($rows as $row) {
                        if ($row->name === null || trim((string) $row->name) === '') {
                            continue;
                        }

                        $normalised = strtoupper(trim((string) $row->name));

                        if ($normalised === $row->name) {
                            continue;
                        }

                        DB::table($table)->where('id', $row->id)->update(['name' => $normalised]);

                        // Original casing is not otherwise recoverable, so record it.
                        // Customer names in particular appear on receipts and
                        // WhatsApp messages, and this is a one-way change.
                        $changes[] = [
                            'user_id'         => null,   // migration, not a person
                            'auditable_type'  => $model,
                            'auditable_id'    => (string) $row->id,
                            'auditable_label' => $normalised,
                            'event'           => 'updated',
                            'field'           => 'name',
                            'old_value'       => $row->name,
                            'new_value'       => $normalised,
                            'created_at'      => now(),
                        ];
                    }

                    if ($changes && Schema::hasTable('audit_logs')) {
                        DB::table('audit_logs')->insert($changes);
                    }
                });
        }
    }

    public function down(): void
    {
        // Original casing is not recoverable; uppercase is the intended form.
    }
};
