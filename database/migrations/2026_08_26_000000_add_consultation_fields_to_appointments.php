<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appointments become paid consultations.
 *
 * mode records how it happens - the system does not host video or messaging,
 * and is not pretending to. Staff arrange the call or the chat as they do
 * now; recording the mode is what lets it be priced and prepared for.
 *
 * provider_type is here from the start even though only pharmacists exist
 * today, so adding a doctor later is a new price and a new option rather than
 * a change to how consultations are stored.
 *
 * was_free is recorded rather than inferred. Whether a consultation was
 * inside the free allowance depends on the settings at the time, and those
 * change - so the answer has to be kept, not recalculated later against rules
 * that no longer apply.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Both applications share one database but keep their own migrations,
        // so whichever runs first creates these and the other must be a no-op.
        Schema::table('appointments', function (Blueprint $table) {
            if (! Schema::hasColumn('appointments', 'mode')) {
                $table->string('mode', 20)->default('physical')->after('description');
            }

            if (! Schema::hasColumn('appointments', 'provider_type')) {
                $table->string('provider_type', 20)->default('pharmacist')->after('mode');
            }

            if (! Schema::hasColumn('appointments', 'price')) {
                $table->decimal('price', 10, 2)->default(0)->after('provider_type');
            }

            if (! Schema::hasColumn('appointments', 'was_free')) {
                $table->boolean('was_free')->default(false)->after('price');
            }

            // free | pending | paid
            if (! Schema::hasColumn('appointments', 'payment_status')) {
                $table->string('payment_status', 20)->default('pending')->after('was_free');
            }

            if (! Schema::hasColumn('appointments', 'payment_reference')) {
                $table->string('payment_reference')->nullable()->after('payment_status');
            }

            if (! Schema::hasColumn('appointments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('payment_reference');
            }

            // Where to reach them for a video or text consultation. Kept on the
            // appointment rather than read from the customer: someone may want
            // a different number for a call than the one on their account.
            if (! Schema::hasColumn('appointments', 'contact')) {
                $table->string('contact')->nullable()->after('paid_at');
            }

            // Null when the customer booked it themselves.
            if (! Schema::hasColumn('appointments', 'booked_by')) {
                $table->foreignId('booked_by')->nullable()->after('contact')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('booked_by');
            $table->dropColumn([
                'mode', 'provider_type', 'price', 'was_free',
                'payment_status', 'payment_reference', 'paid_at', 'contact',
            ]);
        });
    }
};
