<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wrap existing string values into JSON arrays before changing column type
        DB::table('users')->lazyById()->each(function ($user) {
            if (!empty($user->role) && $user->role[0] !== '[') {
                DB::table('users')->where('id', $user->id)->update([
                    'role' => json_encode([$user->role]),
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('role')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->change();
        });

        // Extract first element back to a plain string
        DB::table('users')->lazyById()->each(function ($user) {
            $roles = json_decode($user->role, true);
            DB::table('users')->where('id', $user->id)->update([
                'role' => is_array($roles) ? ($roles[0] ?? 'cashier') : ($user->role ?? 'cashier'),
            ]);
        });
    }
};
