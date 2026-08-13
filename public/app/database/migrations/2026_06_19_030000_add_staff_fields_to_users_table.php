<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('cashier')->after('phone');
            $table->string('position')->nullable()->after('role');
            $table->date('employment_date')->nullable()->after('position');
            $table->decimal('salary', 12, 2)->nullable()->after('employment_date');
            $table->string('address')->nullable()->after('salary');
            $table->string('emergency_contact_name')->nullable()->after('address');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
            $table->string('status')->default('active')->after('emergency_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'role', 'position', 'employment_date', 'salary',
                'address', 'emergency_contact_name', 'emergency_contact_phone', 'status',
            ]);
        });
    }
};
