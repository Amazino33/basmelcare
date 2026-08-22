<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clears searches captured automatically.
     *
     * Every failed search used to be logged, so the list filled with typos and
     * half-typed words. A list like that gets ignored, which is worse than not
     * having one — the whole value of "asked for, not stocked" is that it is
     * short and every line is worth acting on.
     *
     * Capture is now opt-in: a salesperson taps to confirm a customer really
     * asked for something. Nothing of value is lost here — genuine demand gets
     * asked for again and flagged within days.
     */
    public function up(): void
    {
        if (Schema::hasTable('failed_searches')) {
            DB::table('failed_searches')->delete();
        }
    }

    public function down(): void
    {
        // The cleared rows were noise; there is nothing to restore.
    }
};
