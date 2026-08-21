<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Searches at the till that matched nothing.
     *
     * This is demand the sales data can never show: a customer asks for a drug,
     * staff search for it, and it is not stocked. The sale that never happened
     * leaves no other trace.
     *
     * One row per distinct term with a running count, rather than one row per
     * keystroke — the POS searches as you type, so per-event logging would be
     * mostly noise and would grow without limit.
     */
    public function up(): void
    {
        Schema::create('failed_searches', function (Blueprint $table) {
            $table->id();
            $table->string('term');
            $table->unsignedInteger('times')->default(1);
            $table->foreignId('last_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_searched_at')->nullable();
            $table->timestamps();

            $table->unique(['term', 'branch_id']);
            $table->index('last_searched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_searches');
    }
};
