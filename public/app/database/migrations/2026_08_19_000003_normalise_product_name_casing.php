<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product names were displayed uppercase by an accessor but stored as typed,
     * so the database held mixed casing. The model now normalises on write; this
     * brings existing rows in line so the two never disagree.
     */
    public function up(): void
    {
        DB::table('products')
            ->select('id', 'name')
            ->orderBy('id')
            ->chunk(200, function ($products) {
                $changes = [];

                foreach ($products as $product) {
                    $normalised = strtoupper(trim((string) $product->name));

                    if ($normalised === $product->name) {
                        continue;
                    }

                    DB::table('products')->where('id', $product->id)->update(['name' => $normalised]);

                    // The original casing cannot be reconstructed afterwards,
                    // so keep it in the trail rather than losing it.
                    $changes[] = [
                        'user_id'         => null,   // migration, not a person
                        'auditable_type'  => \App\Models\Product::class,
                        'auditable_id'    => (string) $product->id,
                        'auditable_label' => $normalised,
                        'event'           => 'updated',
                        'field'           => 'name',
                        'old_value'       => $product->name,
                        'new_value'       => $normalised,
                        'created_at'      => now(),
                    ];
                }

                if ($changes && Schema::hasTable('audit_logs')) {
                    DB::table('audit_logs')->insert($changes);
                }
            });
    }

    public function down(): void
    {
        // Original casing is not recoverable, and uppercase is now the intended
        // stored form, so there is nothing meaningful to reverse.
    }
};
