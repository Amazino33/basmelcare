<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One canonical form of a customer's phone number, to match on.
 *
 * Numbers are typed however the person says them: 08031234567, 0803 123 4567,
 * +2348031234567, 234 803 123 4567. Matching compared the raw strings, so those
 * were four different customers - four purchase histories, four debts, and four
 * free consultations for one person.
 *
 * That matters most for an outreach, where the free visit is per customer and
 * most people are new. It is also why the customer list fills with the same
 * person several times over.
 *
 * The typed number is kept exactly as given: it is what staff recognise and
 * what is printed. This column is only for finding people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'phone_normalised')) {
                $table->string('phone_normalised', 20)->nullable()->after('phone');
                $table->index('phone_normalised');
            }
        });

        // Existing customers, so a lookup finds people who were registered
        // before this. Chunked: the customer list is the one table that grows
        // with every walk-in.
        DB::table('customers')
            ->select('id', 'phone')
            ->whereNotNull('phone')
            ->orderBy('id')
            ->chunk(500, function ($customers) {
                foreach ($customers as $customer) {
                    $normalised = static::normalise($customer->phone);

                    if ($normalised !== null) {
                        DB::table('customers')
                            ->where('id', $customer->id)
                            ->update(['phone_normalised' => $normalised]);
                    }
                }
            });
    }

    /**
     * Kept here as well as on the model so the backfill does not depend on
     * application code that may change after this migration has run.
     */
    private static function normalise(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        // Nigerian numbers, written every which way. 0803..., 234803...,
        // +234803... and a bare 803... are all the same line.
        if (str_starts_with($digits, '234') && strlen($digits) > 10) {
            $digits = substr($digits, 3);
        }

        $digits = ltrim($digits, '0');

        return $digits === '' ? null : $digits;
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'phone_normalised')) {
                $table->dropIndex(['phone_normalised']);
                $table->dropColumn('phone_normalised');
            }
        });
    }
};
