<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marking something as sourced, so it leaves the buying list.
 *
 * Two things can be unsellable: a drug a customer asked for that is not in the
 * catalogue at all, and one that is but has nothing on the shelf. Both need
 * the same "we have got this, stop showing it to me" mark, and both need it to
 * come back if the situation returns.
 *
 * So the mark is cleared rather than kept: on a product when stock actually
 * arrives, and on a search when somebody asks for it again and still finds
 * nothing. A mark that never expired would quietly hide a real shortage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('failed_searches', function (Blueprint $table) {
            if (! Schema::hasColumn('failed_searches', 'sourced_at')) {
                $table->timestamp('sourced_at')->nullable()->after('last_searched_at');
            }

            if (! Schema::hasColumn('failed_searches', 'sourced_by')) {
                $table->foreignId('sourced_by')->nullable()->after('sourced_at')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sourced_at')) {
                $table->timestamp('sourced_at')->nullable()->after('reorder_level');
            }

            if (! Schema::hasColumn('products', 'sourced_by')) {
                $table->foreignId('sourced_by')->nullable()->after('sourced_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('failed_searches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sourced_by');
            $table->dropColumn('sourced_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sourced_by');
            $table->dropColumn('sourced_at');
        });
    }
};
