<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
                foreach ($products as $product) {
                    $normalised = strtoupper(trim((string) $product->name));

                    if ($normalised !== $product->name) {
                        DB::table('products')
                            ->where('id', $product->id)
                            ->update(['name' => $normalised]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Original casing is not recoverable, and uppercase is now the intended
        // stored form, so there is nothing meaningful to reverse.
    }
};
