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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('mode', 20)->default('physical')->after('description');
            $table->string('provider_type', 20)->default('pharmacist')->after('mode');

            $table->decimal('price', 10, 2)->default(0)->after('provider_type');
            $table->boolean('was_free')->default(false)->after('price');
            // free | pending | paid
            $table->string('payment_status', 20)->default('pending')->after('was_free');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');

            // Where to reach them for a video or text consultation. Kept on the
            // appointment rather than read from the customer: someone may want
            // a different number for a call than the one on their account.
            $table->string('contact')->nullable()->after('paid_at');

            // Null when the customer booked it themselves.
            $table->foreignId('booked_by')->nullable()->after('contact')
                ->constrained('users')->nullOnDelete();
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
