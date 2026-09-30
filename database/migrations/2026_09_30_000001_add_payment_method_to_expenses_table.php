<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('expenses') && !Schema::hasColumn('expenses', 'payment_method')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('payment_method', 20)->default('cash')->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'payment_method')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropColumn('payment_method');
            });
        }
    }
};

